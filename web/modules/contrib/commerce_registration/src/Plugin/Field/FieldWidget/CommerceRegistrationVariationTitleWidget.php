<?php

namespace Drupal\commerce_registration\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_product\Plugin\Field\FieldWidget\ProductVariationTitleWidget;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'commerce_registration_variation_title' widget.
 *
 * @FieldWidget(
 *   id = "commerce_registration_variation_title",
 *   label = @Translation("Product variation title (spaces available)"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class CommerceRegistrationVariationTitleWidget extends ProductVariationTitleWidget {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    // If the element is a select, replace the options array with values
    // containing both the variation title and the number of available spaces
    // still available for registration.
    if ($element['variation']['#type'] == 'select') {
      $variation_options = [];
      $product = $form_state->get('product');
      $handler = $this->entityTypeManager->getHandler('commerce_product_variation', 'registration_host_entity');
      $variations = $this->loadEnabledVariations($product);
      foreach ($variations as $variation) {
        $host_entity = $handler->createHostEntity($variation);
        if ($host_entity->isConfiguredForRegistration()) {
          $capacity = $host_entity->getSettings()->getSetting('capacity');
          if ($capacity) {
            $usage = $host_entity->getActiveSpacesReserved();
            $spaces = $capacity - $usage;
            $variation_options[$variation->id()] = $this->formatPlural($spaces,
              '@label (1 space available)',
              '@label (@count spaces available)', [
                '@label' => $host_entity->label(),
              ]);
          }
          else {
            // Unlimited.
            $variation_options[$variation->id()] = $this->t('@label (unlimited space available)', [
              '@label' => $host_entity->label(),
            ]);
          }
        }
        else {
          // Not configured for registration, use the default title option.
          $variation_options[$variation->id()] = $element['variation']['#options'][$variation->id()];
        }
      }
      $element['variation']['#options'] = $variation_options;
    }
    return $element;
  }

}
