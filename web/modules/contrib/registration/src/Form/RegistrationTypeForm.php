<?php

namespace Drupal\registration\Form;

use Drupal\Core\Entity\BundleEntityFormBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\workflows\Entity\Workflow;

/**
 * Maintains new or existing registration types.
 */
class RegistrationTypeForm extends BundleEntityFormBase {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);
    $state_options = $this->getStateOptions($form_state);

    /** @var \Drupal\registration\Entity\RegistrationTypeInterface $registration_type */
    $registration_type = $this->entity;
    $form['#tree'] = TRUE;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $registration_type->label(),
      '#required' => TRUE,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $registration_type->id(),
      '#machine_name' => [
        'exists' => '\Drupal\registration\Entity\RegistrationType::load',
        'source' => ['label'],
      ],
      '#maxlength' => EntityTypeInterface::BUNDLE_MAX_LENGTH,
      '#disabled' => !$registration_type->isNew(),
    ];
    $form['workflow'] = [
      '#type' => 'select',
      '#title' => $this->t('Workflow'),
      '#required' => TRUE,
      '#options' => $this->getWorkflowOptions(),
      '#default_value' => $registration_type->getWorkflowId(),
      '#description' => $this->t('The workflow used by all registrations of this type.'),
      '#ajax' => [
        'event' => 'change',
        'callback' => '::ajaxRefresh',
        'wrapper' => 'workflow-data',
      ],
    ];
    $form['workflow_data']['default_state'] = [
      '#prefix' => '<div id="workflow-data">',
      '#type' => 'select',
      '#title' => $this->t('Default state'),
      '#required' => TRUE,
      '#options' => $state_options,
      '#description' => $this->t('The default state for new registrations of this type.'),
      '#default_value' => $registration_type->getDefaultState(),
    ];

    $form['workflow_data']['held'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Held registration settings'),
    ];
    $form['workflow_data']['held']['held_expire'] = [
      '#type' => 'number',
      '#title' => $this->t('Hold expiration hours'),
      '#min' => 0,
      '#max' => 24 * 30,
      '#required' => FALSE,
      '#description' => $this->t('The minimum number of hours a registration can remain held before it is taken out of held state and no longer counts against capacity. For no limit, use 0 (default is 1).<br><strong>Note</strong>: registrations are removed from held state by cron, so the number of hours specified is the minimum amount of time a registration will be held for; it can be held for longer depending on when the next cron run is after the minimum amount of time has elapsed.'),
      '#default_value' => $registration_type->getHeldExpirationTime(),
    ];
    $form['workflow_data']['held']['held_expire_state'] = [
      '#type' => 'select',
      '#title' => $this->t('Hold expiration state'),
      '#options' => $state_options,
      '#required' => FALSE,
      '#description' => $this->t('The state a registration will be put into when its hold expires.'),
      '#default_value' => $registration_type->getHeldExpirationState(),
      '#suffix' => '</div>',
    ];

    return $form;
  }

  /**
   * AJAX callback for the registration type form.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The part of the form to update.
   */
  public function ajaxRefresh(array $form, FormStateInterface $form_state): array {
    return $form['workflow_data'];
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function save(array $form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    /** @var \Drupal\registration\Entity\RegistrationTypeInterface $registration_type */
    $registration_type = $this->entity;
    $registration_type->setWorkflowId($values['workflow']);
    $registration_type->setDefaultState($values['workflow_data']['default_state']);
    $registration_type->setHeldExpirationTime($values['workflow_data']['held']['held_expire']);
    $registration_type->setHeldExpirationState($values['workflow_data']['held']['held_expire_state']);
    $return = $registration_type->save();

    $this->messenger()->addMessage($this->t('The registration type %label has been successfully saved.', ['%label' => $this->entity->label()]));
    $form_state->setRedirect('entity.registration_type.collection');

    return $return;
  }

  /**
   * Gets the available registration state options.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The states as an options array of labels keyed by ID.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getStateOptions(FormStateInterface $form_state): array {
    $options = [];
    if ($form_state->getValue('workflow')) {
      $workflow = $this->entityTypeManager->getStorage('workflow')->load($form_state->getValue('workflow'));
    }
    else {
      $workflow = $this->entity->getWorkflow();
    }
    return $workflow ? $workflow->getTypePlugin()->getStateOptions() : [];
  }

  /**
   * Gets the available registration workflow options.
   *
   * @return array
   *   The workflows as an options array of labels keyed by ID.
   */
  protected function getWorkflowOptions(): array {
    $options = [];
    $workflows = Workflow::loadMultipleByType('registration');
    foreach ($workflows as $id => $workflow) {
      $options[$id] = $workflow->label();
    }
    return $options;
  }

}
