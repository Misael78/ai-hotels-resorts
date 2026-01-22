<?php

namespace Drupal\Tests\registration_waitlist\Kernel\Plugin\Validation\Constraint;

use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\Tests\registration\Traits\RegistrationCreationTrait;
use Drupal\Tests\registration_waitlist\Kernel\RegistrationWaitListKernelTestBase;

/**
 * Tests the Registration constraint.
 *
 * Performs a regression test confirming that when the wait list is disabled it
 * has no impact on capacity checking. Then adds a feature test confirming that
 * when the wait list is enabled, the capacity check is overridden.
 *
 * @coversDefaultClass \Drupal\registration\Plugin\Validation\Constraint\RegistrationConstraint
 *
 * @group registration
 */
class RegistrationConstraintTest extends RegistrationWaitListKernelTestBase {

  use NodeCreationTrait;
  use RegistrationCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Create user 1.
    $user = $this->createUser();
  }

  /**
   * @covers ::validate
   */
  public function testRegistrationCapacityConstraint() {
    $node = $this->createAndSaveNode();
    $user = $this->createUser([
      'create registration',
    ]);
    $this->setCurrentUser($user);

    $registration = $this->createRegistration($node);
    $registration->set('author_uid', 1);
    $user = $this->createUser();
    $registration->set('user_uid', $user->id());
    /** @var \Drupal\registration_waitlist\HostEntityInterface $host_entity */
    $host_entity = $registration->getHostEntity();
    $settings = $host_entity->getSettings();
    $settings->set('registration_waitlist_enable', FALSE);
    $settings->set('status', TRUE);
    $settings->set('capacity', 1);
    $settings->save();
    $this->assertFalse($host_entity->isWaitListEnabled());

    // Core capacity checks as normal if waitlist not enabled.
    $this->assertSame('pending', $registration->getState()->id());
    $violations = $registration->validate();
    $this->assertEquals(0, $violations->count());

    // Already completed registration is exempt from capacity check.
    $registration->set('state', 'complete');
    $registration->set('count', 2);
    $registration->save();
    $this->assertSame('complete', $registration->getState()->id());
    $violations = $registration->validate();
    $this->assertEquals(0, $violations->count());

    // Second registration exceeds capacity.
    $registration = $this->createRegistration($node);
    $registration->set('author_uid', 1);
    $user = $this->createUser();
    $registration->set('user_uid', $user->id());
    $violations = $registration->validate();
    $this->assertEquals(1, $violations->count());
    $this->assertEquals('Sorry, unable to register for <em class="placeholder">My event</em> due to: insufficient spaces remaining.', (string) $violations[0]->getMessage());
    $this->assertSame('capacity', $violations[0]->getCode());
    // Already completed registration is exempt from capacity check.
    $registration->set('state', 'complete');
    $registration->save();
    $violations = $registration->validate();
    $this->assertEquals(0, $violations->count());
  }

  /**
   * @covers ::validate
   */
  public function testRegistrationCapacityConstraintWithWaitlist() {
    $node = $this->createAndSaveNode();
    $user = $this->createUser([
      'create registration',
    ]);
    $this->setCurrentUser($user);

    $registration = $this->createRegistration($node);
    $registration->set('author_uid', 1);
    $user = $this->createUser();
    $registration->set('user_uid', $user->id());
    /** @var \Drupal\registration_waitlist\HostEntityInterface $host_entity */
    $host_entity = $registration->getHostEntity();
    /** @var \Drupal\registration\RegistrationSettingsStorage $storage */
    $settings = $host_entity->getSettings();
    $settings->set('status', TRUE);
    $settings->set('capacity', 1);
    $settings->set('registration_waitlist_enable', TRUE);
    $settings->set('registration_waitlist_capacity', 1);
    $settings->save();
    $this->assertTrue($host_entity->isWaitListEnabled());

    // First registration uses only capacity spaces.
    $this->assertSame('pending', $registration->getState()->id());
    $violations = $registration->validate();
    $this->assertEquals(0, $violations->count());
    $registration->set('count', 2);
    $registration->set('state', 'complete');
    $registration->save();
    $this->assertSame('complete', $registration->getState()->id());

    // Existing registration is exempt from capacity check.
    $registration->set('count', 2);
    $registration->save();
    $violations = $registration->validate();
    $this->assertEquals(0, $violations->count());
    $this->assertSame('complete', $registration->getState()->id());

    // New registration is placed on waitlist if host is full.
    $this->assertTrue($host_entity->hasRoom());
    $this->assertFalse($host_entity->hasRoomOffWaitlist());
    $this->assertTrue($host_entity->hasRoomOnWaitlist());
    $registration = $this->createRegistration($node);
    $registration->set('author_uid', 1);
    $user = $this->createUser();
    $registration->set('user_uid', $user->id());
    $this->assertSame('pending', $registration->getState()->id());
    $violations = $registration->validate();
    $this->assertEquals(0, $violations->count());
    $registration->save();
    $this->assertSame('waitlist', $registration->getState()->id());
    $violations = $registration->validate();
    $this->assertEquals(0, $violations->count());

    // Capacity violations if waitlist is full.
    $this->assertFalse($host_entity->hasRoomOffWaitlist());
    $this->assertFalse($host_entity->hasRoomOnWaitlist());
    $registration = $this->createRegistration($node);
    $registration->set('author_uid', 1);
    $user = $this->createUser();
    $registration->set('user_uid', $user->id());
    $this->assertFalse($host_entity->hasRoomOffWaitlist(1, $registration));
    $this->assertFalse($host_entity->hasRoomOnWaitlist(1, $registration));
    $this->assertSame('pending', $registration->getState()->id());
    $violations = $registration->validate();
    $this->assertEquals(1, $violations->count());
    $this->assertEquals('Sorry, unable to register for <em class="placeholder">My event</em> because the wait list is full.', (string) $violations[0]->getMessage());
    $this->assertSame('waitlist_capacity', $violations[0]->getCode());

    // Registration already on waitlist is exempt from capacity check.
    $registration->set('state', 'waitlist');
    $registration->save();
    $this->assertSame('waitlist', $registration->getState()->id());
    $violations = $registration->validate();
    $this->assertEquals(0, $violations->count());
  }

}
