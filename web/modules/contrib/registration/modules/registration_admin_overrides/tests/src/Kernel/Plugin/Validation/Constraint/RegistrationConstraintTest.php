<?php

namespace Drupal\Tests\registration_admin_overrides\Kernel\Plugin\Validation\Constraint;

use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\Tests\registration\Traits\RegistrationCreationTrait;
use Drupal\Tests\registration_admin_overrides\Kernel\RegistrationAdminOverridesKernelTestBase;

/**
 * Tests the Registration constraint.
 *
 * Performs a regression test confirming that inactive overrides have no impact
 * on constraint checking. Then adds a feature test confirming that when
 * overrides are active, the constraints are indeed overridden.
 *
 * @coversDefaultClass \Drupal\registration\Plugin\Validation\Constraint\RegistrationConstraint
 *
 * @group registration
 */
class RegistrationConstraintTest extends RegistrationAdminOverridesKernelTestBase {

  use NodeCreationTrait;
  use RegistrationCreationTrait;

  /**
   * @covers ::validate
   */
  public function testRegistrationConstraintRegression() {
    // Run a regression test with the same assertions as this test in
    // registration core. Overrides are inactive since the current
    // user can administer registrations but does not have the override
    // permissions needed to override constraints.
    $account = $this->createUser([
      'administer registration',
      'create registration',
    ]);
    $this->setCurrentUser($account);

    // Host entity not configured for registration.
    $node = $this->createNode();
    $node->set('event_registration', NULL);
    $node->save();
    $registration = $this->createRegistration($node);
    $registration->set('author_uid', 1);
    $violations = $registration->validate();
    $this->assertEquals('Registration for <em class="placeholder">My event</em> is disabled.', (string) $violations[0]->getMessage());
    $this->assertEquals(1, $violations->count());

    // Host entity is disabled for registration through settings.
    $node = $this->createAndSaveNode();
    $registration = $this->createRegistration($node);
    $registration->set('author_uid', 1);
    $registration->set('user_uid', 1);
    $host_entity = $registration->getHostEntity();
    $settings = $host_entity->getSettings();
    $settings->set('status', FALSE);
    $settings->save();
    $violations = $registration->validate();
    $this->assertEquals('Registration for <em class="placeholder">My event</em> is disabled.', (string) $violations[0]->getMessage());
    $this->assertEquals(1, $violations->count());

    $settings->set('status', TRUE);
    $settings->save();
    $violations = $registration->validate();
    $this->assertEquals(0, $violations->count());

    // Missing host entity.
    $node = $this->createAndSaveNode();
    $registration = $this->createRegistration($node);
    $registration->set('author_uid', 1);
    $registration->set('user_uid', 1);
    $registration->set('entity_id', 999);
    $violations = $registration->validate();
    $this->assertEquals('Missing host entity.', (string) $violations[0]->getMessage());
    $this->assertEquals(1, $violations->count());

    // Exceeds maximum spaces.
    $node = $this->createAndSaveNode();
    $registration = $this->createRegistration($node);
    $registration->set('author_uid', 1);
    $registration->set('user_uid', 1);
    // Capacity 5 and max 2 spaces per registration are set in the
    // registration_test module.
    $registration->set('count', 5);
    $violations = $registration->validate();
    $this->assertEquals('You may not register for more than 2 spaces.', (string) $violations[0]->getMessage());
    $this->assertEquals(1, $violations->count());

    $registration->set('count', 2);
    $violations = $registration->validate();
    $this->assertEquals(0, $violations->count());

    // Add registrations for subsequent assertions.
    $registration->set('count', 2);
    $registration->save();
    $registration = $this->createRegistration($node);
    $registration->set('anon_mail', 'test@example.com');
    $registration->save();
    $registration = $this->createAndSaveRegistration($node);

    // No room.
    $registration = $this->createRegistration($node);
    $registration->set('author_uid', 1);
    $user = $this->createUser();
    $registration->set('user_uid', $user->id());
    $registration->set('count', 2);
    $violations = $registration->validate();
    $this->assertEquals('Sorry, unable to register for <em class="placeholder">My event</em> due to: insufficient spaces remaining.', (string) $violations[0]->getMessage());
    $this->assertEquals(1, $violations->count());

    $registration->set('count', 1);
    $violations = $registration->validate();
    $this->assertEquals(0, $violations->count());

    // Before open.
    $host_entity = $registration->getHostEntity();
    $settings = $host_entity->getSettings();
    $settings->set('open', '2220-01-01T00:00:00');
    $settings->save();
    $registration->set('count', 1);
    $violations = $registration->validate();
    $this->assertEquals('Registration for <em class="placeholder">My event</em> is not open yet.', (string) $violations[0]->getMessage());
    $this->assertEquals(1, $violations->count());

    $settings->set('open', '2020-01-01T00:00:00');
    $settings->save();
    $violations = $registration->validate();
    $this->assertEquals(0, $violations->count());

    // After close.
    $settings->set('open', NULL);
    $settings->set('close', '2020-01-01T00:00:00');
    $settings->save();
    $violations = $registration->validate();
    $this->assertEquals('Registration for <em class="placeholder">My event</em> is closed.', (string) $violations[0]->getMessage());
    $this->assertEquals(1, $violations->count());

    $settings->set('close', '2220-01-01T00:00:00');
    $settings->save();
    $violations = $registration->validate();
    $this->assertEquals(0, $violations->count());

    // Remove open and close dates.
    $settings->set('open', NULL);
    $settings->set('close', NULL);
    $settings->save();

    // Email already registered.
    $registration->set('anon_mail', 'test@example.com');
    $violations = $registration->validate();
    $this->assertEquals('<em class="placeholder">test@example.com</em> is already registered for this event.', (string) $violations[0]->getMessage());
    $this->assertEquals(1, $violations->count());

    $registration->set('anon_mail', 'test2@example.com');
    $violations = $registration->validate();
    $this->assertEquals(0, $violations->count());

    // User already registered.
    $node = $this->createAndSaveNode();
    $registration = $this->createRegistration($node);
    $registration->set('user_uid', 1);
    $registration->save();
    $registration = $this->createRegistration($node);
    $registration->set('author_uid', 1);
    $registration->set('user_uid', 1);
    $violations = $registration->validate();
    $this->assertEquals('You are already registered for this event.', (string) $violations[0]->getMessage());
    $this->assertEquals(1, $violations->count());

    $user = $this->createUser();
    $registration->set('user_uid', $user->id());
    $violations = $registration->validate();
    $this->assertEquals(0, $violations->count());

    // If multiple registrations are allowed per user or email address, then
    // no violations should be triggered for duplicates.
    $host_entity = $registration->getHostEntity();
    $settings = $host_entity->getSettings();
    $settings->set('multiple_registrations', TRUE);
    $settings->save();
    $registration->set('user_uid', 1);
    $violations = $registration->validate();
    $this->assertEquals(0, $violations->count());
    $registration->set('user_uid', NULL);
    $registration->set('anon_mail', 'test@example.com');
    $violations = $registration->validate();
    $this->assertEquals(0, $violations->count());

    // Turn it back off and confirm errors are triggered.
    $settings->set('multiple_registrations', FALSE);
    $settings->save();
    $registration->set('user_uid', 1);
    $registration->set('anon_mail', NULL);
    $violations = $registration->validate();
    $this->assertEquals('You are already registered for this event.', (string) $violations[0]->getMessage());
    $this->assertEquals(1, $violations->count());

    // Administrators can always edit existing registrations.
    $settings->set('status', FALSE);
    $settings->save();
    $registration = $this->createRegistration($node);
    $registration->set('author_uid', 1);
    $user = $this->createUser();
    $registration->set('user_uid', $user->id());
    $violations = $registration->validate();
    $this->assertEquals(1, $violations->count());
    $registration->save();
    $this->assertFalse($registration->isNew());
    $violations = $registration->validate();
    $this->assertEquals(0, $violations->count());

    // Non-administrative user.
    $account = $this->createUser([
      'view any conference registration',
      'update any conference registration',
      'create registration',
    ]);
    $this->setCurrentUser($account);
    $violations = $registration->validate();
    $this->assertEquals(1, $violations->count());
    $this->assertEquals('Registration for <em class="placeholder">My event</em> is disabled.', (string) $violations[0]->getMessage());

    // Capacity should only be checked for existing registrations if spaces
    // reserved or registration state have changed.
    $node = $this->createAndSaveNode();
    $registration = $this->createAndSaveRegistration($node);
    $host_entity = $registration->getHostEntity();
    $settings = $host_entity->getSettings();
    $settings->set('capacity', 1);
    $settings->save();
    // Exceed capacity. This is not allowed through the registration UI but can
    // be done through the entity API since validation is separate.
    $registration = $this->createRegistration($node);
    $registration->set('author_uid', 1);
    $registration->set('user_uid', 1);
    $registration->save();
    // Nothing has changed, so editing should be allowed.
    $violations = $registration->validate();
    $this->assertEquals(0, $violations->count());

    // Try to increase the spaces reserved while capacity is exceeded.
    $registration->set('count', 2);
    $violations = $registration->validate();
    $this->assertEquals(1, $violations->count());
    $this->assertEquals('Sorry, unable to register for <em class="placeholder">My event</em> due to: insufficient spaces remaining.', (string) $violations[0]->getMessage());

    // Try to complete a pending registration while capacity is exceeded.
    $registration->set('count', 1);
    $registration->set('state', 'complete');
    $violations = $registration->validate();
    $this->assertEquals(1, $violations->count());
    $this->assertEquals('Sorry, unable to register for <em class="placeholder">My event</em> due to: insufficient spaces remaining.', (string) $violations[0]->getMessage());

    // Canceling a registration is always allowed.
    $registration->set('count', 2);
    $registration->set('state', 'canceled');
    $violations = $registration->validate();
    $this->assertEquals(0, $violations->count());
  }

