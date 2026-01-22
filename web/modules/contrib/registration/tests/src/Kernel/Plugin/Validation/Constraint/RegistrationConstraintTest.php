<?php

namespace Drupal\Tests\registration\Kernel\Plugin\Validation\Constraint;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Tests\registration\Kernel\RegistrationKernelTestBase;
use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\Tests\registration\Traits\RegistrationCreationTrait;
use Drupal\user\UserInterface;

/**
 * Tests the Registration constraint.
 *
 * @coversDefaultClass \Drupal\registration\Plugin\Validation\Constraint\RegistrationConstraint
 *
 * @group registration
 */
class RegistrationConstraintTest extends RegistrationKernelTestBase {

  use NodeCreationTrait;
  use RegistrationCreationTrait;

  /**
   * The admin user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $adminUser;

  /**
   * The configuration factory.
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->configFactory = $this->container->get('config.factory');

    $admin_user = $this->createUser(['administer registration', 'create registration']);
    $this->setCurrentUser($admin_user);
    $this->adminUser = $admin_user;
  }

  /**
   * @covers ::validate
   */
  public function testRegistrationConstraint() {
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
    $user = $this->createUser();
    $registration->set('user_uid', $user->id());
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
    $user = $this->createUser();
    $registration->set('user_uid', $user->id());
    $registration->set('entity_id', 999);
    $violations = $registration->validate();
    $this->assertEquals('Missing host entity.', (string) $violations[0]->getMessage());
    $this->assertEquals(1, $violations->count());

    // Exceeds maximum spaces.
    $node = $this->createAndSaveNode();
    $registration = $this->createRegistration($node);
    $registration->set('author_uid', 1);
    $user = $this->createUser();
    $registration->set('user_uid', $user->id());
    // Capacity 5 and max 2 spaces per registration are set in the
    // registration_test module.
    $registration->set('count', 5);
    $violations = $registration->validate();
    $this->assertEquals('You may not register for more than 2 spaces.', (string) $violations[0]->getMessage());
    $this->assertEquals(1, $violations->count());

    $host_entity = $registration->getHostEntity();
    $settings = $host_entity->getSettings();
    $settings->set('maximum_spaces', 1);
    $settings->save();
    $violations = $registration->validate();
    $this->assertEquals('You may not register for more than 1 space.', (string) $violations[0]->getMessage());
    $this->assertEquals(1, $violations->count());

    $settings->set('maximum_spaces', 2);
    $settings->save();
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

    $user = $this->createUser(['create registration']);
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
    $this->setCurrentUser($user);
    $violations = $registration->validate();
    $this->assertEquals('<em class="placeholder">' . $this->adminUser->getAccountName() . '</em> is already registered for this event.', (string) $violations[0]->getMessage());
    $this->assertEquals(1, $violations->count());

    // Administrators can always edit existing registrations.
    $this->setCurrentUser($this->adminUser);
    $settings->set('status', FALSE);
    $settings->save();
    $registration = $this->createRegistration($node);
    $user = $this->createUser();
    $registration->set('user_uid', $user->id());
    $registration->set('author_uid', 1);
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
    ]);
    $this->setCurrentUser($account);
    // Access to edit registrations for disabled hosts is prevented by default
    // for non-administrative users.
    $violations = $registration->validate();
    $this->assertEquals(1, $violations->count());
    $this->assertEquals('Registration for <em class="placeholder">My event</em> is disabled.', (string) $violations[0]->getMessage());
    // Access to edit registrations for disabled hosts is allowed for
    // non-administrative users if the relevant configuration is set.
    $global_settings = $this->configFactory->getEditable('registration.settings');
    $global_settings->set('prevent_edit_disabled', FALSE);
    $global_settings->save();
    $violations = $registration->validate();
    $this->assertEquals(0, $violations->count());
    // However a non-administrative user cannot increase spaces or change status
    // or the registrant while the host is disabled or closed.
    $registration->set('count', 2);
    $violations = $registration->validate();
    $this->assertEquals('The number of spaces cannot be increased because registration for <em class="placeholder">My event</em> is disabled.', (string) $violations[0]->getMessage());
    $this->assertEquals(1, $violations->count());
    $registration->set('count', 1);
    $registration->set('state', 'complete');
    $violations = $registration->validate();
    $this->assertEquals('The status cannot be changed because registration for <em class="placeholder">My event</em> is disabled.', (string) $violations[0]->getMessage());
    $this->assertEquals(1, $violations->count());
    $settings->set('status', TRUE);
    $settings->set('close', '2020-01-01T00:00:00');
    $settings->save();
    $user = $this->createUser();
    $registration->set('user_uid', $user->id());
    $violations = $registration->validate();
    $this->assertEquals('The status cannot be changed because registration for <em class="placeholder">My event</em> is disabled.', (string) $violations[0]->getMessage());
    $this->assertEquals('The registrant cannot be changed because registration for <em class="placeholder">My event</em> is disabled.', (string) $violations[1]->getMessage());
    $this->assertEquals(2, $violations->count());
    $registration = $this->reloadEntity($registration);
    $registration->set('user_uid', NULL);
    $registration->set('anon_mail', 'test@example.org');
    $registration->save();
    $violations = $registration->validate();
    $this->assertEquals(0, $violations->count());
    $registration->set('state', 'complete');
    $violations = $registration->validate();
    $this->assertEquals('The status cannot be changed because registration for <em class="placeholder">My event</em> is closed.', (string) $violations[0]->getMessage());
    $this->assertEquals(1, $violations->count());
    $registration->set('count', 2);
    $registration->set('state', 'pending');
    $violations = $registration->validate();
    $this->assertEquals('The number of spaces cannot be increased because registration for <em class="placeholder">My event</em> is closed.', (string) $violations[0]->getMessage());
    $this->assertEquals(1, $violations->count());
    $registration->set('count', 1);
    $registration->set('anon_mail', 'email@example.org');
    $violations = $registration->validate();
    $this->assertEquals('The registrant cannot be changed because registration for <em class="placeholder">My event</em> is closed.', (string) $violations[0]->getMessage());
    $this->assertEquals(1, $violations->count());
    // Administrators can change anything about the registration.
    $this->setCurrentUser($this->adminUser);
    $registration->set('state', 'complete');
    $registration->set('count', 2);
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
    $user = $this->createUser();
    $registration->set('user_uid', $user->id());
    $registration->save();
    $this->setCurrentUser($this->adminUser);
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
   * Tests whether various types of registrants are allowed.
   *
   * @covers ::validate
   */
  public function testAllowedRegistrants() {
    $node = $this->createAndSaveNode();
    $node->save();

    $ordinary_user = $this->createUser();
    $registrant_user = $this->createUser(['create conference registration self']);

    // Without permissions, no registrant is valid for a new registration.
    $this->setCurrentUser($ordinary_user);
    $registration = $this->createRegistration($node);
    $registration->set('author_uid', 1);
    $registration->set('user_uid', $ordinary_user->id());
    $violations = $registration->validate();
    $this->assertCount(1, $violations);
    $this->assertSame('You are not allowed to register yourself.', (string) $violations[0]->getMessage());
    $this->assertNotEmpty($ordinary_user->getEmail());
    $registration->set('anon_mail', $ordinary_user->getEmail());
    $violations = $registration->validate();
    $this->assertCount(1, $violations);
    $this->assertSame('You are not allowed to register yourself.', (string) $violations[0]->getMessage());
    $registration->set('user_uid', NULL);
    $violations = $registration->validate();
    $this->assertCount(1, $violations);
    $this->assertSame('You are not allowed to register other people.', (string) $violations[0]->getMessage());
    $registration->set('user_uid', $registrant_user->id());
    $violations = $registration->validate();
    $this->assertCount(1, $violations);
    $this->assertSame('You are not allowed to register other users.', (string) $violations[0]->getMessage());
    $this->assertNotEmpty($registrant_user->getEmail());
    $registration->set('anon_mail', $registrant_user->getEmail());
    $violations = $registration->validate();
    $this->assertCount(1, $violations);
    $this->assertSame('You are not allowed to register other users.', (string) $violations[0]->getMessage());
    $registration->set('user_uid', NULL);
    $violations = $registration->validate();
    $this->assertCount(1, $violations);
    $this->assertSame('You are not allowed to register other people.', (string) $violations[0]->getMessage());

    // But the registrant is not validated for an existing registration.
    $registration->save();
    $violations = $registration->validate();
    $this->assertCount(0, $violations);
    // Unless the registrant type is changed.
    $registration->set('user_uid', $ordinary_user->id());
    $violations = $registration->validate();
    $this->assertCount(1, $violations);
    $this->assertSame('You are not allowed to register yourself.', (string) $violations[0]->getMessage());

    // With permissions, the appropriate registrant is valid.
    $this->setCurrentUser($registrant_user);
    $registration->delete();
    $registration = $this->createRegistration($node);
    $registration->set('author_uid', 1);
    $registration->set('user_uid', $ordinary_user->id());
    $violations = $registration->validate();
    $this->assertCount(1, $violations);
    $this->assertSame('You are not allowed to register other users.', (string) $violations[0]->getMessage());
    $registration->set('user_uid', $registrant_user->id());
    $violations = $registration->validate();
    $this->assertCount(0, $violations);

    // For existing registrations also.
    $registration->save();
    $violations = $registration->validate();
    $this->assertCount(0, $violations);
    $registration->set('user_uid', $ordinary_user->id());
    $violations = $registration->validate();
    $this->assertCount(1, $violations);
    $this->assertSame('You are not allowed to register other users.', (string) $violations[0]->getMessage());
    $registration->save();
    $violations = $registration->validate();
    $this->assertCount(0, $violations);
  }

}
