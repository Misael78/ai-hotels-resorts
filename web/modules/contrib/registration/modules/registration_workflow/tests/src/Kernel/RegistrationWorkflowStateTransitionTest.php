<?php

namespace Drupal\Tests\registration_workflow\Kernel;

use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\Tests\registration\Traits\RegistrationCreationTrait;
use Drupal\workflows\Entity\Workflow;

/**
 * Tests registration state transitions.
 *
 * @coversDefaultClass \Drupal\registration_workflow\StateTransitionValidation
 *
 * @group registration
 */
class RegistrationWorkflowStateTransitionTest extends RegistrationWorkflowKernelTestBase {

  use NodeCreationTrait;
  use RegistrationCreationTrait;

  /**
   * @covers ::getValidTransitions
   * @covers ::isTransitionValid
   */
  public function testWorkflowStateTransitions() {
    $node = $this->createAndSaveNode();
    $registration = $this->createAndSaveRegistration($node);
    $workflow = Workflow::load('registration');

    $state_names = [
      'pending',
      'held',
      'complete',
      'canceled',
      'waitlist',
    ];

    $permissions = [
      'use registration hold transition',
      'use registration complete transition',
      'use registration cancel transition',
    ];
    $user = $this->createUser($permissions);
    $this->setCurrentUser($user);

    /** @var \Drupal\registration\RegistrationState[] $states */
    $states = [];
    foreach ($state_names as $state_name) {
      $state = $workflow
        ->getTypePlugin()
        ->getState($state_name);
      $states[$state->id()] = $state;
    }

    $validator = $this->container->get('registration_workflow.validation');

    // Check transitions for a pending registration.
    $registration->set('state', 'pending');
    $registration->save();
    $transitions = $validator->getValidTransitions($registration);
    $this->assertArrayHasKey('hold', $transitions);
    $this->assertArrayHasKey('complete', $transitions);
    $this->assertArrayHasKey('cancel', $transitions);

    // Check transitions for a held registration.
    $registration->set('state', 'held');
    $registration->save();
    $transitions = $validator->getValidTransitions($registration);
    $this->assertArrayNotHasKey('hold', $transitions);
    $this->assertArrayHasKey('complete', $transitions);
    $this->assertArrayHasKey('cancel', $transitions);

    // Check transitions for a completed registration.
    $registration->set('state', 'complete');
    $registration->save();
    $transitions = $validator->getValidTransitions($registration);
    $this->assertArrayNotHasKey('hold', $transitions);
    $this->assertArrayNotHasKey('complete', $transitions);
    $this->assertArrayHasKey('cancel', $transitions);

    // Check transitions for a canceled registration.
    $registration->set('state', 'canceled');
    $registration->save();
    $transitions = $validator->getValidTransitions($registration);
    $this->assertArrayNotHasKey('hold', $transitions);
    $this->assertArrayNotHasKey('complete', $transitions);
    $this->assertArrayNotHasKey('cancel', $transitions);

    // Check transitions for a wait listed registration.
    $registration->set('state', 'waitlist');
    $registration->save();
    $transitions = $validator->getValidTransitions($registration);
    $this->assertArrayNotHasKey('hold', $transitions);
    $this->assertArrayHasKey('complete', $transitions);
    $this->assertArrayHasKey('cancel', $transitions);

    // Check all state transitions.
    $this->assertFalse($validator->isTransitionValid($workflow, $states['pending'], $states['pending'], $registration));
    $this->assertTrue($validator->isTransitionValid($workflow, $states['pending'], $states['held'], $registration));
    $this->assertTrue($validator->isTransitionValid($workflow, $states['pending'], $states['complete'], $registration));
    $this->assertTrue($validator->isTransitionValid($workflow, $states['pending'], $states['canceled'], $registration));
    $this->assertFalse($validator->isTransitionValid($workflow, $states['pending'], $states['waitlist'], $registration));

    $this->assertFalse($validator->isTransitionValid($workflow, $states['held'], $states['pending'], $registration));
    $this->assertFalse($validator->isTransitionValid($workflow, $states['held'], $states['held'], $registration));
    $this->assertTrue($validator->isTransitionValid($workflow, $states['held'], $states['complete'], $registration));
    $this->assertTrue($validator->isTransitionValid($workflow, $states['held'], $states['canceled'], $registration));
    $this->assertFalse($validator->isTransitionValid($workflow, $states['held'], $states['waitlist'], $registration));

    $this->assertFalse($validator->isTransitionValid($workflow, $states['complete'], $states['pending'], $registration));
    $this->assertFalse($validator->isTransitionValid($workflow, $states['complete'], $states['held'], $registration));
    $this->assertFalse($validator->isTransitionValid($workflow, $states['complete'], $states['complete'], $registration));
    $this->assertTrue($validator->isTransitionValid($workflow, $states['complete'], $states['canceled'], $registration));
    $this->assertFalse($validator->isTransitionValid($workflow, $states['complete'], $states['waitlist'], $registration));

    $this->assertFalse($validator->isTransitionValid($workflow, $states['canceled'], $states['pending'], $registration));
    $this->assertFalse($validator->isTransitionValid($workflow, $states['canceled'], $states['held'], $registration));
    $this->assertFalse($validator->isTransitionValid($workflow, $states['canceled'], $states['complete'], $registration));
    $this->assertFalse($validator->isTransitionValid($workflow, $states['canceled'], $states['canceled'], $registration));
    $this->assertFalse($validator->isTransitionValid($workflow, $states['canceled'], $states['waitlist'], $registration));

    $this->assertFalse($validator->isTransitionValid($workflow, $states['waitlist'], $states['pending'], $registration));
    $this->assertFalse($validator->isTransitionValid($workflow, $states['waitlist'], $states['held'], $registration));
    $this->assertTrue($validator->isTransitionValid($workflow, $states['waitlist'], $states['complete'], $registration));
    $this->assertTrue($validator->isTransitionValid($workflow, $states['waitlist'], $states['canceled'], $registration));
    $this->assertFalse($validator->isTransitionValid($workflow, $states['waitlist'], $states['waitlist'], $registration));
  }

}
