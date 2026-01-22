<?php

namespace Drupal\Tests\registration\Unit\Access;

use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\registration\Access\RegistrationCollectionAccessCheck;
use Symfony\Component\Routing\Route;

/**
 * Tests the 'registration collection' access check.
 *
 * @coversDefaultClass \Drupal\registration\Access\RegistrationCollectionAccessCheck
 *
 * @group registration
 */
class RegistrationCollectionAccessCheckTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    \Drupal::unsetContainer();
    $container = new ContainerBuilder();
    \Drupal::setContainer($container);

    $cache_contexts_manager = new CacheContextsManager($container, [
      'user.permissions',
    ]);
    $container->set('cache_contexts_manager', $cache_contexts_manager);
  }

  /**
   * @covers ::access
   */
  public function testRegistrationCollectionAccessCheck() {
    $route_match = $this->createMock(RouteMatch::class);
    $route = $this->createMock(Route::class);
    $access_checker = new RegistrationCollectionAccessCheck();

    $account = $this->createMock(AccountInterface::class);
    $account
      ->expects($this->any())
      ->method('hasPermission')
      ->willReturnMap([
        ['administer registration', TRUE],
        ['access registration overview', FALSE],
      ]);
    $access_result = $access_checker->access($route, $route_match, $account);
    $this->assertTrue($access_result->isAllowed());

    $account = $this->createMock(AccountInterface::class);
    $account
      ->expects($this->any())
      ->method('hasPermission')
      ->willReturnMap([
        ['administer registration', FALSE],
        ['access registration overview', TRUE],
      ]);
    $access_result = $access_checker->access($route, $route_match, $account);
    $this->assertTrue($access_result->isAllowed());

    $account = $this->createMock(AccountInterface::class);
    $account
      ->expects($this->any())
      ->method('hasPermission')
      ->willReturnMap([
        ['administer registration', FALSE],
        ['access registration overview', FALSE],
      ]);
    $access_result = $access_checker->access($route, $route_match, $account);
    $this->assertFalse($access_result->isAllowed());
  }

}
