<?php

namespace Drupal\Tests\registration\Kernel\Access;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Tests\registration\Kernel\RegistrationKernelTestBase;
use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\Tests\registration\Traits\RegistrationCreationTrait;
use Drupal\registration\Access\RegisterAccessCheck;
use Drupal\registration\RegistrationManagerInterface;

/**
 * Tests the "register" access check.
 *
 * @coversDefaultClass \Drupal\registration\Access\RegisterAccessCheck
 *
 * @group registration
 */
class RegisterAccessCheckTest extends RegistrationKernelTestBase {

  use NodeCreationTrait;
  use RegistrationCreationTrait;

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $admin_user = $this->createUser();
    $this->setCurrentUser($admin_user);

    $this->configFactory = $this->container->get('config.factory');
    $this->registrationManager = $this->container->get('registration.manager');
  }

  /**
   * @covers ::access
   */
  public function testAccessRegistrationConfigured() {
    $access_checker = new RegisterAccessCheck($this->configFactory, $this->entityTypeManager, $this->registrationManager);

    $node = $this->createAndSaveNode();
    $entity_type = $node->getEntityType();

    $route = $this->registrationManager->getRoute($entity_type, 'register');
    $route_name = $this->registrationManager->getBaseRouteName($entity_type) . '.register';
    $route_match = new RouteMatch($route_name, $route, [
      'node' => $node,
    ]);

    $parameters = $route_match->getParameters();
    $host_entity = $this->registrationManager->getEntityFromParameters($parameters, TRUE);
    $settings = $host_entity->getSettings();
    $settings->set('status', TRUE);
    $settings->save();

    $account = $this->createUser(['access registration overview']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    $account = $this->createUser(['administer registration']);
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

    $settings->set('status', FALSE);
    $settings->save();

    $account = $this->createUser(['access registration overview']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    $account = $this->createUser(['administer registration']);
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

    // Re-enable.
    $settings->set('status', TRUE);
    $settings->save();

    $account = $this->createUser(['administer registration']);
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

    // After close.
    $settings->set('close', '2020-01-01T00:00:00');
    $settings->save();

    $account = $this->createUser(['administer registration']);
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

    // Lenient access control.
    $global_settings = $this->configFactory->getEditable('registration.settings');
    $global_settings->set('lenient_access_check', TRUE);
    $global_settings->save();

    $account = $this->createUser(['administer registration']);
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

    // Status still matters with lenient access control.
    $settings->set('status', FALSE);
    $settings->save();

    $account = $this->createUser(['administer registration']);
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
  public function testAccessRegistrationNotConfigured() {
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

    $account = $this->createUser(['create conference registration self']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    $account = $this->createUser(['create conference registration other users']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    $account = $this->createUser(['create conference registration other anonymous']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    // Lenient access control does not matter when registration not configured.
    $global_settings = $this->configFactory->getEditable('registration.settings');
    $global_settings->set('lenient_access_check', TRUE);
    $global_settings->save();

    $account = $this->createUser(['access registration overview']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    $account = $this->createUser(['administer registration']);
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
   * Tests cacheability of the register access check.
   */
  public function testAccessCheckCacheability() {
    $access_checker = new RegisterAccessCheck($this->configFactory, $this->entityTypeManager, $this->registrationManager);

    $node = $this->createAndSaveNode();
    $entity_type = $node->getEntityType();

    $route = $this->registrationManager->getRoute($entity_type, 'register');
    $route_name = $this->registrationManager->getBaseRouteName($entity_type) . '.register';
    $route_match = new RouteMatch($route_name, $route, [
      'node' => $node,
    ]);

    $parameters = $route_match->getParameters();
    $host_entity = $this->registrationManager->getEntityFromParameters($parameters, TRUE);
    $settings = $host_entity->getSettings();
    $settings->set('status', TRUE);
    $settings->save();

    $account = $this->createUser(['administer registration']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());
    $metadata = CacheableMetadata::createFromObject($access_result);
    $this->assertContains('node:1', $metadata->getCacheTags());
    $this->assertContains('config:registration.type.conference', $metadata->getCacheTags());
    $this->assertContains('config:workflows.workflow.registration', $metadata->getCacheTags());
    // Registration is open so registration configuration is not included in
    // cacheability.
    $this->assertNotContains('config:registration.settings', $metadata->getCacheTags());
    $this->assertContains('registration_settings:1', $metadata->getCacheTags());
    $this->assertNotContains('registration_list', $metadata->getCacheTags());
    $this->assertNotContains($host_entity->getRegistrationListCacheTag(), $metadata->getCacheTags());
    $this->assertContains('user.permissions', $metadata->getCacheContexts());
    $this->assertNotContains('user', $metadata->getCacheContexts());

    // Before open.
    $settings->set('open', '2220-01-01T00:00:00');
    $settings->save();
    $this->assertTrue($host_entity->isBeforeOpen());
    $account = $this->createUser(['administer registration']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());
    $metadata = CacheableMetadata::createFromObject($access_result);
    $this->assertContains('node:1', $metadata->getCacheTags());
    $this->assertContains('config:registration.type.conference', $metadata->getCacheTags());
    $this->assertContains('config:workflows.workflow.registration', $metadata->getCacheTags());
    // This time registration configuration is included in cacheability,
    // because of the open date.
    $this->assertContains('config:registration.settings', $metadata->getCacheTags());
    $this->assertContains('registration_settings:1', $metadata->getCacheTags());
    $this->assertNotContains('registration_list', $metadata->getCacheTags());
    $this->assertNotContains($host_entity->getRegistrationListCacheTag(), $metadata->getCacheTags());
    $this->assertContains('user.permissions', $metadata->getCacheContexts());
    $this->assertNotContains('user', $metadata->getCacheContexts());
    // There is a cache max-age based on the open date.
    $this->assertNotEquals(-1, $metadata->getCacheMaxAge());

    // After close.
    $settings->set('open', NULL);
    $settings->set('close', '2020-01-01T00:00:00');
    $settings->save();
    $this->assertTrue($host_entity->isAfterClose());
    $account = $this->createUser(['administer registration']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());
    $metadata = CacheableMetadata::createFromObject($access_result);
    $this->assertContains('node:1', $metadata->getCacheTags());
    $this->assertContains('config:registration.type.conference', $metadata->getCacheTags());
    $this->assertContains('config:workflows.workflow.registration', $metadata->getCacheTags());
    // This time registration configuration is included in cacheability,
    // because of the close date.
    $this->assertContains('config:registration.settings', $metadata->getCacheTags());
    $this->assertContains('registration_settings:1', $metadata->getCacheTags());
    $this->assertNotContains('registration_list', $metadata->getCacheTags());
    $this->assertNotContains($host_entity->getRegistrationListCacheTag(), $metadata->getCacheTags());
    $this->assertContains('user.permissions', $metadata->getCacheContexts());
    $this->assertNotContains('user', $metadata->getCacheContexts());
    $this->assertEquals(-1, $metadata->getCacheMaxAge());
  }

}
