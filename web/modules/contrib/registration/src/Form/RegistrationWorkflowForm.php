<?php

namespace Drupal\registration\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\workflows\Plugin\WorkflowTypeConfigureFormBase;
use Drupal\workflows\State;

/**
 * The registration WorkflowType configuration form.
 *
 * @see \Drupal\registration\Plugin\WorkflowType\Registration
 */
class RegistrationWorkflowForm extends WorkflowTypeConfigureFormBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $workflow_type_configuration = $this->workflowType->getConfiguration();
    $form['workflow_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Workflow Settings'),
      '#open' => TRUE,
    ];
    $options = array_map([State::class, 'labelCallback'], $this->workflowType->getStates());
    $form['workflow_settings']['default_registration_state'] = [
      '#title' => $this->t('Default registration state'),
      '#type' => 'select',
      '#required' => TRUE,
      '#options' => $options,
      '#description' => $this->t('Select the default state for new registration types.'),
      '#default_value' => $workflow_type_configuration['default_registration_state'] ?? 'pending',
    ];
    $options = array_merge(['' => $this->t('- None -')], $options);
    $form['workflow_settings']['complete_registration_state'] = [
      '#title' => $this->t('Complete registration state'),
      '#type' => 'select',
      '#options' => $options,
      '#description' => $this->t('Select the complete state for this workflow if one exists.'),
      '#default_value' => $workflow_type_configuration['complete_registration_state'] ?? NULL,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $configuration = $this->workflowType->getConfiguration();
    $configuration['default_registration_state'] = $form_state->getValue([
      'workflow_settings',
      'default_registration_state',
    ]);
    $configuration['complete_registration_state'] = $form_state->getValue([
      'workflow_settings',
      'complete_registration_state',
    ]);
    $this->workflowType->setConfiguration($configuration);
  }

}
