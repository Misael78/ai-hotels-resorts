<?php

namespace Drupal\Tests\registration_workflow\Kernel;

use Drupal\Core\Routing\RouteMatch;
use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\Tests\registration\Traits\RegistrationCreationTrait;
use Drupal\registration_workflow\Access\StateTransitionAccessCheck;
use Symfony\Component\Routing\Route;

/**
 * Tests registration transition access.
 *
 * @coversDefaultClass \Drupal\registration_workflow\Access\StateTransitionAccessCheck
 *
 * @group registration
 */
class RegistrationWorkflowStateTransitionAccessTest extends RegistrationWorkflowKernelTestBase {

  use NodeCreationTrait;
  use RegistrationCreationTrait;

  /**
   * @covers ::checkAccess
   */
  public function testWorkflowStateTransitionAccess() {
    $node = $this->createAndSaveNode();
    $registration = $this->createAndSaveRegistration($node);
    /** @var \Drupal\Core\Config\ConfigFactoryInterface $config_factory */
    $config_factory = $this->container->get('config.factory');
    /** @var \Drupal\registration_workflow\StateTransitionValidationInterface $validator */
    $validator = $this->container->get('registration_workflow.validation');
    $access_checker = new StateTransitionAccessCheck($config_factory, $validator);

    // By default, update access to registrations is required for access
    // to a transition. Disable that here for this round of tests.
    $config_factory
      ->getEditable('registration_workflow.settings')
      ->set('require_update_access', FALSE)
      ->save();

    $route = new Route('/registration/{registration}/transition/{transition}');
    $route
      ->addDefaults([
        '_form' => '\Drupal\registration_workflow\Form\StateTransitionForm',
      ])
      ->addRequirements([
        '_state_transition_access_check' => 'TRUE',
      ])
      ->setOption('_admin_route', TRUE)
      ->setOption('parameters', [
        'registration' => ['type' => 'entity:registration'],
      ]);

    // Access to complete a pending registration is allowed with the right
    // permission.
    $registration->set('state', 'pending');
    $registration->save();
    $account = $this->createUser(['use registration complete transition']);
    $route_match = new RouteMatch('registration_workflow.transition', $route, [
      'registration' => $registration,
      'transition' => 'complete',
    ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    // Wrong permission.
    $account = $this->createUser(['use registration hold transition']);
    $route_match = new RouteMatch('registration_workflow.transition', $route, [
      'registration' => $registration,
      'transition' => 'complete',
    ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    // Invalid transition name.
    $account = $this->createUser(['use registration complete transition']);
    $route_match = new RouteMatch('registration_workflow.transition', $route, [
      'registration' => $registration,
      'transition' => 'complete_cruft',
    ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    // Invalid transition since the registration is already complete now.
    $registration->set('state', 'complete');
    $registration->save();
    $account = $this->createUser(['use registration complete transition']);
    $route_match = new RouteMatch('registration_workflow.transition', $route, [
      'registration' => $registration,
      'transition' => 'complete',
    ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    // Access to cancel a completed registration is allowed with the right
    // permission.
    $account = $this->createUser(['use registration cancel transition']);
    $route_match = new RouteMatch('registration_workflow.transition', $route, [
      'registration' => $registration,
      'transition' => 'cancel',
    ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    // Wrong permission.
    $account = $this->createUser(['use registration hold transition']);
    $route_match = new RouteMatch('registration_workflow.transition', $route, [
      'registration' => $registration,
      'transition' => 'cancel',
    ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    // Invalid transition name.
    $account = $this->createUser(['use registration cancel transition']);
    $route_match = new RouteMatch('registration_workflow.transition', $route, [
      'registration' => $registration,
      'transition' => 'cancel_cruft',
    ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    // Invalid transition since the registration is already canceled now.
    $registration->set('state', 'canceled');
    $registration->save();
    $account = $this->createUser(['use registration cancel transition']);
    $route_match = new RouteMatch('registration_workflow.transition', $route, [
      'registration' => $registration,
      'transition' => 'cancel',
    ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());
  }

  /**
   * @covers ::checkAccess
   */
  public function testWorkflowStateTransitionUpdateRequiredAccess() {
    // By default, update access to registrations is required for access
    // to a transition.
    $node = $this->createAndSaveNode();
    $registration = $this->createAndSaveRegistration($node);
    /** @var \Drupal\Core\Config\ConfigFactoryInterface $config_factory */
    $config_factory = $this->container->get('config.factory');
    /** @var \Drupal\registration_workflow\StateTransitionValidationInterface $validator */
    $validator = $this->container->get('registration_workflow.validation');
    $access_checker = new StateTransitionAccessCheck($config_factory, $validator);

    $route = new Route('/registration/{registration}/transition/{transition}');
    $route
      ->addDefaults([
        '_form' => '\Drupal\registration_workflow\Form\StateTransitionForm',
      ])
      ->addRequirements([
        '_state_transition_access_check' => 'TRUE',
      ])
      ->setOption('_admin_route', TRUE)
      ->setOption('parameters', [
        'registration' => ['type' => 'entity:registration'],
      ]);

    // Access to complete a pending registration is allowed with the right
    // permissions.
    $registration->set('state', 'pending');
    $registration->save();
    $account = $this->createUser(['use registration complete transition']);
    $route_match = new RouteMatch('registration_workflow.transition', $route, [
      'registration' => $registration,
      'transition' => 'complete',
    ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());
    $account = $this->createUser([
      'use registration complete transition',
      'update any conference registration',
    ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    // Wrong permission.
    $account = $this->createUser([
      'use registration hold transition',
      'update any conference registration',
    ]);
    $route_match = new RouteMatch('registration_workflow.transition', $route, [
      'registration' => $registration,
      'transition' => 'complete',
    ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    // Invalid transition name.
    $account = $this->createUser([
      'use registration complete transition',
      'update any conference registration',
    ]);
    $route_match = new RouteMatch('registration_workflow.transition', $route, [
      'registration' => $registration,
      'transition' => 'complete_cruft',
    ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    // Invalid transition since the registration is already complete now.
    $registration->set('state', 'complete');
    $registration->save();
    $account = $this->createUser([
      'use registration complete transition',
      'update any conference registration',
    ]);
    $route_match = new RouteMatch('registration_workflow.transition', $route, [
      'registration' => $registration,
      'transition' => 'complete',
    ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    // Access to cancel a completed registration is allowed with the right
    // permissions.
    $account = $this->createUser(['use registration cancel transition']);
    $route_match = new RouteMatch('registration_workflow.transition', $route, [
      'registration' => $registration,
      'transition' => 'cancel',
    ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());
    $account = $this->createUser([
      'use registration cancel transition',
      'update any conference registration',
    ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    // Wrong permission.
    $account = $this->createUser([
      'use registration hold transition',
      'update any conference registration',
    ]);
    $route_match = new RouteMatch('registration_workflow.transition', $route, [
      'registration' => $registration,
      'transition' => 'cancel',
    ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    // Invalid transition name.
    $account = $this->createUser([
      'use registration cancel transition',
      'update any conference registration',
    ]);
    $route_match = new RouteMatch('registration_workflow.transition', $route, [
      'registration' => $registration,
      'transition' => 'cancel_cruft',
    ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    // Invalid transition since the registration is already canceled now.
    $registration->set('state', 'canceled');
    $registration->save();
    $account = $this->createUser([
      'use registration cancel transition',
      'update any conference registration',
    ]);
    $route_match = new RouteMatch('registration_workflow.transition', $route, [
      'registration' => $registration,
      'transition' => 'cancel',
    ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());
  }

  /**
   * @covers ::checkAccess
   */
  public function testWorkflowStateTransitionOwnRegistrationAccess() {
    // By default, users can complete their own registrations.
    $node = $this->createAndSaveNode();
    $registration = $this->createAndSaveRegistration($node);
    /** @var \Drupal\Core\Config\ConfigFactoryInterface $config_factory */
    $config_factory = $this->container->get('config.factory');
    /** @var \Drupal\registration_workflow\StateTransitionValidationInterface $validator */
    $validator = $this->container->get('registration_workflow.validation');
    $access_checker = new StateTransitionAccessCheck($config_factory, $validator);

    $route = new Route('/registration/{registration}/transition/{transition}');
    $route
      ->addDefaults([
        '_form' => '\Drupal\registration_workflow\Form\StateTransitionForm',
      ])
      ->addRequirements([
        '_state_transition_access_check' => 'TRUE',
      ])
      ->setOption('_admin_route', TRUE)
      ->setOption('parameters', [
        'registration' => ['type' => 'entity:registration'],
      ]);

    // Access to complete your own registration is allowed by default.
    $account = $this->createUser([
      'use registration complete transition',
      'update any conference registration',
    ]);
    $registration->set('state', 'pending');
    $registration->set('user_uid', $account->id());
    $registration->save();
    $route_match = new RouteMatch('registration_workflow.transition', $route, [
      'registration' => $registration,
      'transition' => 'complete',
    ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    // Prevent completion of own registrations.
    $config_factory
      ->getEditable('registration_workflow.settings')
      ->set('prevent_complete_own', TRUE)
      ->save();

    $account = $this->createUser([
      'use registration complete transition',
      'update any conference registration',
    ]);
    $registration = $this->createAndSaveRegistration($node);
    $registration->set('state', 'pending');
    $registration->set('user_uid', $account->id());
    $registration->save();
    $route_match = new RouteMatch('registration_workflow.transition', $route, [
      'registration' => $registration,
      'transition' => 'complete',
    ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    // Switch to a different registrant.
    $registration->set('user_uid', 1);
    $registration->save();
    $route_match = new RouteMatch('registration_workflow.transition', $route, [
      'registration' => $registration,
      'transition' => 'complete',
    ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    // Hold transition is allowed for own registrations.
    $account = $this->createUser([
      'use registration hold transition',
      'update any conference registration',
    ]);
    $registration = $this->createAndSaveRegistration($node);
    $registration->set('state', 'pending');
    $registration->set('user_uid', $account->id());
    $registration->save();
    $route_match = new RouteMatch('registration_workflow.transition', $route, [
      'registration' => $registration,
      'transition' => 'hold',
    ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());
  }

}
