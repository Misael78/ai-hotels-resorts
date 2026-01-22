<?php

namespace Drupal\Tests\registration_waitlist\Kernel;

use Drupal\Core\Routing\RouteMatch;
use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\Tests\registration\Traits\RegistrationCreationTrait;
use Drupal\registration_waitlist\Access\StateTransitionAccessCheck;
use Symfony\Component\Routing\Route;

/**
 * Tests registration transition access.
 *
 * @coversDefaultClass \Drupal\registration_waitlist\Access\StateTransitionAccessCheck
 *
 * @group registration
 */
class RegistrationWaitListStateTransitionAccessTest extends RegistrationWaitListKernelTestBase {

  use NodeCreationTrait;
  use RegistrationCreationTrait;

  /**
   * Modules to enable.
   *
   * Note that when a child class declares its own $modules list, that list
   * doesn't override this one, it just extends it.
   *
   * @var array
   */
  protected static $modules = [
    'registration_workflow',
  ];

  /**
   * @covers ::access
   */
  public function testWaitlistStateTransitionAccess() {
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

    // Set up the host entity and first registration.
    $node = $this->createAndSaveNode();
    $registration = $this->createRegistration($node);
    $registration->set('state', 'complete');
    $registration->save();

    // Enable the wait list with a capacity of 1 in both standard capacity
    // and wait list capacity.
    $handler = $this->entityTypeManager->getHandler('node', 'registration_host_entity');
    $host_entity = $handler->createHostEntity($node);
    /** @var \Drupal\registration\RegistrationSettingsStorage $storage */
    $storage = $this->entityTypeManager->getStorage('registration_settings');
    $settings = $storage->loadSettingsForHostEntity($host_entity);
    $settings->set('capacity', 1);
    $settings->set('registration_waitlist_enable', TRUE);
    $settings->set('registration_waitlist_capacity', 1);
    $settings->save();

    // Fill the wait list. Standard capacity is already full.
    $registration2 = $this->createRegistration($node);
    $registration2->set('state', 'waitlist');
    $registration2->save();

    $completer = $this->createUser(['use registration complete transition']);
    $canceler = $this->createUser(['use registration cancel transition']);

    // Access to complete a wait listed registration cannot be granted if there
    // is no room for it within standard capacity.
    $account = $this->createUser(['use registration complete transition']);
    $route_match = new RouteMatch('registration_workflow.transition', $route, [
      'registration' => $registration2,
      'transition' => 'complete',
    ]);
    $access_result = $access_checker->access($completer, $route_match);
    $this->assertFalse($access_result->isAllowed());

    // Same scenario but with room this time.
    $node = $this->createAndSaveNode();
    $handler = $this->entityTypeManager->getHandler('node', 'registration_host_entity');
    $host_entity = $handler->createHostEntity($node);
    /** @var \Drupal\registration\RegistrationSettingsStorage $storage */
    $storage = $this->entityTypeManager->getStorage('registration_settings');
    $settings = $storage->loadSettingsForHostEntity($host_entity);
    $settings->set('capacity', 2);
    $settings->set('registration_waitlist_enable', TRUE);
    $settings->set('registration_waitlist_capacity', 1);
    $settings->save();
    $registration = $this->createRegistration($node);
    $registration->set('state', 'complete');
    $registration->save();
    $registration2 = $this->createRegistration($node);
    $registration2->set('state', 'waitlist');
    $registration2->save();
    $route_match = new RouteMatch('registration_workflow.transition', $route, [
      'registration' => $registration2,
      'transition' => 'complete',
    ]);
    $access_result = $access_checker->access($completer, $route_match);
    $this->assertTrue($access_result->isAllowed());
    $access_result = $access_checker->access($canceler, $route_match);
    $this->assertFalse($access_result->isAllowed());

    // Original scenario but moving to canceled is allowed.
    $node = $this->createAndSaveNode();
    $handler = $this->entityTypeManager->getHandler('node', 'registration_host_entity');
    $host_entity = $handler->createHostEntity($node);
    /** @var \Drupal\registration\RegistrationSettingsStorage $storage */
    $storage = $this->entityTypeManager->getStorage('registration_settings');
    $settings = $storage->loadSettingsForHostEntity($host_entity);
    $settings->set('capacity', 1);
    $settings->set('registration_waitlist_enable', TRUE);
    $settings->set('registration_waitlist_capacity', 1);
    $settings->save();
    $registration = $this->createRegistration($node);
    $registration->set('state', 'complete');
    $registration->save();
    $registration2 = $this->createRegistration($node);
    $registration2->set('state', 'waitlist');
    $registration2->save();
    $route_match = new RouteMatch('registration_workflow.transition', $route, [
      'registration' => $registration2,
      'transition' => 'complete',
    ]);
    $access_result = $access_checker->access($completer, $route_match);
    $this->assertFalse($access_result->isAllowed());
    $route_match = new RouteMatch('registration_workflow.transition', $route, [
      'registration' => $registration2,
      'transition' => 'cancel',
    ]);
    $access_result = $access_checker->access($completer, $route_match);
    $this->assertFalse($access_result->isAllowed());
    $access_result = $access_checker->access($canceler, $route_match);
    $this->assertTrue($access_result->isAllowed());
  }

}
