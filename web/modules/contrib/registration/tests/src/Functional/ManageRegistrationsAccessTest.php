<?php

namespace Drupal\Tests\registration\Functional;

/**
 * Tests access to the manage registrations page.
 *
 * @group registration
 */
class ManageRegistrationsAccessTest extends RegistrationBrowserTestBase {

  /**
   * Tests access to manage registrations.
   */
  public function testManageRegistrationsAccess() {
    $user = $this->drupalCreateUser();
    $user->set('field_registration', 'conference');
    $user->set('name', 'Test user');
    $user->save();

    // No registrants.
    $this->drupalGet('user/' . $user->id() . '/registrations');
    $this->assertSession()->pageTextContains('There are no registrants for Test user');

    /** @var \Drupal\registration\RegistrationStorage $registration_storage */
    $registration_storage = $this->entityTypeManager->getStorage('registration');
    $registration = $registration_storage->create([
      'workflow' => 'registration',
      'state' => 'pending',
      'type' => 'conference',
      'entity_type_id' => 'user',
      'entity_id' => $user->id(),
      'user_uid' => 1,
      'count' => 2,
    ]);
    $registration->save();

    // Administer permission.
    $test_user = $this->drupalCreateUser([
      'administer registration',
      'access user profiles',
    ]);
    $this->drupalLogin($test_user);
    $this->drupalGet('user/' . $user->id() . '/registrations');
    $this->assertSession()->pageTextContains('List of registrations for');
    $this->assertSession()->pageTextNotContains('Registration summary for');

    // Administer type permission.
    $test_user = $this->drupalCreateUser([
      'administer conference registration',
      'access user profiles',
    ]);
    $this->drupalLogin($test_user);
    $this->drupalGet('user/' . $user->id() . '/registrations');
    $this->assertSession()->pageTextContains('List of registrations for');
    $this->assertSession()->pageTextNotContains('Registration summary for');

    // Administer type settings permission only provides summary access.
    $test_user = $this->drupalCreateUser([
      'administer conference registration settings',
      'access user profiles',
    ]);
    $this->drupalLogin($test_user);
    $this->drupalGet('user/' . $user->id() . '/registrations');
    $this->assertSession()->pageTextContains('Registration summary for');
    $this->assertSession()->pageTextNotContains('List of registrations for');

    // Adding view "any" permission provides access to the listing.
    $test_user = $this->drupalCreateUser([
      'administer conference registration settings',
      'access user profiles',
      'view any registration',
    ]);
    $this->drupalLogin($test_user);
    $this->drupalGet('user/' . $user->id() . '/registrations');
    $this->assertSession()->pageTextContains('List of registrations for');
    $this->assertSession()->pageTextNotContains('Registration summary for');

    // Administer "own" type permission never gives access.
    $test_user = $this->drupalCreateUser([
      'administer own conference registration',
      'administer users',
      'view any registration',
    ]);
    $this->drupalLogin($test_user);
    $this->drupalGet('user/' . $user->id() . '/registrations');
    $this->assertSession()->statusCodeEquals(403);

    // Administer "own" type settings permission only provides summary access.
    $test_user = $this->drupalCreateUser([
      'administer own conference registration settings',
      'administer users',
    ]);
    $this->drupalLogin($test_user);
    $this->drupalGet('user/' . $user->id() . '/registrations');
    $this->assertSession()->pageTextContains('Registration summary for');
    $this->assertSession()->pageTextNotContains('List of registrations for');

    // Adding view "any" permission provides access to the listing.
    $test_user = $this->drupalCreateUser([
      'administer own conference registration settings',
      'administer users',
      'view any registration',
    ]);
    $this->drupalLogin($test_user);
    $this->drupalGet('user/' . $user->id() . '/registrations');
    $this->assertSession()->pageTextContains('List of registrations for');
    $this->assertSession()->pageTextNotContains('Registration summary for');

    $test_user = $this->drupalCreateUser([
      'administer own conference registration settings',
      'access user profiles',
    ]);
    $this->drupalLogin($test_user);
    $this->drupalGet('user/' . $user->id() . '/registrations');
    $this->assertSession()->statusCodeEquals(403);

    // Manage type permission only provides summary access.
    $test_user = $this->drupalCreateUser([
      'manage conference registration',
      'access user profiles',
    ]);
    $this->drupalLogin($test_user);
    $this->drupalGet('user/' . $user->id() . '/registrations');
    $this->assertSession()->pageTextContains('Registration summary for');
    $this->assertSession()->pageTextNotContains('List of registrations for');

    // Adding view "any" permission provides access to the listing.
    $test_user = $this->drupalCreateUser([
      'manage conference registration',
      'view any registration',
    ]);
    $this->drupalLogin($test_user);
    $this->drupalGet('user/' . $user->id() . '/registrations');
    $this->assertSession()->pageTextContains('List of registrations for');
    $this->assertSession()->pageTextNotContains('Registration summary for');

    // Adding view "host" permission does not provide access to the listing
    // when the host entity is not editable.
    $test_user = $this->drupalCreateUser([
      'manage conference registration',
      'view host registration',
    ]);
    $this->drupalLogin($test_user);
    $this->drupalGet('user/' . $user->id() . '/registrations');
    $this->assertSession()->pageTextContains('Registration summary for');
    $this->assertSession()->pageTextNotContains('List of registrations for');

    // Adding view "host" permission provides access to the listing when the
    // host entity is editable.
    $test_user = $this->drupalCreateUser([
      'manage conference registration',
      'administer users',
      'view host registration',
    ]);
    $this->drupalLogin($test_user);
    $this->drupalGet('user/' . $user->id() . '/registrations');
    $this->assertSession()->pageTextContains('List of registrations for');
    $this->assertSession()->pageTextNotContains('Registration summary for');

    // Manage "own" type permission only provides summary access.
    $test_user = $this->drupalCreateUser([
      'manage own conference registration',
      'administer users',
    ]);
    $this->drupalLogin($test_user);
    $this->drupalGet('user/' . $user->id() . '/registrations');
    $this->assertSession()->pageTextContains('Registration summary for');
    $this->assertSession()->pageTextNotContains('List of registrations for');

    // Adding view "any" permission provides access to the listing.
    $test_user = $this->drupalCreateUser([
      'manage own conference registration',
      'administer users',
      'view any registration',
    ]);
    $this->drupalLogin($test_user);
    $this->drupalGet('user/' . $user->id() . '/registrations');
    $this->assertSession()->pageTextContains('List of registrations for');
    $this->assertSession()->pageTextNotContains('Registration summary for');

    // Adding view "host" permission provides access to the listing.
    $test_user = $this->drupalCreateUser([
      'manage own conference registration',
      'administer users',
      'view host registration',
    ]);
    $this->drupalLogin($test_user);
    $this->drupalGet('user/' . $user->id() . '/registrations');
    $this->assertSession()->pageTextContains('List of registrations for');
    $this->assertSession()->pageTextNotContains('Registration summary for');

    // Manage "own" type permission requires edit access to the host entity.
    $test_user = $this->drupalCreateUser([
      'manage own conference registration',
      'access user profiles',
    ]);
    $this->drupalLogin($test_user);
    $this->drupalGet('user/' . $user->id() . '/registrations');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests access to manage registration settings.
   */
  public function testManageRegistrationSettingsAccess() {
    $user = $this->drupalCreateUser();
    $user->set('field_registration', 'conference');
    $user->set('name', 'Test user');
    $user->save();

    // Administer permission.
    $test_user = $this->drupalCreateUser([
      'administer registration',
    ]);
    $this->drupalLogin($test_user);
    $this->drupalGet('user/' . $user->id() . '/registrations/settings');
    $this->assertSession()->pageTextContains('Registration settings');

    // Administer type permission.
    $test_user = $this->drupalCreateUser([
      'administer conference registration',
    ]);
    $this->drupalLogin($test_user);
    $this->drupalGet('user/' . $user->id() . '/registrations/settings');
    $this->assertSession()->pageTextContains('Registration settings');

    // Administer type settings permission.
    $test_user = $this->drupalCreateUser([
      'administer conference registration settings',
    ]);
    $this->drupalLogin($test_user);
    $this->drupalGet('user/' . $user->id() . '/registrations/settings');
    $this->assertSession()->pageTextContains('Registration settings');

    // Administer "own" type permission never gives access.
    $test_user = $this->drupalCreateUser([
      'administer own conference registration',
      'administer users',
    ]);
    $this->drupalLogin($test_user);
    $this->drupalGet('user/' . $user->id() . '/registrations/settings');
    $this->assertSession()->statusCodeEquals(403);

    // Administer "own" type settings permission.
    $test_user = $this->drupalCreateUser([
      'administer own conference registration settings',
      'administer users',
    ]);
    $this->drupalLogin($test_user);
    $this->drupalGet('user/' . $user->id() . '/registrations/settings');
    $this->assertSession()->pageTextContains('Registration settings');

    // Administer "own" type permission requires edit access to the host entity.
    $test_user = $this->drupalCreateUser([
      'administer own conference registration settings',
      'access user profiles',
    ]);
    $this->drupalLogin($test_user);
    $this->drupalGet('user/' . $user->id() . '/registrations/settings');
    $this->assertSession()->statusCodeEquals(403);

    // Manage type permission does not provide access to settings on its own.
    $test_user = $this->drupalCreateUser([
      'manage conference registration',
    ]);
    $this->drupalLogin($test_user);
    $this->drupalGet('user/' . $user->id() . '/registrations/settings');
    $this->assertSession()->statusCodeEquals(403);

    // Manage type permission with settings permission.
    $test_user = $this->drupalCreateUser([
      'manage conference registration',
      'manage conference registration settings',
    ]);
    $this->drupalLogin($test_user);
    $this->drupalGet('user/' . $user->id() . '/registrations/settings');
    $this->assertSession()->pageTextContains('Registration settings');

    // Manage "own" type permission with settings permission.
    $test_user = $this->drupalCreateUser([
      'manage own conference registration',
      'manage conference registration settings',
      'administer users',
    ]);
    $this->drupalLogin($test_user);
    $this->drupalGet('user/' . $user->id() . '/registrations/settings');
    $this->assertSession()->pageTextContains('Registration settings');

    // Manage "own" type permission requires edit access to the host entity.
    $test_user = $this->drupalCreateUser([
      'manage own conference registration',
      'manage conference registration settings',
      'access user profiles',
    ]);
    $this->drupalLogin($test_user);
    $this->drupalGet('user/' . $user->id() . '/registrations/settings');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests access to send registrant broadcast email.
   */
  public function testManageRegistrationBroadcastAccess() {
    $user = $this->drupalCreateUser();
    $user->set('field_registration', 'conference');
    $user->set('name', 'Test user');
    $user->save();

    // Administer permission.
    $test_user = $this->drupalCreateUser([
      'administer registration',
    ]);
    $this->drupalLogin($test_user);
    $this->drupalGet('user/' . $user->id() . '/registrations/broadcast');
    $this->assertSession()->pageTextContains('Email registrants');

    // Administer type permission.
    $test_user = $this->drupalCreateUser([
      'administer conference registration',
    ]);
    $this->drupalLogin($test_user);
    $this->drupalGet('user/' . $user->id() . '/registrations/broadcast');
    $this->assertSession()->pageTextContains('Email registrants');

    // Administer type settings permission.
    $test_user = $this->drupalCreateUser([
      'administer conference registration settings',
    ]);
    $this->drupalLogin($test_user);
    $this->drupalGet('user/' . $user->id() . '/registrations/broadcast');
    $this->assertSession()->pageTextContains('Email registrants');

    // Administer "own" type permission never gives access.
    $test_user = $this->drupalCreateUser([
      'administer own conference registration',
      'administer users',
    ]);
    $this->drupalLogin($test_user);
    $this->drupalGet('user/' . $user->id() . '/registrations/broadcast');
    $this->assertSession()->statusCodeEquals(403);

    // Administer "own" type settings permission.
    $test_user = $this->drupalCreateUser([
      'administer own conference registration settings',
      'administer users',
    ]);
    $this->drupalLogin($test_user);
    $this->drupalGet('user/' . $user->id() . '/registrations/broadcast');
    $this->assertSession()->pageTextContains('Email registrants');

    // Administer "own" type settings permission requires edit access to the
    // host entity.
    $test_user = $this->drupalCreateUser([
      'administer own conference registration settings',
      'access user profiles',
    ]);
    $this->drupalLogin($test_user);
    $this->drupalGet('user/' . $user->id() . '/registrations/broadcast');
    $this->assertSession()->statusCodeEquals(403);

    // Manage type permission does not provide access to broadcast on its own.
    $test_user = $this->drupalCreateUser([
      'manage conference registration',
    ]);
    $this->drupalLogin($test_user);
    $this->drupalGet('user/' . $user->id() . '/registrations/broadcast');
    $this->assertSession()->statusCodeEquals(403);

    // Manage type permission with settings permission.
    $test_user = $this->drupalCreateUser([
      'manage conference registration',
      'manage conference registration settings',
    ]);
    $this->drupalLogin($test_user);
    $this->drupalGet('user/' . $user->id() . '/registrations/broadcast');
    $this->assertSession()->statusCodeEquals(403);

    // Manage type permission with broadcast permission.
    $test_user = $this->drupalCreateUser([
      'manage conference registration',
      'manage conference registration broadcast',
    ]);
    $this->drupalLogin($test_user);
    $this->drupalGet('user/' . $user->id() . '/registrations/broadcast');
    $this->assertSession()->pageTextContains('Email registrants');

    // Manage "own" type permission with broadcast permission.
    $test_user = $this->drupalCreateUser([
      'manage own conference registration',
      'manage conference registration broadcast',
      'administer users',
    ]);
    $this->drupalLogin($test_user);
    $this->drupalGet('user/' . $user->id() . '/registrations/broadcast');
    $this->assertSession()->pageTextContains('Email registrants');

    // Manage "own" type permission requires edit access to the host entity.
    $test_user = $this->drupalCreateUser([
      'manage own conference registration',
      'manage conference registration broadcast',
      'access user profiles',
    ]);
    $this->drupalLogin($test_user);
    $this->drupalGet('user/' . $user->id() . '/registrations/broadcast');
    $this->assertSession()->statusCodeEquals(403);
  }

}
