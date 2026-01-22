<?php

namespace Drupal\Tests\registration\Unit\Access;

use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\registration\Access\UserRegistrationsAccessCheck;
use Drupal\registration\RegistrationManagerInterface;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * Tests the 'user registrations' access check.
 *
 * @coversDefaultClass \Drupal\registration\Access\UserRegistrationsAccessCheck
 *
 * @group registration
 */
class UserRegistrationsAccessCheckTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    \Drupal::unsetContainer();
    $container = new ContainerBuilder();
    \Drupal::setContainer($container);

    $cache_contexts_manager = new CacheContextsManager($container, [
      'user',
      'user.permissions',
    ]);
    $container->set('cache_contexts_manager', $cache_contexts_manager);
  }

  /**
   * @covers ::access
   */
  public function testUserRegistrationsAccessCheck() {
    // Mock the required services and objects.
    $route_match = $this->createMock(RouteMatch::class);
    $bag = new ParameterBag();
    $route_match->expects($this->any())->method('getParameters')->willReturn($bag);

    $user = $this->createMock(UserInterface::class);
    $user->expects($this->any())->method('id')->willReturn(135);
    $user->expects($this->any())->method('getCacheTags')->willReturn(['user:135']);
    $user->expects($this->any())->method('getCacheContexts')->willReturn([]);

    $registration_manager = $this->createMock(RegistrationManagerInterface::class);
    $registration_manager->expects($this->any())->method('getEntityFromParameters')->willReturn($user);
    $registration_manager->expects($this->any())->method('userHasRegistrations')->willReturn(TRUE);

    $access_checker = new UserRegistrationsAccessCheck($registration_manager);

    // Administer registration permission.
    $account = $this->createMock(AccountInterface::class);
    $account->expects($this->any())->method('id')->willReturn(298);
    $account->expects($this->any())->method('isAuthenticated')->willReturn(TRUE);
    $account
      ->expects($this->once())
      ->method('hasPermission')
      ->with('administer registration')
      ->willReturn(TRUE);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    // View "own" registration permission for wrong account.
    $account = $this->createMock(AccountInterface::class);
    $account->expects($this->any())->method('id')->willReturn(298);
    $account->expects($this->any())->method('isAuthenticated')->willReturn(TRUE);
    $account
      ->expects($this->any())
      ->method('hasPermission')
      ->willReturnMap([
        ['administer registration', FALSE],
        ['view own registration', TRUE],
      ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    // View "own" registration permission does not apply to anonymous.
    $account = $this->createMock(AccountInterface::class);
    $account->expects($this->any())->method('id')->willReturn(0);
    $account->expects($this->any())->method('isAuthenticated')->willReturn(FALSE);
    $account
      ->expects($this->any())
      ->method('hasPermission')
      ->willReturnMap([
        ['administer registration', FALSE],
        ['view own registration', TRUE],
      ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    // View "own" registration permission.
    $account = $this->createMock(AccountInterface::class);
    $account->expects($this->any())->method('id')->willReturn(135);
    $account->expects($this->any())->method('isAuthenticated')->willReturn(TRUE);
    $account
      ->expects($this->any())
      ->method('hasPermission')
      ->willReturnMap([
        ['administer registration', FALSE],
        ['view own registration', TRUE],
      ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    // Insufficient permission.
    $account = $this->createMock(AccountInterface::class);
    $account->expects($this->any())->method('id')->willReturn(135);
    $account->expects($this->any())->method('isAuthenticated')->willReturn(TRUE);
    $account
      ->expects($this->any())
      ->method('hasPermission')
      ->willReturnMap([
        ['administer registration', FALSE],
        ['view own registration', FALSE],
      ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());
  }

}
