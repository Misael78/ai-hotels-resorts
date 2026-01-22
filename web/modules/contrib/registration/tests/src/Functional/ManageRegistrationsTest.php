<?php

namespace Drupal\Tests\registration\Functional;

/**
 * Tests the manage registrations page.
 *
 * @group registration
 */
class ManageRegistrationsTest extends RegistrationBrowserTestBase {

  /**
   * Tests manage registrations.
   */
  public function testManageRegistrations() {
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
    $save_registration = $registration;

    $this->drupalGet('user/' . $user->id() . '/registrations');
    $this->assertSession()->pageTextContains('List of registrations for Test user. 2 spaces are filled.');

    $registration = $registration_storage->create([
      'workflow' => 'registration',
      'state' => 'pending',
      'type' => 'conference',
      'entity_type_id' => 'user',
      'entity_id' => $user->id(),
      'user_uid' => 1,
      'count' => 1,
    ]);
    $registration->save();

    $this->drupalGet('user/' . $user->id() . '/registrations');
    $this->assertSession()->pageTextContains('List of registrations for Test user. 3 spaces are filled.');

    // Canceled registrations do not count towards the total.
    $registration = $registration_storage->create([
      'workflow' => 'registration',
      'state' => 'canceled',
      'type' => 'conference',
      'entity_type_id' => 'user',
      'entity_id' => $user->id(),
      'user_uid' => 1,
      'count' => 1,
    ]);
    $registration->save();

    $this->drupalGet('user/' . $user->id() . '/registrations');
    $this->assertSession()->pageTextContains('List of registrations for Test user. 3 spaces are filled.');

    // Held registrations count towards the total.
    $registration = $registration_storage->create([
      'workflow' => 'registration',
      'state' => 'held',
      'type' => 'conference',
      'entity_type_id' => 'user',
      'entity_id' => $user->id(),
      'user_uid' => 1,
      'count' => 1,
    ]);
    $registration->save();

    $this->drupalGet('user/' . $user->id() . '/registrations');
    $this->assertSession()->pageTextContains('List of registrations for Test user. 4 spaces are filled.');

    // Include capacity when configured.
    $host_entity = $registration->getHostEntity();
    $settings = $host_entity->getSettings();
    $settings->set('capacity', 10);
    $settings->save();
    $this->drupalGet('user/' . $user->id() . '/registrations');
    $this->assertSession()->pageTextContains('List of registrations for Test user. 4 of 10 spaces are filled.');

    // Reflect deletions.
    $registration->delete();
    $save_registration->delete();
    $settings->set('capacity', 0);
    $settings->save();
    $this->drupalGet('user/' . $user->id() . '/registrations');
    $this->assertSession()->pageTextContains('List of registrations for Test user. 1 space is filled.');

    // Host entity is not configured for registration.
    $user->set('field_registration', NULL);
    $user->save();
    $this->drupalGet('user/' . $user->id() . '/registrations');
    $this->assertSession()->statusCodeEquals(403);
  }

}
