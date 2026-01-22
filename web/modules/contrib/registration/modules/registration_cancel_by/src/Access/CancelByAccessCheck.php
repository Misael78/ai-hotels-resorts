<?php

namespace Drupal\registration_cancel_by\Access;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Session\AccountInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\registration\Entity\RegistrationSettings;

/**
 * Checks access for the state transition route.
 */
class CancelByAccessCheck implements AccessInterface {

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected TimeInterface $time;

  /**
   * CancelByAccessCheck constructor.
   *
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(TimeInterface $time) {
    $this->time = $time;
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
    $access_result = AccessResult::neutral();

    $parameters = $route_match->getParameters();
    if ($parameters->has('registration') && $parameters->has('transition')) {
      /** @var \Drupal\registration\Entity\RegistrationInterface $registration */
      $registration = $parameters->get('registration');
      $transition = $parameters->get('transition');
      $workflow = $registration->getWorkflow();

      // Retrieving a transition could throw an exception, so must use a try
      // catch block here.
      try {
        $transition = $workflow
          ->getTypePlugin()
          ->getTransition($transition);

        if ($transition->to()->isCanceled()) {
          // Check the cancel by date.
          $registration_settings = $registration->getHostEntity()->getSettings();
          $cancel_allowed = $this->isCancelAllowed($registration_settings);
          $access_result = AccessResult::allowedIf($cancel_allowed)
            ->addCacheableDependency($registration_settings);

          // Check the bypass permission if cancel not allowed.
          if (!$cancel_allowed) {
            $bypass_result = AccessResult::allowedIfHasPermission($account, 'bypass cancel by access');
            $access_result = $access_result->orIf($bypass_result);
          }

          // Set a cache expiration if applicable.
          if ($cancel_allowed) {
            if ($max_age = $this->calculateMaxAge($registration_settings)) {
              $access_result->setCacheMaxAge($max_age);
            }
          }
        }
        else {
          // This is not a cancel transition, so simply allow it.
          // Other route requirements will still be enforced.
          $access_result = AccessResult::allowed();
        }
      }

      // Handle an invalid transition name.
      catch (\Exception) {
        $access_result = AccessResult::forbidden("The transition does not exist in the registration workflow.")
          // Recalculate this result if the relevant entities are updated.
          ->cachePerPermissions()
          ->addCacheableDependency($workflow)
          ->addCacheableDependency($registration);
      }
    }

    return $access_result;
  }

  /**
   * Calculates a max-age based on the settings "cancel by" date.
   *
   * @param \Drupal\registration\Entity\RegistrationSettings $registration_settings
   *   The registration settings.
   *
   * @return int|null
   *   The calculated max age, if available.
   */
  protected function calculateMaxAge(RegistrationSettings $registration_settings): ?int {
    $expiration = NULL;

    if ($cancel_by = $registration_settings->getSetting('cancel_by')) {
      $storage_timezone = new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE);
      $expiration = DrupalDateTime::createFromFormat(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $cancel_by, $storage_timezone);
    }

    // If a "cancel by" date in the future was found, calculate the amount
    // of time before that date, and use that as the max age.
    if ($expiration) {
      return $expiration->getTimestamp() - $this->time->getCurrentTime();
    }

    return NULL;
  }

  /**
   * Determines if cancel is allowed based on the settings "cancel by" date.
   *
   * Cancel is allowed if there is no cancel by date, or the cancel by date is
   * still in the future.
   *
   * @param \Drupal\registration\Entity\RegistrationSettings $registration_settings
   *   The registration settings.
   *
   * @return bool
   *   TRUE if cancel is allowed, FALSE otherwise.
   */
  protected function isCancelAllowed(RegistrationSettings $registration_settings): bool {
    if ($cancel_by = $registration_settings->getSetting('cancel_by')) {
      $storage_timezone = new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE);
      $cancel_by = DrupalDateTime::createFromFormat(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $cancel_by, $storage_timezone);
      $now = new DrupalDateTime('now', $storage_timezone);
      return ($now < $cancel_by);
    }
    return TRUE;
  }

}