  /**
   * @covers ::validate
   */
  public function testRegistrationConstraintWithOverrides() {
    $admin_user = $this->createUser([
      'administer registration',
      'create registration',
    ]);
    $overriding_user = $this->createUser([
      'administer registration',
      'create registration',
      'registration override status',
      'registration override maximum spaces',
      'registration override capacity',
      'registration override open',
      'registration override close',
    ]);
    $regular_user = $this->createUser([
      'create registration',
      'view any conference registration',
      'update any conference registration',
    ]);

    // Exceeds maximum spaces.
    $node = $this->createAndSaveNode();
    $registration = $this->createRegistration($node);
    $registration->set('author_uid', 1);
    $registration->set('user_uid', 1);
    // Capacity 5 and max 2 spaces per registration are set in the
    // registration_test module.
    $registration->set('count', 4);
    $this->setCurrentUser($admin_user);
    $violations = $registration->validate();
    $this->assertEquals('You may not register for more than 2 spaces.', (string) $violations[0]->getMessage());
    $this->assertEquals(1, $violations->count());
    $this->setCurrentUser($overriding_user);
    $violations = $registration->validate();
    $this->assertEquals(0, $violations->count());

    // A regular user can save an existing registration, even if it exceeds
    // maximum spaces, as long as the number of spaces has not changed.
    $this->setCurrentUser($regular_user);
    $violations = $registration->validate();
    // The registration is new, so the maximum spaces cannot be exceeded.
    $this->assertEquals('You may not register for more than 2 spaces.', (string) $violations[0]->getMessage());
    $this->assertEquals(1, $violations->count());
    $registration->save();
    // The registration is existing, but the maximum spaces can be exceeded
    // since the spaces field was not changed.
    $violations = $registration->validate();
    $this->assertEquals(0, $violations->count());
    // The registration is existing, and spaces is increasing from 4 to 5,
    // so the maximum spaces cannot be exceeded by a regular user.
    $registration->set('count', 5);
    $violations = $registration->validate();
    $this->assertEquals('You may not register for more than 2 spaces.', (string) $violations[0]->getMessage());
    $this->assertEquals(1, $violations->count());

    // Add registrations for subsequent assertions.
    $registration->set('count', 2);
    $registration->save();
    $registration = $this->createRegistration($node);
    $registration->set('anon_mail', 'test@example.com');
    $registration->save();
    $registration = $this->createAndSaveRegistration($node);

    // No room.
    $registration = $this->createRegistration($node);
    $registration->set('author_uid', 1);
    $user = $this->createUser();
    $registration->set('user_uid', $user->id());
    $registration->set('count', 2);
    $this->setCurrentUser($admin_user);
    $violations = $registration->validate();
    $this->assertEquals('Sorry, unable to register for <em class="placeholder">My event</em> due to: insufficient spaces remaining.', (string) $violations[0]->getMessage());
    $this->assertEquals(1, $violations->count());
    $this->setCurrentUser($overriding_user);
    $violations = $registration->validate();
    $this->assertEquals(0, $violations->count());

    // Before open.
    $host_entity = $registration->getHostEntity();
    $settings = $host_entity->getSettings();
    $settings->set('open', '2220-01-01T00:00:00');
    $settings->save();
    $registration->set('count', 1);
    $this->setCurrentUser($admin_user);
    $violations = $registration->validate();
    $this->assertEquals('Registration for <em class="placeholder">My event</em> is not open yet.', (string) $violations[0]->getMessage());
    $this->assertEquals(1, $violations->count());
    $this->setCurrentUser($overriding_user);
    $violations = $registration->validate();
    $this->assertEquals(0, $violations->count());

    // After close.
    $settings->set('open', NULL);
    $settings->set('close', '2020-01-01T00:00:00');
    $settings->save();
    $this->setCurrentUser($admin_user);
    $violations = $registration->validate();
    $this->assertEquals('Registration for <em class="placeholder">My event</em> is closed.', (string) $violations[0]->getMessage());
    $this->assertEquals(1, $violations->count());
    $this->setCurrentUser($overriding_user);
    $violations = $registration->validate();
    $this->assertEquals(0, $violations->count());

    // Capacity should only be checked for existing registrations if spaces
    // reserved or registration state have changed.
    $node = $this->createAndSaveNode();
    $registration = $this->createAndSaveRegistration($node);
    $host_entity = $registration->getHostEntity();
    $settings = $host_entity->getSettings();
    $settings->set('capacity', 1);
    $settings->save();
    // Exceed capacity. This is not allowed through the registration UI but can
    // be done through the entity API since validation is separate.
    $registration = $this->createRegistration($node);
    $registration->set('author_uid', 1);
    $registration->set('user_uid', 1);
    $registration->save();
    // Nothing has changed, so editing should be allowed.
    $this->setCurrentUser($admin_user);
    $violations = $registration->validate();
    $this->assertEquals(0, $violations->count());
    $this->setCurrentUser($overriding_user);
    $violations = $registration->validate();
    $this->assertEquals(0, $violations->count());

    // Try to increase the spaces reserved while capacity is exceeded.
    $registration->set('count', 2);
    $this->setCurrentUser($admin_user);
    $violations = $registration->validate();
    $this->assertEquals('Sorry, unable to register for <em class="placeholder">My event</em> due to: insufficient spaces remaining.', (string) $violations[0]->getMessage());
    $this->assertEquals(1, $violations->count());
    $this->setCurrentUser($overriding_user);
    $violations = $registration->validate();
    $this->assertEquals(0, $violations->count());

    // Try to complete a pending registration while capacity is exceeded.
    $registration->set('count', 1);
    $registration->set('state', 'complete');
    $this->setCurrentUser($admin_user);
    $violations = $registration->validate();
    $this->assertEquals('Sorry, unable to register for <em class="placeholder">My event</em> due to: insufficient spaces remaining.', (string) $violations[0]->getMessage());
    $this->assertEquals(1, $violations->count());
    $this->setCurrentUser($overriding_user);
    $violations = $registration->validate();
    $this->assertEquals(0, $violations->count());

    // Canceling a registration is always allowed.
    $registration->set('count', 2);
    $registration->set('state', 'canceled');
    $this->setCurrentUser($admin_user);
    $violations = $registration->validate();
    $this->assertEquals(0, $violations->count());
    $this->setCurrentUser($overriding_user);
    $violations = $registration->validate();
    $this->assertEquals(0, $violations->count());
  }

}
