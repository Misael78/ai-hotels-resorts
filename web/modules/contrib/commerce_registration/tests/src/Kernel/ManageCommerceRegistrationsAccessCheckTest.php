<?php

namespace Drupal\Tests\commerce_registration\Kernel;

use Drupal\Core\Routing\RouteMatch;
use Drupal\Tests\commerce_registration\Traits\ProductCreationTrait;
use Drupal\Tests\commerce_registration\Traits\ProductVariationCreationTrait;
use Drupal\commerce_registration\Access\ManageCommerceRegistrationsAccessCheck;
use Symfony\Component\Routing\Route;

/**
 * Tests the manage commerce registrations access checker.
 *
 * @coversDefaultClass \Drupal\commerce_registration\Access\ManageCommerceRegistrationsAccessCheck
 *
 * @group commerce_registration
 */
class ManageCommerceRegistrationsAccessCheckTest extends CommerceRegistrationKernelTestBase {

  use ProductCreationTrait;
  use ProductVariationCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $admin_user = $this->createUser();
    $this->setCurrentUser($admin_user);
  }

  /**
   * @covers ::access
   */
  public function testManageCommerceRegistrationsAccessCheck() {
    $permission_handler = $this->container->get('user.permissions');
    $registration_manager = $this->container->get('registration.manager');
    $core_access_checker = $this->container->get('registration.manage_registrations_access_checker');
    $access_checker = new ManageCommerceRegistrationsAccessCheck($core_access_checker, $this->entityTypeManager, $registration_manager);

    $product = $this->createAndSaveProduct();
    $variation = $this->createAndSaveVariation($product);

    $route = new Route('/product/{commerce_product}/registrations');
    $route
      ->addDefaults([
        '_controller' => '\Drupal\commerce_registration\Controller\CommerceRegistrationController::manageRegistrations',
        '_title_callback' => '\Drupal\commerce_registration\Controller\CommerceRegistrationController::manageRegistrationsTitle',
      ])
      ->addRequirements([
        '_manage_commerce_registrations_access_check' => 'TRUE',
      ])
      ->setOption('_admin_route', TRUE)
      ->setOption('parameters', [
        'commerce_product' => ['type' => 'entity:commerce_product'],
      ]);
    $route_name = 'entity.commerce_product.commerce_registration.manage_registrations';
    $route_match = new RouteMatch($route_name, $route, [
      'commerce_product' => $product,
    ]);

    // Administer registration permission.
    $account = $this->createUser(['administer registration']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    // Administer "type" registration permission.
    $account = $this->createUser(['administer conference registration']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    // Administer "type" registration settings permission.
    $account = $this->createUser(['administer conference registration settings']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    // Administer "own" registration settings permission.
    $account = $this->createUser([
      'administer own conference registration settings',
      'administer commerce_product',
    ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    // Insufficient permission.
    $account = $this->createUser([
      'administer own conference registration settings',
      'view any registration',
    ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    // Insufficient permission.
    $account = $this->createUser([
      'administer commerce_product',
      'view any registration',
    ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    // Insufficient permission.
    $account = $this->createUser(['access registration overview']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    // Manage permissions may not be present.
    $all_permissions = $permission_handler->getPermissions();
    if (!isset($all_permissions['manage conference registration'])) {
      return;
    }

    // Manage "type" registration permission.
    $account = $this->createUser(['manage conference registration']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    // Manage "own" registration permission.
    $account = $this->createUser([
      'manage own conference registration',
      'administer commerce_product',
    ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    // Insufficient permission.
    $account = $this->createUser([
      'manage own conference registration',
      'view any registration',
    ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());
  }

  /**
   * @covers ::access
   */
  public function testManageCommerceRegistrationsAccessCheckForCustomRoute() {
    $permission_handler = $this->container->get('user.permissions');
    $registration_manager = $this->container->get('registration.manager');
    $core_access_checker = $this->container->get('registration.manage_registrations_access_checker');
    $access_checker = new ManageCommerceRegistrationsAccessCheck($core_access_checker, $this->entityTypeManager, $registration_manager);

    $product = $this->createAndSaveProduct();
    $variation = $this->createAndSaveVariation($product);

    $route = new Route('/product/{commerce_product}/registrations/custom');
    $route
      ->addDefaults([
        '_controller' => '\Drupal\commerce_registration\Controller\CommerceRegistrationController::manageRegistrations',
        '_title_callback' => '\Drupal\commerce_registration\Controller\CommerceRegistrationController::manageRegistrationsTitle',
      ])
      ->addRequirements([
        '_manage_commerce_registrations_access_check' => 'TRUE',
      ])
      ->setOption('_admin_route', TRUE)
      ->setOption('parameters', [
        'commerce_product' => ['type' => 'entity:commerce_product'],
      ]);
    $route_name = 'entity.commerce_product.commerce_registration.custom_route';
    $route_match = new RouteMatch($route_name, $route, [
      'commerce_product' => $product,
    ]);

    // Administer registration permission.
    $account = $this->createUser(['administer registration']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    // Administer "type" registration permission.
    $account = $this->createUser(['administer conference registration']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    // Administer "type" registration settings permission.
    $account = $this->createUser(['administer conference registration settings']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    // Administer "own" registration settings permission.
    $account = $this->createUser([
      'administer own conference registration settings',
      'administer commerce_product',
    ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    // Insufficient permission.
    $account = $this->createUser([
      'administer own conference registration settings',
      'view any registration',
    ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    // Insufficient permission.
    $account = $this->createUser([
      'administer commerce_product',
      'view any registration',
    ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    // Insufficient permission.
    $account = $this->createUser(['access registration overview']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    // Manage permissions may not be present.
    $all_permissions = $permission_handler->getPermissions();
    if (!isset($all_permissions['manage conference registration'])) {
      return;
    }

    // Manage "type" registration permission.
    $account = $this->createUser(['manage conference registration']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    // Manage "own" registration permission.
    $account = $this->createUser([
      'manage own conference registration',
      'administer commerce_product',
    ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    // Insufficient permission.
    $account = $this->createUser([
      'manage own conference registration',
      'view any registration',
    ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());
  }

  /**
   * @covers ::access
   */
  public function testManageCommerceRegistrationsAccessCheckWithoutVariations() {
    $permission_handler = $this->container->get('user.permissions');
    $registration_manager = $this->container->get('registration.manager');
    $core_access_checker = $this->container->get('registration.manage_registrations_access_checker');
    $access_checker = new ManageCommerceRegistrationsAccessCheck($core_access_checker, $this->entityTypeManager, $registration_manager);

    $product = $this->createAndSaveProduct();

    $route = new Route('/product/{commerce_product}/registrations');
    $route
      ->addDefaults([
        '_controller' => '\Drupal\commerce_registration\Controller\CommerceRegistrationController::manageRegistrations',
        '_title_callback' => '\Drupal\commerce_registration\Controller\CommerceRegistrationController::manageRegistrationsTitle',
      ])
      ->addRequirements([
        '_manage_commerce_registrations_access_check' => 'TRUE',
      ])
      ->setOption('_admin_route', TRUE)
      ->setOption('parameters', [
        'commerce_product' => ['type' => 'entity:commerce_product'],
      ]);
    $route_name = 'entity.commerce_product.commerce_registration.manage_registrations';
    $route_match = new RouteMatch($route_name, $route, [
      'commerce_product' => $product,
    ]);

    // Administer registration permission fails without variations.
    $account = $this->createUser(['administer registration']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());
  }

  /**
   * @covers ::access
   */
  public function testManageCommerceRegistrationSettingsAccessCheck() {
    $permission_handler = $this->container->get('user.permissions');
    $registration_manager = $this->container->get('registration.manager');
    $core_access_checker = $this->container->get('registration.manage_registrations_access_checker');
    $access_checker = new ManageCommerceRegistrationsAccessCheck($core_access_checker, $this->entityTypeManager, $registration_manager);

    $product = $this->createAndSaveProduct();
    $variation = $this->createAndSaveVariation($product);

    $route = new Route('/product/{commerce_product}/registrations/settings');
    $route
      ->addDefaults([
        '_controller' => '\Drupal\commerce_registration\Controller\CommerceRegistrationController::registrationSettings',
        '_title_callback' => '\Drupal\commerce_registration\Controller\CommerceRegistrationController::registrationSettingsTitle',
      ])
      ->addRequirements([
        '_manage_commerce_registrations_access_check' => 'TRUE',
      ])
      ->setOption('_admin_route', TRUE)
      ->setOption('parameters', [
        'commerce_product' => ['type' => 'entity:commerce_product'],
      ]);
    $route_name = 'entity.commerce_product.commerce_registration.registration_settings';
    $route_match = new RouteMatch($route_name, $route, [
      'commerce_product' => $product,
    ]);

    // Administer registration permission.
    $account = $this->createUser(['administer registration']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    // Administer "type" registration permission.
    $account = $this->createUser(['administer conference registration']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    // Administer "type" registration settings permission.
    $account = $this->createUser(['administer conference registration settings']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    // Administer "own" registration settings permission.
    $account = $this->createUser([
      'administer own conference registration settings',
      'administer commerce_product',
    ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    // Insufficient permission.
    $account = $this->createUser([
      'administer own conference registration settings',
      'view any registration',
    ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    // Insufficient permission.
    $account = $this->createUser([
      'administer commerce_product',
      'view any registration',
    ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    // Insufficient permission.
    $account = $this->createUser(['access registration overview']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    // Manage permissions may not be present.
    $all_permissions = $permission_handler->getPermissions();
    if (!isset($all_permissions['manage conference registration'])) {
      return;
    }

    // Manage "type" registration permission.
    $account = $this->createUser(['manage conference registration']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());
    // Must be paired with settings permission.
    $account = $this->createUser([
      'manage conference registration',
      'manage conference registration settings',
    ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    // Manage "own" registration permission.
    $account = $this->createUser([
      'manage own conference registration',
      'administer commerce_product',
    ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());
    // Must be paired with settings permission.
    $account = $this->createUser([
      'manage own conference registration',
      'administer commerce_product',
      'manage conference registration settings',
    ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    // Insufficient permission.
    $account = $this->createUser([
      'manage own conference registration',
      'administer commerce_product',
      'access registration overview',
    ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());
  }

  /**
   * @covers ::access
   */
  public function testManageCommerceRegistrationBroadcastAccessCheck() {
    $permission_handler = $this->container->get('user.permissions');
    $registration_manager = $this->container->get('registration.manager');
    $core_access_checker = $this->container->get('registration.manage_registrations_access_checker');
    $access_checker = new ManageCommerceRegistrationsAccessCheck($core_access_checker, $this->entityTypeManager, $registration_manager);

    $product = $this->createAndSaveProduct();
    $variation = $this->createAndSaveVariation($product);

    $route = new Route('/product/{commerce_product}/registrations/broadcast');
    $route
      ->addDefaults([
        '_form' => '\Drupal\commerce_registration\Form\EmailProductRegistrantsForm',
        '_title_callback' => '\Drupal\commerce_registration\Controller\CommerceRegistrationController::broadcastTitle',
      ])
      ->addRequirements([
        '_manage_commerce_registrations_access_check' => 'TRUE',
      ])
      ->setOption('_admin_route', TRUE)
      ->setOption('parameters', [
        'commerce_product' => ['type' => 'entity:commerce_product'],
      ]);
    $route_name = 'entity.commerce_product.commerce_registration.broadcast';
    $route_match = new RouteMatch($route_name, $route, [
      'commerce_product' => $product,
    ]);

    // Administer registration permission.
    $account = $this->createUser(['administer registration']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    // Administer "type" registration permission.
    $account = $this->createUser(['administer conference registration']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    // Administer "type" registration settings permission.
    $account = $this->createUser(['administer conference registration settings']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    // Administer "own" registration settings permission.
    $account = $this->createUser([
      'administer own conference registration settings',
      'administer commerce_product',
    ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    // Insufficient permission.
    $account = $this->createUser([
      'administer own conference registration settings',
      'view any registration',
    ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    // Insufficient permission.
    $account = $this->createUser([
      'administer commerce_product',
      'view any registration',
    ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    // Insufficient permission.
    $account = $this->createUser(['access registration overview']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    // Manage permissions may not be present.
    $all_permissions = $permission_handler->getPermissions();
    if (!isset($all_permissions['manage conference registration'])) {
      return;
    }

    // Manage "type" registration permission.
    $account = $this->createUser(['manage conference registration']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());
    // Must be paired with broadcast permission.
    $account = $this->createUser([
      'manage conference registration',
      'manage conference registration settings',
    ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    // Manage "type" registration permission.
    $account = $this->createUser(['manage conference registration']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());
    // Must be paired with broadcast permission.
    $account = $this->createUser([
      'manage conference registration',
      'manage conference registration broadcast',
    ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    // Manage "own" registration permission.
    $account = $this->createUser([
      'manage own conference registration',
      'administer commerce_product',
    ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());
    // Must be paired with broadcast permission.
    $account = $this->createUser([
      'manage own conference registration',
      'administer commerce_product',
      'manage conference registration settings',
    ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    // Manage "own" registration permission.
    $account = $this->createUser([
      'manage own conference registration',
      'administer commerce_product',
    ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());
    // Must be paired with broadcast permission.
    $account = $this->createUser([
      'manage own conference registration',
      'administer commerce_product',
      'manage conference registration broadcast',
    ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    // Insufficient permission.
    $account = $this->createUser([
      'manage own conference registration',
      'administer commerce_product',
      'access registration overview',
    ]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());
  }

}
