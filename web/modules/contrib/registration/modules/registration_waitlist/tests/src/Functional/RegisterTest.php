<?php

namespace Drupal\Tests\registration_waitlist\Functional;

/**
 * Tests register.
 *
 * @group registration
 */
class RegisterTest extends RegistrationWaitListBrowserTestBase {

  /**
   * Tests doing a new registration.
   */
  public function testRegister() {
    $this->drupalLogout();

    $this->adminUser->set('field_registration', 'conference');
    $this->adminUser->save();

    $handler = $this->entityTypeManager->getHandler('user', 'registration_host_entity');
    $host_entity = $handler->createHostEntity($this->adminUser);

    /** @var \Drupal\registration\RegistrationSettingsStorage $storage */
    $storage = $this->entityTypeManager->getStorage('registration_settings');
    $settings = $storage->loadSettingsForHostEntity($host_entity);
    $settings->set('status', TRUE);
    $settings->save();

    // Register self.
    $user = $this->drupalCreateUser([
      'access user profiles',
      'create conference registration self',
    ]);
    $this->drupalLogin($user);
    $this->drupalGet('user/' . $this->adminUser->id() . '/register');
    $this->assertSession()->buttonExists('Save Registration');
    $this->submitForm([], 'Save Registration');
    $this->assertSession()->statusMessageExists('status', 'Registration has been saved.');
    $this->drupalLogout();

    // No more room.
    $settings->set('capacity', 1);
    $settings->save();
    $user = $this->drupalCreateUser([
      'access user profiles',
      'create conference registration self',
    ]);
    $this->drupalLogin($user);
    $this->drupalGet('user/' . $this->adminUser->id() . '/register');
    $this->assertSession()->buttonNotExists('Save Registration');
    $this->assertSession()->pageTextContains('insufficient spaces remaining');

    // Set up the wait list and retry.
    $settings->set('registration_waitlist_enable', TRUE);
    $settings->set('registration_waitlist_message_enable', TRUE);
    $settings->save();
    $this->drupalGet('user/' . $this->adminUser->id() . '/register');
    $this->assertSession()->buttonExists('Save Registration');
    $this->assertSession()->pageTextContains('completing this registration form will place you on a waitlist');
    $this->assertSession()->pageTextNotContains('insufficient spaces remaining');
    $this->submitForm([], 'Save Registration');
    $this->assertSession()->statusMessageExists('warning', 'Registration placed on the wait list.');
    $this->assertSession()->statusMessageNotExists('status', 'Registration has been saved.');
    $this->drupalLogout();
  }

}
