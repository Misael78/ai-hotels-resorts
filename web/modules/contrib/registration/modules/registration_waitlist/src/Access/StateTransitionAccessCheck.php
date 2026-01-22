<?php

namespace Drupal\registration_waitlist\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Session\AccountInterface;
use Drupal\registration_workflow\Access\StateTransitionAccessCheck as BaseStateTransitionAccessCheck;

/**
 * Extends the access checker for the state transition route.
 */
class StateTransitionAccessCheck extends BaseStateTransitionAccessCheck {

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
    $access_result = parent::access($account, $route_match);
    if ($access_result->isAllowed()) {
      // If the transition is allowed, but the current status is waitlist, do an
      // additional check ensuring there is room within standard capacity for
      // the registration to move into. If not, return "neutral" instead, which
      // will likely result in the transition not being allowed.
      $parameters = $route_match->getParameters();
      if ($parameters->has('registration') && $parameters->has('transition')) {
        /** @var \Drupal\registration\Entity\RegistrationInterface $registration */
        $registration = $parameters->get('registration');
        if ($registration->getState()->id() == 'waitlist') {
          $transition = $parameters->get('transition');
          $transition = $registration->getWorkflow()
            ->getTypePlugin()
            ->getTransition($transition);
          // Only check if moving to an active status. Moving to a canceled
          // state does not need an additional check, for example.
          if ($transition->to()->isActive()) {
            /** @var \Drupal\registration_waitlist\HostEntityInterface $host_entity */
            $host_entity = $registration->getHostEntity();
            $enabled = $host_entity->hasRoomOffWaitList($registration->getSpacesReserved());
            $waitlist_access_result = AccessResult::allowedIf($enabled);
            return $access_result->andIf($waitlist_access_result);
          }
        }
      }
    }

    return $access_result;
  }

}
