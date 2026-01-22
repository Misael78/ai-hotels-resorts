<?php

namespace Drupal\commerce_registration\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_registration\CommerceRegistrationManagerInterface;
use Drupal\views\Plugin\views\filter\InOperator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a filter for product variation.
 *
 * Allow selection from a list of registration enabled product variations for a
 * given product. The product must be passed as the first argument to the view
 * using this filter.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("commerce_registration_product_variation")
 */
class ProductVariation extends InOperator {

  /**
   * The commerce registration manager.
   *
   * @var \Drupal\commerce_registration\CommerceRegistrationManagerInterface
   */
  protected CommerceRegistrationManagerInterface $commerceRegistrationManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): ProductVariation {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->commerceRegistrationManager = $container->get('commerce_registration.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    if (!empty($this->value) && !empty($this->value[0])) {
      $this->ensureMyTable();

      $this->query->addWhere($this->options['group'], "$this->tableAlias.entity_id", array_values($this->value), $this->operator);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildExposedForm(&$form, FormStateInterface $form_state) {
    parent::buildExposedForm($form, $form_state);
    if (!empty($this->options['expose']['identifier'])) {
      $identifier = $this->options['expose']['identifier'];
      $form[$identifier]['#title'] = $this->options['expose']['label'];
    }
  }

  /**
   * Provide the basic form which calls through to subforms.
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    // This filter should always be exposed.
    $form['expose_button']['checkbox']['checkbox'] = [
      '#type' => 'hidden',
      '#value' => TRUE,
    ];

    // Only expose options that make sense for a dynamically created list.
    $form['expose']['multiple']['#access'] = FALSE;
    $form['expose']['reduce']['#access'] = FALSE;
    $form['expose']['remember'] = [
      '#type' => 'hidden',
      '#value' => FALSE,
    ];
    $form['expose']['remember_roles'] = [
      '#type' => 'hidden',
      '#value' => [],
    ];
    $form['expose']['use_operator'] = [
      '#type' => 'hidden',
      '#value' => FALSE,
    ];

    // Add an option which defaults the filter to the first registration
    // enabled product variation for the given product.
    $form['default_to_first_variation'] = [
      '#title' => $this->t('Default to the first product variation'),
      '#description' => $this->t('Enable to default to the first product variation in the list.'),
      '#type' => 'checkbox',
      '#default_value' => !empty($this->options['default_to_first_variation']),
      '#weight' => -1,
    ];
  }

  /**
   * Set the allowed operators.
   */
  public function operators() {
    $operators = [
      'in' => [
        'title' => $this->t('Is one of the selected product variations'),
        'short' => $this->t('in'),
        'short_single' => $this->t('='),
        'method' => 'opSimple',
        'values' => 1,
      ],
      'not in' => [
        'title' => $this->t('Is not one of the selected product variations'),
        'short' => $this->t('not in'),
        'short_single' => $this->t('<>'),
        'method' => 'opSimple',
        'values' => 1,
      ],
    ];
    return $operators;
  }

  /**
   * Determine if a filter can be converted into a group.
   */
  protected function canBuildGroup(): bool {
    return FALSE;
  }

  /**
   * Define the filter options.
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();
    $options['default_to_first_variation'] = ['default' => FALSE];
    return $options;
  }

  /**
   * Build the value form when the filter is exposed.
   */
  protected function valueForm(&$form, FormStateInterface $form_state) {
    // Default to a hidden blank value so configuration schema is satisfied.
    $form['value'] = [
      '#type' => 'hidden',
      '#value' => [],
    ];

    if (!empty($this->view->args)) {
      // The view argument is assumed to be a list of product variation IDs.
      // Retrieve the associated product.
      if ($product_id = $this->commerceRegistrationManager->getProductIdFromArgument($this->view->args[0])) {
        $form['value'] = [
          '#type' => 'commerce_registration_entity_select',
          '#target_type' => 'commerce_product_variation',
          '#selection_handler' => 'commerce_registration_variation',
          '#selection_settings' => [
            'product_id' => $product_id,
          ],
          '#autocomplete_threshold' => 20,
          '#autocomplete_size' => 40,
        ];
        if ($this->options['default_to_first_variation']) {
          if ($default_value = $this->getDefaultValue($form)) {
            $form['value']['#default_value'] = $default_value;
            $identifier = $this->options['expose']['identifier'];
            $user_input = $form_state->getUserInput();
            $exposed = $form_state->get('exposed');
            if ($exposed && !isset($user_input[$identifier])) {
              $user_input[$identifier] = $default_value;
              $form_state->setUserInput($user_input);
            }
          }
        }
      }
    }
  }

  /**
   * Gets the default value for the form if available.
   *
   * @param array $form
   *   The form.
   *
   * @return int|null
   *   The default value as a product variation ID, if available.
   */
  protected function getDefaultValue(array $form): ?int {
    $default_value = NULL;
    if ($product_id = $this->commerceRegistrationManager->getProductIdFromArgument($this->view->args[0])) {
      $options = [
        'product_id' => $product_id,
        'target_type' => 'commerce_product_variation',
        'handler' => 'commerce_registration_variation',
      ];
      $handler = \Drupal::service('plugin.manager.entity_reference_selection')->getInstance($options);
      $entity_ids = $this->getEntityIds($handler->getReferenceableEntities());
      if (!empty($entity_ids)) {
        $default_value = reset($entity_ids);
      }
    }
    return $default_value;
  }

  /**
   * Gets entity IDs from an array of entities returned by a selection plugin.
   *
   * @param array $entities
   *   The entities keyed first by bundle and second by entity ID.
   *
   * @return array
   *   A flattened array containing only entity IDs.
   */
  protected function getEntityIds(array $entities): array {
    $flattened_entities = [];
    foreach ($entities as $entities_per_bundle) {
      $flattened_entities = array_merge($flattened_entities, array_keys($entities_per_bundle));
    }
    return $flattened_entities;
  }

}
