<?php

namespace Drupal\Tests\registration_waitlist\Kernel;

use Drupal\workflows\Entity\Workflow;

/**
 * Tests registration state.
 *
 * @coversDefaultClass \Drupal\registration\RegistrationState
 *
 * @group registration
 */
class RegistrationWaitListStateTest extends RegistrationWaitListKernelTestBase {

  /**
   * Tests the wait list state.
   */
  public function testWaitListState() {
    $workflow = Workflow::load('registration');

    // Wait list.
    $state = $workflow
      ->getTypePlugin()
      ->getState('waitlist');

    /** @var \Drupal\registration\RegistrationState $state */
    $this->assertEquals('Special state for registrations after capacity is reached.', $state->getDescription());
    $this->assertFalse($state->isActive());
    $this->assertFalse($state->isCanceled());
    $this->assertFalse($state->isHeld());
    $this->assertTrue($state->isShownOnForm());
    $this->assertEquals('waitlist', $state->id());
    $this->assertEquals('Wait list', $state->label());
    $this->assertEquals(10, $state->weight());
    $this->assertTrue($state->canTransitionTo('complete'));
    $this->assertTrue($state->canTransitionTo('canceled'));
    $this->assertFalse($state->canTransitionTo('pending'));

    $transition = $state->getTransitionTo('complete');
    $this->assertEquals('complete', $transition->id());

    $transitions = $state->getTransitions();
    $this->assertArrayHasKey('complete', $transitions);
    $this->assertArrayHasKey('cancel', $transitions);
  }

}
