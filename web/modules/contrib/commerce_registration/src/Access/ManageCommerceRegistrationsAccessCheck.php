<?php

namespace Drupal\commerce_registration\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Session\AccountInterface;
use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\registration\Access\ManageRegistrationsAccessCheck;
use Drupal\registration\RegistrationManagerInterface;

/**
 * Checks access for the Manage Commerce Registrations route.
 */
class ManageCommerceRegistrationsAccessCheck implements AccessInterface {

  /**
   * The manage registrations access checker.
   *
   * @var \Drupal\registration\Access\ManageRegistrationsAccessCheck
   */
  protected ManageRegistrationsAccessCheck $accessChecker;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The registration manager.
   *
   * @var \Drupal\registration\RegistrationManagerInterface
   */
  protected RegistrationManagerInterface $registrationManager;

  /**
   * ManageCommerceRegistrationsAccessCheck constructor.
   *
   * @param \Drupal\registration\Access\ManageRegistrationsAccessCheck $access_checker
   *   The manage registrations access checker.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\registration\RegistrationManagerInterface $registration_manager
   *   The registration manager.
   */
  public function __construct(ManageRegistrationsAccessCheck $access_checker, EntityTypeManagerInterface $entity_type_manager, RegistrationManagerInterface $registration_manager) {
    $this->accessChecker = $access_checker;
    $this->entityTypeManager = $entity_type_manager;
    $this->registrationManager = $registration_manager;
  }

  /**
   * A custom access check.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   * @param \Drupal\Core\Routing\RouteMatch $route_match
   *   Run access checks for this route.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function access(AccountInterface $account, RouteMatch $route_match): AccessResultInterface {
    // Allow access if the product in the route has at least one variation with
    // its registration type field set, and the given user account has access
    // to manage registrations for the variation.
    $product = $this->registrationManager->getEntityFromParameters($route_match->getParameters());
    if ($product instanceof ProductInterface) {
      $handler = $this->entityTypeManager->getHandler('commerce_product_variation', 'registration_host_entity');
      foreach ($product->getVariations() as $variation) {
        $host_entity = $handler->createHostEntity($variation);
        if ($host_entity->getRegistrationTypeBundle()) {
          if ($variation_route_match = $this->getVariationRouteMatch($variation, $route_match)) {
            $access_result = $this->accessChecker->access($account, $variation_route_match);
            if ($access_result->isAllowed()) {
              // Recalculate this result if the product is updated.
              $access_result->addCacheableDependency($product);
              return $access_result;
            }
          }
        }
      }
    }

    // No product available, or its registration fields are set to disable
    // registrations. Return neutral so other modules can have a say in
    // whether registration is allowed. Most likely no other module will
    // allow the registration, so this will disable the route. This would
    // in turn hide the Manage Registrations tab for the product.
    $access_result = AccessResult::neutral();

    // Recalculate this result if the relevant entities are updated.
    $access_result->cachePerPermissions();
    if ($product instanceof ProductInterface) {
      $access_result->addCacheableDependency($product);
    }
    return $access_result;
  }

  /**
   * Gets the route match for a product variation.
   *
   * @param \Drupal\commerce_product\Entity\ProductVariationInterface $variation
   *   The product variation.
   * @param \Drupal\Core\Routing\RouteMatch $route_match
   *   The product route match.
   *
   * @return \Drupal\Core\Routing\RouteMatch|null
   *   The route match for a commerce product variation instead of a product.
   */
  protected function getVariationRouteMatch(ProductVariationInterface $variation, RouteMatch $route_match): ?RouteMatch {
    $entity_type = $variation->getEntityType();

    switch ($route_match->getRouteName()) {
      case 'entity.commerce_product.commerce_registration.registration_settings':
        $route = $this->registrationManager->getRoute($entity_type, 'settings');
        $route_name = 'entity.commerce_product_variation.registration.registration_settings';
        return new RouteMatch($route_name, $route, [
          'commerce_product_variation' => $variation,
        ]);

      case 'entity.commerce_product.commerce_registration.broadcast':
        $route = $this->registrationManager->getRoute($entity_type, 'broadcast');
        $route_name = 'entity.commerce_product_variation.registration.broadcast';
        return new RouteMatch($route_name, $route, [
          'commerce_product_variation' => $variation,
        ]);

      // Default to the manage registrations route.
      default:
        $route = $this->registrationManager->getRoute($entity_type, 'manage');
        $route_name = 'entity.commerce_product_variation.registration.manage_registrations';
        return new RouteMatch($route_name, $route, [
          'commerce_product_variation' => $variation,
        ]);
    }

    return NULL;
  }

}
