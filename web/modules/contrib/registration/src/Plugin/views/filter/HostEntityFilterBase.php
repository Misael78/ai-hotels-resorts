<?php

namespace Drupal\registration\Plugin\views\filter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\registration\RegistrationManagerInterface;
use Drupal\views\Plugin\views\filter\NumericFilter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a base class for host entity filters.
 */
abstract class HostEntityFilterBase extends NumericFilter {

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
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->registrationManager = $container->get('registration.manager');
    return $instance;
  }

  /**
   * Builds the options form.
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state): void {
    $form['registration_states'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Registration states'),
      '#options' => $this->registrationManager->getWorkflowStateOptions(),
      '#required' => TRUE,
      '#default_value' => $this->options['registration_states'],
      '#description' => $this->t('Registration states to include when determining the number of reserved spaces. Typically, only active states should be selected.'),
    ];
    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * Defines plugin option names and defaults.
   *
   * @return array
   *   The plugin options.
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();
    $options['registration_states'] = [
      'default' => [
        'pending' => 'pending',
        'complete' => 'complete',
      ],
    ];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function operators(): array {
    $operators = parent::operators();
    unset($operators['regular_expression']);
    unset($operators['not_regular_expression']);
    return $operators;
  }

  /**
   * Filters by "between" or "not between" operator.
   *
   * @param object $field
   *   The views field.
   */
  protected function opBetween($field): void {
    if ($entity_type = $this->view->getBaseEntityType()) {
      $this->ensureMyTable();

      $expression = NULL;
      $args = [];
      $operator = '';
      $field = $this->getMainSelect();

      if (is_numeric($this->value['min']) && is_numeric($this->value['max'])) {
        $operator = $this->operator == 'between' ? 'BETWEEN' : 'NOT BETWEEN';
        $args = [
          ':min' => $this->value['min'],
          ':max' => $this->value['max'],
        ];
        $expression = "$field $operator :min AND :max";
      }
      elseif (is_numeric($this->value['min'])) {
        $operator = $this->operator == 'between' ? '>=' : '<';
        $args = [
          ':min' => $this->value['min'],
        ];
        $expression = "$expression $operator :min";
      }
      elseif (is_numeric($this->value['max'])) {
        $operator = $this->operator == 'between' ? '<=' : '>';
        $args = [
          ':max' => $this->value['max'],
        ];
        $expression = "$expression $operator :max";
      }

      if ($expression) {
        $args += [
          ':entity_type_id' => $entity_type->id(),
          ':states[]' => array_filter($this->options['registration_states']),
        ];
        $this->query->addWhereExpression($this->options['group'], $expression, $args);
      }
    }
  }

  /**
   * Filters by simple operator.
   *
   * @param object $field
   *   The views field.
   */
  protected function opSimple($field): void {
    if ($entity_type = $this->view->getBaseEntityType()) {
      $this->ensureMyTable();

      $field = $this->getMainSelect();
      $args = [
        ':entity_type_id' => $entity_type->id(),
        ':states[]' => array_filter($this->options['registration_states']),
        ':value' => $this->value['value'],
      ];
      $expression = "$field $this->operator :value";
      $this->query->addWhereExpression($this->options['group'], $expression, $args);
    }
  }

  /**
   * Gets the SQL SELECT for the main field.
   *
   * This method should be overridden in extending classes.
   *
   * @return string
   *   The SELECT statement.
   */
  protected function getMainSelect(): string {
    return '';
  }

}
