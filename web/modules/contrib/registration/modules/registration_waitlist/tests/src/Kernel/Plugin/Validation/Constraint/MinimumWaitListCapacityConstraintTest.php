<?php

namespace Drupal\Tests\registration_waitlist\Kernel\Plugin\Validation\Constraint;

use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\Tests\registration\Traits\RegistrationCreationTrait;
use Drupal\Tests\registration_waitlist\Kernel\RegistrationWaitListKernelTestBase;

/**
 * Tests the Minimum Wait List Capacity constraint.
 *
 * @coversDefaultClass \Drupal\registration_waitlist\Plugin\Validation\Constraint\MinimumWaitListCapacityConstraint
 *
 * @group registration
 */
class MinimumWaitListCapacityConstraintTest extends RegistrationWaitListKernelTestBase {

  use NodeCreationTrait;
  use RegistrationCreationTrait;

  /**
   * @covers ::validate
   */
  public function testMinimumWaitListCapacityConstraint() {
    $node = $this->createAndSaveNode();

    // Fill the standard capacity.
    $registration = $this->createRegistration($node);
    $registration->set('count', 5);
    $registration->save();

    // Put two spaces on the wait list.
    $registration = $this->createRegistration($node);
    $registration->set('count', 2);
    $registration->save();

    $host_entity = $registration->getHostEntity();
    $settings = $host_entity->getSettings();

    // Do not allow a null wait list capacity.
    $settings->set('registration_waitlist_capacity', NULL);
    $violations = $settings->validate();
    $this->assertEquals(1, $violations->count());

    // Do not allow a negative wait list capacity.
    $settings->set('registration_waitlist_capacity', -1);
    $violations = $settings->validate();
    $this->assertEquals(1, $violations->count());

    // Allow a wait list capacity of 0, which means "unlimited".
    $settings->set('registration_waitlist_capacity', 0);
    $violations = $settings->validate();
    $this->assertEquals(0, $violations->count());

    // Do not allow wait list capacity less than wait listed spaces reserved.
    $settings->set('registration_waitlist_capacity', 1);
    $violations = $settings->validate();
    $this->assertEquals(1, $violations->count());
    $this->assertEquals('The minimum wait list capacity for this @type is @capacity due to existing wait listed registrations.', (string) $violations[0]->getMessageTemplate());

    // Allow wait list capacity equal to number of wait listed spaces reserved.
    $settings->set('registration_waitlist_capacity', 2);
    $violations = $settings->validate();
    $this->assertEquals(0, $violations->count());

    // Allow wait list capacity greater than number of wait listed spaces
    // reserved.
    $settings->set('registration_waitlist_capacity', 3);
    $violations = $settings->validate();
    $this->assertEquals(0, $violations->count());
  }

}
