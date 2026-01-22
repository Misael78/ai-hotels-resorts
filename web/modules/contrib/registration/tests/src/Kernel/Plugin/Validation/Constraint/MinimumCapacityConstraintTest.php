<?php

namespace Drupal\Tests\registration\Kernel\Plugin\Validation\Constraint;

use Drupal\Tests\registration\Kernel\RegistrationKernelTestBase;
use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\Tests\registration\Traits\RegistrationCreationTrait;

/**
 * Tests the Minimum Capacity constraint.
 *
 * @coversDefaultClass \Drupal\registration\Plugin\Validation\Constraint\MinimumCapacityConstraint
 *
 * @group registration
 */
class MinimumCapacityConstraintTest extends RegistrationKernelTestBase {

  use NodeCreationTrait;
  use RegistrationCreationTrait;

  /**
   * @covers ::validate
   */
  public function testMinimumCapacityConstraint() {
    $node = $this->createAndSaveNode();

    // Register for two spaces.
    $registration = $this->createRegistration($node);
    $registration->set('count', 2);
    $registration->save();

    $host_entity = $registration->getHostEntity();
    $settings = $host_entity->getSettings();

    // Do not allow a null capacity.
    $settings->set('capacity', NULL);
    $violations = $settings->validate();
    $this->assertEquals(1, $violations->count());

    // Do not allow a negative capacity.
    $settings->set('capacity', -1);
    $violations = $settings->validate();
    $this->assertEquals(1, $violations->count());

    // Allow a capacity of 0, which means "unlimited".
    $settings->set('capacity', 0);
    $violations = $settings->validate();
    $this->assertEquals(0, $violations->count());

    // Do not allow capacity less than spaces reserved.
    $settings->set('capacity', 1);
    $violations = $settings->validate();
    $this->assertEquals(1, $violations->count());
    $this->assertEquals('The minimum capacity for this conference is 2 due to existing registrations.', (string) $violations[0]->getMessage());

    // Allow capacity equal to number of spaces reserved.
    $settings->set('capacity', 2);
    $violations = $settings->validate();
    $this->assertEquals(0, $violations->count());

    // Allow capacity greater than number of spaces reserved.
    $settings->set('capacity', 3);
    $violations = $settings->validate();
    $this->assertEquals(0, $violations->count());
  }

}
