<?php

namespace Drupal\registration_workflow;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\registration\Entity\RegistrationInterface;
use Drupal\workflows\StateInterface;
use Drupal\workflows\Transition;
use Drupal\workflows\WorkflowInterface;

/**
 * Validates whether a certain state transition is allowed.
 */
class StateTransitionValidation implements StateTransitionValidationInterface {

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected AccountProxy $currentUser;

  /**
   * Creates a StateTransitionValidation object.
   *
   * @param \Drupal\Core\Session\AccountProxy $current_user
   *   The current user.
   */
  public function __construct(AccountProxy $current_user) {
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public function getValidTransitions(RegistrationInterface $registration, ?AccountInterface $user = NULL): array {
    if (is_null($user)) {
      $user = $this->currentUser;
    }

    $workflow = $registration->getWorkflow();
    $current_state = $registration->getState();

    return array_filter($current_state->getTransitions(), function (Transition $transition) use ($workflow, $user) {
      return $user->hasPermission('use ' . $workflow->id() . ' ' . $transition->id() . ' transition');
    });
  }

  /**
   * {@inheritdoc}
   */
  public function isTransitionValid(WorkflowInterface $workflow, StateInterface $original_state, StateInterface $new_state, RegistrationInterface $registration, ?AccountInterface $user = NULL): bool {
    if (is_null($user)) {
      $user = $this->currentUser;
    }

    if ($workflow->getTypePlugin()->hasTransitionFromStateToState($original_state->id(), $new_state->id())) {
      $transition = $workflow->getTypePlugin()->getTransitionFromStateToState($original_state->id(), $new_state->id());
      return $user->hasPermission('use ' . $workflow->id() . ' ' . $transition->id() . ' transition');
    }
    return FALSE;
  }

}
