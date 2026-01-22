<?php

namespace Drupal\commerce_registration\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\filter\Equality;

/**
 * Provides a select filter for products.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("commerce_registration_product_id")
 */
class Product extends Equality {

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
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();
    $this->query->addWhere($this->options['group'], "$this->tableAlias.product_id", $this->value, $this->operator);
  }

  /**
   * Build the value form when the filter is exposed.
   */
  protected function valueForm(&$form, FormStateInterface $form_state) {
    $form['value'] = [
      '#title' => $this->t('Product'),
      '#type' => 'commerce_entity_select',
      '#target_type' => 'commerce_product',
      '#autocomplete_threshold' => 20,
      '#autocomplete_size' => 40,
      '#default_value' => $this->value,
    ];

    if ($form_state->get('exposed')) {
      $identifier = $this->options['expose']['identifier'];
      $user_input = $form_state->getUserInput();
      if (!isset($user_input[$identifier])) {
        $user_input[$identifier] = $this->value;
        $form_state->setUserInput($user_input);
      }
    }
  }

}
