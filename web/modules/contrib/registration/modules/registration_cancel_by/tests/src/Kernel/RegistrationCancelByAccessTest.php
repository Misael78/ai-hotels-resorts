<?php

namespace Drupal\Tests\registration_cancel_by\Kernel;

use Drupal\Core\Routing\RouteMatch;
use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\Tests\registration\Traits\RegistrationCreationTrait;
use Drupal\registration_cancel_by\Access\CancelByAccessCheck;
use Symfony\Component\Routing\Route;

/**
 * Tests registration transition access.
 *
 * @coversDefaultClass \Drupal\registration_cancel_by\Access\CancelByAccessCheck
 *
 * @group registration
 */
class RegistrationCancelByAccessTest extends RegistrationCancelByKernelTestBase {

  use NodeCreationTrait;
  use RegistrationCreationTrait;

  /**
   * @covers ::access
   */
  public function testCancelByAccess() {
    $node = $this->createAndSaveNode();
    $registration = $this->createAndSaveRegistration($node);
    $host_entity = $registration->getHostEntity();
    $settings = $host_entity->getSettings();

    $time = $this->container->get('datetime.time');
    $access_checker = new CancelByAccessCheck($time);

    $route = new Route('/registration/{registration}/transition/{transition}');
    $route
      ->addDefaults([
        '_form' => '\Drupal\registration_workflow\Form\StateTransitionForm',
      ])
      ->addRequirements([
        '_cancel_by_access_check',
      ])
      ->setOption('_admin_route', TRUE)
      ->setOption('parameters', [
        'registration' => ['type' => 'entity:registration'],
      ]);

    // No cancel date.
    $account = $this->createUser(['use registration cancel transition']);
    $route_match = new RouteMatch('registration_workflow.transition', $route, [
      'registration' => $registration,
      'transition' => 'cancel',
    ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    // Before cancel date.
    $settings->set('cancel_by', '2220-01-01T00:00:00');
    $settings->save();
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    // After cancel date.
    $settings->set('cancel_by', '2020-01-01T00:00:00');
    $settings->save();
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    // After cancel date but user has bypass permission.
    $account = $this->createUser([
      'use registration cancel transition',
      'bypass cancel by access',
    ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    // Cancel date is ignored for other transitions besides cancel.
    $account = $this->createUser(['use registration complete transition']);
    $route_match = new RouteMatch('registration_workflow.transition', $route, [
      'registration' => $registration,
      'transition' => 'complete',
    ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    // Invalid transition.
    $account = $this->createUser(['use registration complete transition']);
    $route_match = new RouteMatch('registration_workflow.transition', $route, [
      'registration' => $registration,
      'transition' => 'invalid',
    ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());
  }

}
