<?php

namespace Drupal\registration\Plugin\views;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\registration\RegistrationManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a trait for retrieving a user from the contextual filters.
 */
trait UserContextualFilterTrait {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The registration manager.
   */
  protected RegistrationManagerInterface $registrationManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->registrationManager = $container->get('registration.manager');
    return $instance;
  }

  /**
   * Builds the options form.
   *
   * @param array $form
   *   An alterable, associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state): void {
    parent::buildOptionsForm($form, $form_state);
    $form['user_argument'] = [
      '#type' => 'select',
      '#title' => $this->t('Contextual filter with user ID'),
      '#options' => [
        0 => $this->t('Position 1'),
        1 => $this->t('Position 2'),
        2 => $this->t('Position 3'),
        3 => $this->t('Position 4'),
      ],
      '#default_value' => $this->options['user_argument'],
      '#description' => $this->t('The position of the user ID within the contextual filters. For example, if the path is "/user/%user/content", then the user ID is in position 1.'),
      '#weight' => -91,
    ];
    $form['registration_states'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Registration states'),
      '#options' => $this->registrationManager->getWorkflowStateOptions(),
      '#required' => TRUE,
      '#default_value' => $this->options['registration_states'],
      '#description' => $this->t('Registration states to include when determining if a user has registered. Typically, only active states should be selected.'),
      '#weight' => -90,
    ];
    $form['form_description']['#markup'] = $this->t('This plugin requires a contextual filter containing a User ID. The recommended setup when the host entities are nodes is a content listing at path "/user/%user/content", and a Global: Null contextual filter with User ID validation enabled.');
  }

  /**
   * Hides the operator form.
   *
   * @param array $form
   *   An alterable, associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function showOperatorForm(&$form, FormStateInterface $form_state) {}

  /**
   * Defines plugin option names and defaults.
   *
   * @return array
   *   The plugin options.
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();
    $options['user_argument'] = ['default' => 0];
    $options['registration_states'] = [
      'default' => [
        'pending' => 'pending',
        'complete' => 'complete',
      ],
    ];
    return $options;
  }

}
