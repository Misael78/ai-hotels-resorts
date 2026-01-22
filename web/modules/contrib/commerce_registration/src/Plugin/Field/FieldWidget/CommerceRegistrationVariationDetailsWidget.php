<?php

namespace Drupal\commerce_registration\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityViewBuilderInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\commerce_product\Plugin\Field\FieldWidget\ProductVariationTitleWidget;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of 'commerce_registration_variation_details' widget.
 *
 * @FieldWidget(
 *   id = "commerce_registration_variation_details",
 *   label = @Translation("Product variation title with details"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class CommerceRegistrationVariationDetailsWidget extends ProductVariationTitleWidget {

  /**
   * The entity view builder.
   *
   * @var \Drupal\Core\Entity\EntityViewBuilderInterface
   */
  protected EntityViewBuilderInterface $viewBuilder;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->viewBuilder = $container->get('entity_type.manager')->getViewBuilder('commerce_product_variation');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    /** @var \Drupal\commerce_product\Entity\ProductInterface $product */
    $product = $form_state->get('product');
    $variations = $this->loadEnabledVariations($product);
    if (count($variations) === 0) {
      // Nothing to purchase, tell the parent form to hide itself.
      $form_state->set('hide_form', TRUE);
      $element['variation'] = [
        '#type' => 'value',
        '#value' => 0,
      ];
      return $element;
    }
    elseif (count($variations) === 1 && $this->getSetting('hide_single')) {
      /** @var \Drupal\commerce_product\Entity\ProductVariationInterface $selected_variation */
      $selected_variation = reset($variations);
      $element['variation'] = [
        '#type' => 'value',
        '#value' => $selected_variation->id(),
      ];
      return $element;
    }

    // Build the variation options form.
    $wrapper_id = Html::getUniqueId('commerce-product-add-to-cart-form');
    $form += [
      '#wrapper_id' => $wrapper_id,
      '#prefix' => '<div id="' . $wrapper_id . '">',
      '#suffix' => '</div>',
      '#attached' => [
        'library' => [
          'commerce_product/update_product_url',
        ],
      ],
    ];
    // If an operation caused the form to rebuild, select the variation from
    // the user's current input.
    $selected_variation = NULL;
    if ($form_state->isRebuilding()) {
      $parents = array_merge($element['#field_parents'], [
        $items->getName(),
        $delta,
      ]);
      $user_input = (array) NestedArray::getValue($form_state->getUserInput(), $parents);
      $selected_variation = $this->selectVariationFromUserInput($variations, $user_input);
    }
    // Otherwise, fallback to the default.
    if (!$selected_variation) {
      $selected_variation = $this->getDefaultVariation($product, $variations);
    }

    // Set the selected variation in the form state for our AJAX callback.
    $form_state->set('selected_variation', $selected_variation->id());

    $variation_options = [];
    foreach ($variations as $option) {
      $variation_options[$option->id()] = $option->label();
    }
    $element['variation'] = [
      '#type' => 'select',
      '#title' => $this->getWidgetLabel($selected_variation),
      '#options' => $variation_options,
      '#required' => TRUE,
      '#access' => (count($variation_options) > 1),
      '#default_value' => $selected_variation->id(),
      '#ajax' => [
        'callback' => [get_class($this), 'ajaxRefresh'],
        'wrapper' => $form['#wrapper_id'],
        // Prevent a jump to the top of the page.
        'disable-refocus' => TRUE,
      ],
    ];
    if (!$this->getSetting('label_display')) {
      $element['variation']['#title_display'] = 'invisible';
    }
    $element['product_variation_details'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['product-variation-details'],
      ],
    ];
    $element['product_variation_details']['title'] = [
      '#type' => 'item',
      '#title' => $this->getWidgetLabel($selected_variation),
      '#markup' => '<div class="single-variation-details">' . $selected_variation->getTitle() . '</div>',
      '#access' => (count($variation_options) == 1) && $this->getSetting('label_display'),
    ];
    $element['product_variation_details']['entity'] = $this->viewBuilder->view($selected_variation, 'add_to_cart');
    return $element;
  }

  /**
   * Gets the widget label when a variation is selected.
   *
   * @param \Drupal\commerce_product\Entity\ProductVariationInterface $variation
   *   The variation. Not used but is provided for derived classes.
   *
   * @return string
   *   The label.
   */
  protected function getWidgetLabel(ProductVariationInterface $variation): string {
    return $this->getSetting('label_text');
  }

}
