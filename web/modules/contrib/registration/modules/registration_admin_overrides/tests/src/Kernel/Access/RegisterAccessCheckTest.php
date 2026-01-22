<?php

namespace Drupal\Tests\registration_admin_overrides\Kernel\Access;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\Tests\registration_admin_overrides\Kernel\RegistrationAdminOverridesKernelTestBase;
use Drupal\registration\RegistrationManagerInterface;
use Drupal\registration\Access\RegisterAccessCheck;
use Drupal\registration_admin_overrides\RegistrationOverrideCheckerInterface;

/**
 * Tests the "register" access check.
 *
 * @group registration
 */
class RegisterAccessCheckTest extends RegistrationAdminOverridesKernelTestBase {

  use NodeCreationTrait;

  /**
   * The configuration factory.
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The registration manager.
   *
   * @var \Drupal\registration\RegistrationManagerInterface
   */
  protected RegistrationManagerInterface $registrationManager;

  /**
   * The registration override checker.
   *
   * @var \Drupal\registration_admin_overrides\RegistrationOverrideCheckerInterface
   */
  protected RegistrationOverrideCheckerInterface $overrideChecker;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $admin_user = $this->createUser();
    $this->setCurrentUser($admin_user);

    $this->configFactory = $this->container->get('config.factory');
    $this->registrationManager = $this->container->get('registration.manager');
    $this->overrideChecker = $this->container->get('registration_admin_overrides.override_checker');
  }

  /**
   * @covers ::access
   */
  public function testAccessRegistrationConfiguredNoOverrides() {
    // This is a regression test that runs the same tests as registration core.
    $access_checker = new RegisterAccessCheck($this->configFactory, $this->entityTypeManager, $this->registrationManager);

    $node = $this->createAndSaveNode();
    $entity_type = $node->getEntityType();

    $route = $this->registrationManager->getRoute($entity_type, 'register');
    $route_name = $this->registrationManager->getBaseRouteName($entity_type) . '.register';
    $route_match = new RouteMatch($route_name, $route, [
      'node' => $node,
    ]);

    $account = $this->createUser(['access registration overview']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    $account = $this->createUser(['administer registration']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    $account = $this->createUser(['administer conference registration']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    $account = $this->createUser(['create registration']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    $account = $this->createUser(['create conference registration self']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    $account = $this->createUser(['create conference registration other users']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    $account = $this->createUser(['create conference registration other anonymous']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    $parameters = $route_match->getParameters();
    $host_entity = $this->registrationManager->getEntityFromParameters($parameters, TRUE);
    $settings = $host_entity->getSettings();
    $settings->set('status', FALSE);
    $settings->save();

    $account = $this->createUser(['access registration overview']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    $account = $this->createUser(['administer registration']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    $account = $this->createUser(['administer conference registration']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    $account = $this->createUser(['create registration']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    $account = $this->createUser(['create conference registration self']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    $account = $this->createUser(['create conference registration other users']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    $account = $this->createUser(['create conference registration other anonymous']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());
  }

  /**
   * @covers ::access
   */
  public function testAccessRegistrationNotConfiguredNoOverrides() {
    // This is a regression test that runs the same tests as registration core.
    $access_checker = new RegisterAccessCheck($this->configFactory, $this->entityTypeManager, $this->registrationManager);

    $node = $this->createNode();
    $node->set('event_registration', NULL);
    $node->save();
    $entity_type = $node->getEntityType();

    $route = $this->registrationManager->getRoute($entity_type, 'register');
    $route_name = $this->registrationManager->getBaseRouteName($entity_type) . '.register';
    $route_match = new RouteMatch($route_name, $route, [
      'node' => $node,
    ]);

    $account = $this->createUser(['access registration overview']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    $account = $this->createUser(['administer registration']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    $account = $this->createUser(['administer conference registration']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    $account = $this->createUser(['create registration']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    $account = $this->createUser(['create conference registration self']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    $account = $this->createUser(['create conference registration other users']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    $account = $this->createUser(['create conference registration other anonymous']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());
  }

  /**
   * @covers ::access
   */
  public function testAccessRegistrationConfiguredWithOverrides() {
    $handler = $this->entityTypeManager->getHandler('node', 'registration_host_entity');
    $access_checker = new RegisterAccessCheck($this->configFactory, $this->entityTypeManager, $this->registrationManager);

    $node = $this->createAndSaveNode();
    $entity_type = $node->getEntityType();
    $host_entity = $handler->createHostEntity($node);
    $settings = $host_entity->getSettings();

    $route = $this->registrationManager->getRoute($entity_type, 'register');
    $route_name = $this->registrationManager->getBaseRouteName($entity_type) . '.register';
    $route_match = new RouteMatch($route_name, $route, [
      'node' => $node,
    ]);

    // Disable registration.
    $settings->set('status', FALSE);
    $settings->save();
    $account = $this->createUser(['administer registration']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    // Override the status.
    $account = $this->createUser([
      'administer registration',
      'registration override status',
    ]);
    $this->setCurrentUser($account);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    // Disable the override on the registration type.
    $this->regType->setThirdPartySetting('registration_admin_overrides', 'status', FALSE);
    $this->regType->save();
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());
  }

}
