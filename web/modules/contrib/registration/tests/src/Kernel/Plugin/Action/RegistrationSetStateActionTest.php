<?php

namespace Drupal\Tests\registration\Kernel\Plugin\Action;

use Drupal\Core\Action\ActionManager;
use Drupal\Tests\registration\Kernel\RegistrationKernelTestBase;
use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\Tests\registration\Traits\RegistrationCreationTrait;

/**
 * Tests the registration 'set state' action.
 *
 * @coversDefaultClass \Drupal\registration\Plugin\Action\RegistrationSetStateAction
 *
 * @group registration
 */
class RegistrationSetStateActionTest extends RegistrationKernelTestBase {

  use NodeCreationTrait;
  use RegistrationCreationTrait;

  /**
   * The action manager.
   *
   * @var \Drupal\Core\Action\ActionManager
   */
  protected ActionManager $actionManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $admin_user = $this->createUser();
    $this->setCurrentUser($admin_user);

    $this->actionManager = $this->container->get('plugin.manager.action');
  }

  /**
   * @covers ::access
   * @covers ::execute
   * @covers ::getConfiguration
   */
  public function testSetStateAction() {
    $action = $this->actionManager->createInstance('registration_views_set_state_action');
    $action->setConfiguration([
      'registration_state' => 'complete',
    ]);
    $configuration = $action->getConfiguration();
    $this->assertArrayHasKey('registration_state', $configuration);

    $node = $this->createAndSaveNode();
    $registration = $this->createAndSaveRegistration($node);

    // Must be able to update the registration and edit state to access the
    // action.
    $account = $this->createUser(['edit conference registration state']);
    $this->assertFalse($action->access($registration, $account));
    $account = $this->createUser(['update any conference registration']);
    $this->assertFalse($action->access($registration, $account));
    $account = $this->createUser([
      'update any conference registration',
      'edit conference registration state',
    ]);
    $this->assertTrue($action->access($registration, $account));

    // Administrators have an implied right to edit state.
    $account = $this->createUser(['administer registration']);
    $this->assertTrue($action->access($registration, $account));
    $account = $this->createUser(['administer conference registration']);
    $this->assertTrue($action->access($registration, $account));
    // But "administer own" is not enough.
    $account = $this->createUser(['administer own conference registration']);
    $this->assertFalse($action->access($registration, $account));
    $account = $this->createUser(['administer own conference registration settings']);
    $this->assertFalse($action->access($registration, $account));

    $action->execute($registration);
    $this->assertEquals('complete', $registration->getState()->id());

    // There must be a valid transition to the new state.
    $action->setConfiguration([
      'registration_state' => 'pending',
    ]);
    $account = $this->createUser(['administer registration']);
    $this->assertFalse($action->access($registration, $account));
    $action->execute($registration);
    $this->assertEquals('complete', $registration->getState()->id());
    $action->setConfiguration([
      'registration_state' => 'canceled',
    ]);
    $account = $this->createUser(['administer registration']);
    $this->assertTrue($action->access($registration, $account));
    $action->execute($registration);
    $this->assertEquals('canceled', $registration->getState()->id());
  }

}
