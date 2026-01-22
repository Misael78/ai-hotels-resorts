<?php

namespace Drupal\registration\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Session\AccountInterface;
use Drupal\registration\RegistrationManagerInterface;

/**
 * Checks access for the User Registrations route.
 */
class UserRegistrationsAccessCheck implements AccessInterface {

  /**
   * The registration manager.
   *
   * @var \Drupal\registration\RegistrationManagerInterface
   */
  protected RegistrationManagerInterface $registrationManager;

  /**
   * UserRegistrationsAccessCheck constructor.
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
    /** @var \Drupal\user\UserInterface $user */
    $user = $this->registrationManager->getEntityFromParameters($route_match->getParameters());

    // If the request has a user with any registrations, then allow access if
    // the user has the appropriate permission.
    if ($user && $this->registrationManager->userHasRegistrations($user)) {
      $viewing_own = $account->isAuthenticated() && ($account->id() == $user->id());
      $access =
           $account->hasPermission("administer registration")
        // Own permission only applies to the currently logged-in user viewing
        // their own registrations.
        || ($account->hasPermission("view own registration") && $viewing_own);
      return AccessResult::allowedIf($access)
        // Every user should get their own access result.
        ->cachePerUser()
        // Recalculate this result if the relevant entities are updated.
        ->addCacheTags(['registration.user:' . $user->id()])
        ->addCacheableDependency($user);
    }

    // User not available or has no registrations.
    $access_result = AccessResult::forbidden("The user is not available or does not have any registrations.");
    if ($user) {
      // Every user should get their own access result.
      $access_result->cachePerUser();

      // Recalculate this result if registrations are added or deleted for this
      // user.
      $access_result->addCacheTags(['registration.user:' . $user->id()]);
    }
    return $access_result;
  }

}
