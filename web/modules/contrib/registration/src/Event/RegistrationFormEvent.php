<?php

namespace Drupal\registration\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines the registration form event.
 *
 * @see \Drupal\registration\Event\RegistrationEvents
 */
class RegistrationFormEvent extends Event {

  /**
   * The registration form.
   *
   * @var array
   */
  protected array $form;

  /**
   * The form state.
   *
   * @var \Drupal\Core\Form\FormStateInterface
   */
  protected FormStateInterface $formState;

  /**
   * Constructs a new RegistrationFormEvent.
   *
   * @param array $form
   *   The registration form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function __construct(array $form, FormStateInterface $form_state) {
    $this->form = $form;
    $this->formState = $form_state;
  }

  /**
   * Gets the registration form.
   *
   * @return array
   *   The registration form.
   */
  public function getForm(): array {
    return $this->form;
  }

  /**
   * Sets the registration form.
   *
   * @param array $form
   *   The registration form.
   */
  public function setForm(array $form) {
    $this->form = $form;
  }

  /**
   * Gets the form state.
   *
   * @return \Drupal\Core\Form\FormStateInterface
   *   The form state.
   */
  public function getFormState(): FormStateInterface {
    return $this->formState;
  }

}
