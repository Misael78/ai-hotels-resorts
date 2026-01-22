<?php

namespace Drupal\registration;

use Drupal\workflows\StateInterface;
use Drupal\workflows\TransitionInterface;

/**
 * A value object representing a workflow state for a registration.
 */
class RegistrationState implements StateInterface {

  /**
   * The vanilla state object from the Workflow module.
   *
   * @var \Drupal\workflows\StateInterface
   */
  protected StateInterface $state;

  /**
   * The description of the state.
   *
   * @var string
   */
  protected string $description;

  /**
   * If registrations are considered active if in this state.
   *
   * @var bool
   */
  protected bool $active;

  /**
   * If registrations should be considered canceled if in this state.
   *
   * @var bool
   */
  protected bool $canceled;

  /**
   * If registrations should be held if in this state.
   *
   * @var bool
   */
  protected bool $held;

  /**
   * If this state should be displayed on the registration form.
   *
   * @var bool
   */
  protected bool $showOnForm;

  /**
   * RegistrationState constructor.
   *
   * Decorates state objects to add registration related methods,
   *
   * @param \Drupal\workflows\StateInterface $state
   *   The vanilla state object from the Workflow module.
   * @param string $description
   *   The registration state description.
   * @param bool $active
   *   TRUE if registrations should be considered active in this state,
   *   FALSE otherwise.
   * @param bool $canceled
   *   TRUE if registrations should be considered canceled in this state,
   *   FALSE otherwise.
   * @param bool $held
   *   TRUE if registrations in this state should be held, FALSE otherwise.
   * @param bool $show_on_form
   *   TRUE if this state should be displayed on the registration form,
   *   FALSE otherwise.
   */
  public function __construct(StateInterface $state, string $description = '', bool $active = FALSE, bool $canceled = FALSE, bool $held = FALSE, bool $show_on_form = FALSE) {
    $this->state = $state;
    $this->description = $description;
    $this->active = $active;
    $this->canceled = $canceled;
    $this->held = $held;
    $this->showOnForm = $show_on_form;
  }

  /**
   * Gets the state description.
   *
   * @return string
   *   The description.
   */
  public function getDescription(): string {
    return $this->description;
  }

  /**
   * Determines if registrations should be considered active in this state.
   *
   * @return bool
   *   TRUE if active, FALSE otherwise.
   */
  public function isActive(): bool {
    return $this->active;
  }

  /**
   * Determines if registrations should be considered canceled in this state.
   *
   * @return bool
   *   TRUE if canceled, FALSE otherwise.
   */
  public function isCanceled(): bool {
    return $this->canceled;
  }

  /**
   * Determines if registrations in this state should be held.
   *
   * @return bool
   *   TRUE if held, FALSE otherwise.
   */
  public function isHeld(): bool {
    return $this->held;
  }

  /**
   * Determines if this state should be displayed on the registration form.
   *
   * @return bool
   *   TRUE if displayed on the form, FALSE otherwise.
   */
  public function isShownOnForm(): bool {
    return $this->showOnForm;
  }

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return $this->state->id();
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    return $this->state->label();
  }

  /**
   * {@inheritdoc}
   */
  public function weight(): int {
    return $this->state->weight();
  }

  /**
   * {@inheritdoc}
   */
  public function canTransitionTo($to_state_id): bool {
    return $this->state->canTransitionTo($to_state_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getTransitionTo($to_state_id): TransitionInterface {
    return $this->state->getTransitionTo($to_state_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getTransitions(): array {
    return $this->state->getTransitions();
  }

}
