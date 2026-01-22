<?php

namespace Drupal\registration\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Session\AccountInterface;
use Drupal\registration\RegistrationManagerInterface;

/**
 * Checks access for the Manage Registrations route.
 */
class ManageRegistrationsAccessCheck implements AccessInterface {

  /**
   * The registration manager.
   *
   * @var \Drupal\registration\RegistrationManagerInterface
   */
  protected RegistrationManagerInterface $registrationManager;

  /**
   * ManageRegistrationsAccessCheck constructor.
   *
   * @param \Drupal\registration\RegistrationManagerInterface $registration_manager
   *   The registration manager.
   */
  public function __construct(RegistrationManagerInterface $registration_manager) {
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
   */
  public function access(AccountInterface $account, RouteMatch $route_match): AccessResultInterface {
    $host_entity = $this->registrationManager->getEntityFromParameters($route_match->getParameters(), TRUE);
    if ($host_entity && $entity = $host_entity->getEntity()) {
      $operation = 'manage registrations';
      $entity_type_id = $entity->getEntityTypeId();
      switch ($route_match->getRouteName()) {
        case "entity.{$entity_type_id}.registration.registration_settings":
          $operation = 'manage settings';
          break;

        case "entity.{$entity_type_id}.registration.broadcast":
          $operation = 'manage broadcast';
          break;

      }
      return $host_entity->access($operation, $account, TRUE);
    }
    return AccessResult::neutral();
  }

}
