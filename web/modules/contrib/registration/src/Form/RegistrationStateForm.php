<?php

namespace Drupal\registration\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\workflows\Plugin\WorkflowTypeStateFormBase;
use Drupal\workflows\StateInterface;

/**
 * The registration state form.
 *
 * @see \Drupal\registration\Plugin\WorkflowType\Registration
 */
class RegistrationStateForm extends WorkflowTypeStateFormBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, ?StateInterface $state = NULL): array {
    /** @var \Drupal\registration\RegistrationState $state */
    $state = $form_state->get('state');

    $form = [];
    $form['description'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Description'),
      '#description' => $this->t('The description of the state.'),
      '#required' => TRUE,
      '#default_value' => isset($state) ? $state->getDescription() : '',
    ];
    $form['active'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Active'),
      '#description' => $this->t('Determines if registrations should be considered active when in this state.<br />This usually applies to registrations that are in progress or complete.'),
      '#default_value' => isset($state) && $state->isActive(),
    ];
    $form['canceled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Canceled'),
      '#description' => $this->t('Determines if registrations should be considered canceled when in this state.'),
      '#default_value' => isset($state) && $state->isCanceled(),
    ];
    $form['held'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Held'),
      '#description' => $this->t('Determines if registrations in this state should be held.'),
      '#default_value' => isset($state) && $state->isHeld(),
    ];
    $form['show_on_form'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show on form'),
      '#description' => $this->t('Determines if this state should be displayed on the registration form.'),
      '#default_value' => isset($state) && $state->isShownOnForm(),
    ];

    return $form;
  }

}
