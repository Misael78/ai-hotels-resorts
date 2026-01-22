<?php

namespace Drupal\Tests\registration\Kernel;

use Drupal\registration\RegistrationState;
use Drupal\workflows\Entity\Workflow;
use Drupal\workflows\State;

/**
 * Tests registration state.
 *
 * @coversDefaultClass \Drupal\registration\RegistrationState
 *
 * @group registration
 */
class RegistrationStateTest extends RegistrationKernelTestBase {

  /**
   * @covers ::getDescription
   * @covers ::isActive
   * @covers ::isCanceled
   * @covers ::isHeld
   * @covers ::isShownOnForm
   * @covers ::id
   * @covers ::label
   * @covers ::weight
   * @covers ::canTransitionTo
   * @covers ::getTransitionTo
   * @covers ::getTransitions
   */
  public function testState() {
    $workflow = Workflow::load('registration');

    // Pending.
    $state = $workflow
      ->getTypePlugin()
      ->getState('pending');

    /** @var \Drupal\registration\RegistrationState $state */
    $this->assertEquals('Registration is pending.', $state->getDescription());
    $this->assertTrue($state->isActive());
    $this->assertFalse($state->isCanceled());
    $this->assertFalse($state->isHeld());
    $this->assertFalse($state->isShownOnForm());
    $this->assertEquals('pending', $state->id());
    $this->assertEquals('Pending', $state->label());
    $this->assertEquals(0, $state->weight());
    $this->assertTrue($state->canTransitionTo('complete'));
    $this->assertFalse($state->canTransitionTo('pending'));

    $transition = $state->getTransitionTo('complete');
    $this->assertEquals('complete', $transition->id());

    $transitions = $state->getTransitions();
    $this->assertArrayHasKey('complete', $transitions);

    // Complete.
    $state = $transition->to();

    $this->assertEquals('Registration has been completed.', $state->getDescription());
    $this->assertTrue($state->isActive());
    $this->assertFalse($state->isCanceled());
    $this->assertFalse($state->isHeld());
    $this->assertFalse($state->isShownOnForm());
    $this->assertEquals('complete', $state->id());
    $this->assertEquals('Complete', $state->label());
    // Held state is in between pending and complete.
    $this->assertEquals(2, $state->weight());
    $this->assertFalse($state->canTransitionTo('complete'));
    $this->assertFalse($state->canTransitionTo('pending'));

    // Completed registrations can be canceled.
    $transitions = $state->getTransitions();
    $this->assertCount(1, $transitions);
  }

  /**
   * Tests creation of a new state.
   */
  public function testNewState() {
    $workflow = Workflow::load('registration');
    $workflow_state = new State($workflow->getTypePlugin(), 'ticketed', 'Ticketed', 10);
    $active = FALSE;
    $canceled = TRUE;
    $held = FALSE;
    $show = FALSE;
    $state = new RegistrationState($workflow_state, 'Registration has been ticketed.', $active, $canceled, $held, $show);

    $this->assertEquals('Registration has been ticketed.', $state->getDescription());
    $this->assertFalse($state->isActive());
    $this->assertTrue($state->isCanceled());
    $this->assertFalse($state->isHeld());
    $this->assertFalse($state->isShownOnForm());
    $this->assertEquals('ticketed', $state->id());
    $this->assertEquals('Ticketed', $state->label());
    $this->assertEquals(10, $state->weight());
  }

}
