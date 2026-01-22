<?php

namespace Drupal\registration_workflow\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Session\AccountInterface;
use Drupal\registration_workflow\StateTransitionValidationInterface;
use Drupal\workflows\Transition;
use Drupal\workflows\WorkflowInterface;

/**
 * Checks access for the state transition route.
 */
class StateTransitionAccessCheck implements AccessInterface {

  /**
   * The configuration.
   */
  protected ImmutableConfig $config;

  /**
   * The state transition validator.
   */
  protected StateTransitionValidationInterface $transitionValidator;

  /**
   * StateTransitionAccessCheck constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\registration_workflow\StateTransitionValidationInterface $transition_validator
   *   The state transition validator.
   */
  public function __construct(ConfigFactoryInterface $config_factory, StateTransitionValidationInterface $transition_validator) {
    $this->config = $config_factory->get('registration_workflow.settings');
    $this->transitionValidator = $transition_validator;
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

        // Ensure there is a valid transition from the registration current
        // state to the requested new state. The transition validator also
        // checks that the account has permission to perform the transition.
        $valid = $this->transitionValidator
          ->isTransitionValid($workflow, $registration->getState(), $transition->to(), $registration, $account);

        // Require update access to the registration if this option is selected
        // in registration workflow settings.
        $require_update_access = (bool) $this->config->get('require_update_access');
        $update_access = $require_update_access ? $registration->access('update', $account, TRUE) : AccessResult::allowed();

        // Prevent completion of own registrations if this option is selected
        // in registration workflow settings.
        $own_registration = $registration->getUser() && ($account->id() == $registration->getUserId());
        if ($own_registration && $this->isCompleteTransition($valid, $workflow, $transition)) {
          $prevent_complete_own = (bool) $this->config->get('prevent_complete_own');
          $complete_own_access = $prevent_complete_own ? AccessResult::neutral() : AccessResult::allowed();
          // "Own" access depends on the current user.
          $complete_own_access->cachePerUser();
          // Administrators can always complete their own registrations.
          $complete_own_access = $complete_own_access->orIf($registration->access('administer', $account, TRUE));
        }
        else {
          $complete_own_access = AccessResult::allowed();
        }

        return AccessResult::allowedIf($valid)
          ->andIf($update_access)
          ->andIf($complete_own_access)
          // Recalculate this result if the relevant entities are updated.
          ->cachePerPermissions()
          ->addCacheableDependency($this->config)
          ->addCacheableDependency($workflow)
          ->addCacheableDependency($registration);
      }

      // Handle an invalid transition name.
      catch (\Exception) {
        return AccessResult::forbidden("The transition does not exist in the registration workflow.")
          // Recalculate this result if the relevant entities are updated.
          ->cachePerPermissions()
          ->addCacheableDependency($workflow)
          ->addCacheableDependency($registration);
      }
    }

    return AccessResult::neutral();
  }

  /**
   * Determines if a workflow transition completes a registration.
   *
   * @param bool $valid
   *   Whether the transition is valid.
   * @param \Drupal\workflows\WorkflowInterface $workflow
   *   The workflow.
   * @param \Drupal\workflows\Transition $transition
   *   The transition.
   *
   * @return bool
   *   TRUE if the transition would complete the registration, FALSE otherwise.
   */
  protected function isCompleteTransition(bool $valid, WorkflowInterface $workflow, Transition $transition) {
    if ($valid) {
      $configuration = $workflow->getTypePlugin()->getConfiguration();
      if (!empty($configuration['complete_registration_state'])) {
        $complete_state = $configuration['complete_registration_state'];
        return ($transition->to()->id() == $complete_state);
      }
    }

    // The transition is invalid or thw workflow does not have a complete state.
    return FALSE;
  }

}
