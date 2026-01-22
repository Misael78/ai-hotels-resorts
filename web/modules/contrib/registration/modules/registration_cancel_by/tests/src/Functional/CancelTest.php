<?php

namespace Drupal\Tests\registration_cancel_by\Functional;

/**
 * Tests cancel.
 *
 * @group registration
 */
class CancelTest extends RegistrationCancelByBrowserTestBase {

  /**
   * Tests canceling a registration.
   */
  public function testCancel() {
    // Register.
    $user = $this->drupalCreateUser([
      'access user profiles',
      'create conference registration self',
      'update own conference registration',
      'use registration cancel transition',
      'view own registration',
    ]);
    $this->drupalLogin($user);
    $this->drupalGet('user/' . $this->adminUser->id() . '/register');
    $this->assertSession()->buttonExists('Save Registration');
    $this->submitForm([], 'Save Registration');
    $this->assertSession()->pageTextContains('Registration has been saved.');

    // Cancel by date is not set, so cancel is allowed.
    $this->drupalGet('registration/1/transition/cancel');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Are you sure you want to Cancel registration #1?');

    // Cancel by date is in the past, so cancel is no longer allowed.
    $storage = $this->entityTypeManager->getStorage('registration_settings');
    $settings = $storage->load(1);
    $settings->set('cancel_by', '2020-01-01T00:00:00');
    $settings->save();
    $this->drupalGet('registration/1/transition/cancel');
    $this->assertSession()->statusCodeEquals(403);

    // Cancel by date is in the future, so cancel is allowed.
    $settings->set('cancel_by', '2220-01-01T00:00:00');
    $settings->save();
    $this->drupalGet('registration/1/transition/cancel', ['query' => ['destination' => '/user/' . $user->id() . '/registrations']]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Are you sure you want to Cancel registration #1?');
  }

  /**
   * Tests canceling a registration with bypass permission.
   */
  public function testCancelWithBypass() {
    // Register.
    $user = $this->drupalCreateUser([
      'access user profiles',
      'create conference registration self',
      'update own conference registration',
      'use registration cancel transition',
      'view own registration',
      'bypass cancel by access',
    ]);
    $this->drupalLogin($user);
    $this->drupalGet('user/' . $this->adminUser->id() . '/register');
    $this->assertSession()->buttonExists('Save Registration');
    $this->submitForm([], 'Save Registration');
    $this->assertSession()->pageTextContains('Registration has been saved.');

    // Cancel by date is not set, so cancel is allowed.
    $this->drupalGet('registration/1/transition/cancel');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Are you sure you want to Cancel registration #1?');

    // Cancel by date is in the past, but the user has bypass permission,
    // so cancel is still allowed.
    $storage = $this->entityTypeManager->getStorage('registration_settings');
    $settings = $storage->load(1);
    $settings->set('cancel_by', '2020-01-01T00:00:00');
    $settings->save();
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Are you sure you want to Cancel registration #1?');

    // Cancel by date is in the future, so cancel is allowed.
    $settings->set('cancel_by', '2220-01-01T00:00:00');
    $settings->save();
    $this->drupalGet('registration/1/transition/cancel', ['query' => ['destination' => '/user/' . $user->id() . '/registrations']]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Are you sure you want to Cancel registration #1?');
  }

}
