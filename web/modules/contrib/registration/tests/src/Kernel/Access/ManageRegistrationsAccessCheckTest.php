<?php

namespace Drupal\Tests\registration\Kernel\Access;

use Drupal\Core\Routing\RouteMatch;
use Drupal\Tests\registration\Kernel\RegistrationKernelTestBase;
use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\registration\Access\ManageRegistrationsAccessCheck;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * Tests the 'manage registrations' access check.
 *
 * @coversDefaultClass \Drupal\registration\Access\ManageRegistrationsAccessCheck
 *
 * @group registration
 */
class ManageRegistrationsAccessCheckTest extends RegistrationKernelTestBase {

  use NodeCreationTrait;

  /**
   * The host entity.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * The access checker.
   *
   * @var \Drupal\registration\Access\ManageRegistrationsAccessCheck
   */
  protected $accessChecker;

  /**
   * {@inheritdoc}
   */
  protected bool $usesSuperUserAccessPolicy = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->node = $this->createAndSaveNode();
    $this->accessChecker = new ManageRegistrationsAccessCheck($this->container->get('registration.manager'));
  }

  /**
   * Data provider for testManageRegistrationsAccess.
   */
  public function manageRegistrationsAccessProvider(): array {
    $manage = $this->basicManageScenarios('manage');
    $manage += [
      'manage: manage conference registration' => [
        'permissions' => ['manage conference registration'],
        'host update' => FALSE,
        'route' => 'manage',
        'expected' => TRUE,
      ],
      'manage: manage own conference registration without host update' => [
        'permissions' => ['manage own conference registration'],
        'host update' => FALSE,
        'route' => 'manage',
        'expected' => FALSE,
      ],
      'manage: manage own conference registration with host update' => [
        'permissions' => ['manage own conference registration'],
        'host update' => TRUE,
        'route' => 'manage',
        'expected' => TRUE,
      ],
    ];

    $settings = $this->basicManageScenarios('settings');
    $settings += $this->specificManageScenarios('settings');

    $broadcast = $this->basicManageScenarios('broadcast');
    $broadcast += $this->specificManageScenarios('broadcast');

    return array_merge($manage, $settings, $broadcast);
  }

  /**
   * Get basic manage scenarios relevant to all manage routes.
   *
   * @param string $route_name
   *   The route name.
   *
   * @return array
   *   The scenarios.
   */
  protected function basicManageScenarios(string $route_name): array {
    $basic_scenarios = [
      'administer registration' => [
        'permissions' => ['administer registration'],
        'host update' => FALSE,
        'route' => 'manage',
        'expected' => TRUE,
      ],
      'administer conference registration' => [
        'permissions' => ['administer conference registration'],
        'host update' => FALSE,
        'route' => 'manage',
        'expected' => TRUE,
      ],
      'administer own conference registration without host update' => [
        'permissions' => ['administer own conference registration'],
        'host update' => FALSE,
        'route' => 'manage',
        'expected' => FALSE,
      ],
      'administer own conference registration with host update' => [
        'permissions' => ['administer own conference registration'],
        'host update' => TRUE,
        'route' => 'manage',
        'expected' => FALSE,
      ],
      'administer own conference registration settings without host update' => [
        'permissions' => ['administer own conference registration settings'],
        'host update' => FALSE,
        'route' => 'manage',
        'expected' => FALSE,
      ],
      'administer own conference registration settings with host update' => [
        'permissions' => ['administer own conference registration settings'],
        'host update' => TRUE,
        'route' => 'manage',
        'expected' => TRUE,
      ],
      'update conference registration' => [
        'permissions' => ['update any conference registration'],
        'host update' => TRUE,
        'route' => 'manage',
        'expected' => FALSE,
      ],
      'view conference registration' => [
        'permissions' => ['view any conference registration'],
        'host update' => TRUE,
        'route' => 'manage',
        'expected' => FALSE,
      ],
      'no permissions manage' => [
        'permissions' => [],
        'host update' => TRUE,
        'route' => 'manage',
        'expected' => FALSE,
      ],
    ];

    $route_scenarios = [];
    foreach ($basic_scenarios as $key => $scenario) {
      $new_key = $route_name . ': ' . $key;
      $scenario['route'] = $route_name;
      $route_scenarios[$new_key] = $scenario;
    }
    return $route_scenarios;
  }

  /**
   * Get specific manage scenarios relevant to a given route.
   *
   * @param string $route_name
   *   The route name.
   *
   * @return array
   *   The scenarios.
   */
  protected function specificManageScenarios(string $route_name): array {
    return [
      "$route_name: manage conference registration" => [
        'permissions' => ['manage conference registration'],
        'host update' => FALSE,
        'route' => $route_name,
        'expected' => FALSE,
      ],
      "$route_name: manage own conference registration without host update" => [
        'permissions' => ['manage own conference registration'],
        'host update' => FALSE,
        'route' => $route_name,
        'expected' => FALSE,
      ],
      "$route_name: manage own conference registration with host update" => [
        'permissions' => ['manage own conference registration'],
        'host update' => TRUE,
        'route' => $route_name,
        'expected' => FALSE,
      ],
      "$route_name: manage conference registration $route_name" => [
        'permissions' => ["manage conference registration $route_name"],
        'host update' => FALSE,
        'route' => $route_name,
        'expected' => FALSE,
      ],
      "$route_name: manage conference registration" => [
        'permissions' => ["manage conference registration", "manage conference registration $route_name"],
        'host update' => FALSE,
        'route' => $route_name,
        'expected' => TRUE,
      ],
      "$route_name: manage own conference registration without host update" => [
        'permissions' => ['manage own conference registration', "manage conference registration $route_name"],
        'host update' => FALSE,
        'route' => $route_name,
        'expected' => FALSE,
      ],
      "$route_name: manage own conference registration with host update" => [
        'permissions' => ['manage own conference registration', "manage conference registration $route_name"],
        'host update' => TRUE,
        'route' => $route_name,
        'expected' => TRUE,
      ],
    ];
  }

  /**
   * @covers ::access
   * @dataProvider manageRegistrationsAccessProvider
   */
  public function testManageRegistrationsAccess(array $permissions, bool $hostAccess, string $route, bool $expected): void {
    if ($route === 'settings') {
      $route = 'registration_settings';
    }
    $route_match = $this->createMock(RouteMatch::class);
    $route_match->method('getParameters')->willReturn(new ParameterBag(['node' => $this->node]));
    $route_match->method('getRouteName')->willReturn("entity.node.registration.$route");

    $user_permissions = $permissions;
    if ($hostAccess) {
      $user_permissions[] = 'bypass node access';
    }
    $account = $this->createUser($user_permissions);
    $access_result = $this->accessChecker->access($account, $route_match);

    $this->assertSame($expected, $access_result->isAllowed(), "Unexpected result for permissions: " . implode(', ', $permissions) . ", on $route route.");
  }

}
