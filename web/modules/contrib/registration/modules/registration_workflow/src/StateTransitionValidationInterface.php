<?php

namespace Drupal\registration_workflow;

use Drupal\Core\Session\AccountInterface;
use Drupal\registration\Entity\RegistrationInterface;
use Drupal\workflows\StateInterface;
use Drupal\workflows\WorkflowInterface;

/**
 * Validates whether a certain state transition is allowed.
 */
interface StateTransitionValidationInterface {

  /**
   * Gets the transitions that are valid for a given user and registration.
   *
   * @param \Drupal\registration\Entity\RegistrationInterface $registration
   *   The registration to be transitioned.
   * @param \Drupal\Core\Session\AccountInterface|null $user
   *   The account that wants to perform a transition, if available.
   *   Defaults to the current user if not passed.
   *
   * @return \Drupal\workflows\Transition[]
   *   The valid transitions.
   */
  public function getValidTransitions(RegistrationInterface $registration, ?AccountInterface $user = NULL): array;

  /**
   * Checks if a transition between two states is valid for the given user.
   *
   * @param \Drupal\workflows\WorkflowInterface $workflow
   *   The workflow entity.
   * @param \Drupal\workflows\StateInterface $original_state
   *   The original workflow state.
   * @param \Drupal\workflows\StateInterface $new_state
   *   The new workflow state.
   * @param \Drupal\registration\Entity\RegistrationInterface $registration
   *   The registration to be transitioned.
   * @param \Drupal\Core\Session\AccountInterface|null $user
   *   The user to validate, if available.
   *   Defaults to the current user if not passed.
   *
   * @return bool
   *   Returns TRUE if the transition is valid, otherwise FALSE.
   */
  public function isTransitionValid(WorkflowInterface $workflow, StateInterface $original_state, StateInterface $new_state, RegistrationInterface $registration, ?AccountInterface $user = NULL): bool;

}
