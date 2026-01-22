<?php

namespace Drupal\commerce_registration_waitlist\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_product\Plugin\Field\FieldWidget\ProductVariationTitleWidget;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of "commerce_registration_waitlist_variation_title".
 *
 * @FieldWidget(
 *   id = "commerce_registration_waitlist_variation_title",
 *   label = @Translation("Product variation title (waiting list)"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class WaitListVariationTitleWidget extends ProductVariationTitleWidget {

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
  public static function defaultSettings() {
    return [
      'show_spaces_available' => TRUE,
      'show_waitlist_spaces_available' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);
    $element['show_spaces_available'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show spaces available when there is room within standard capacity'),
      '#default_value' => $this->getSetting('show_spaces_available'),
    ];
    $element['show_waitlist_spaces_available'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show spaces available on the wait list when capacity is reached'),
      '#default_value' => $this->getSetting('show_waitlist_spaces_available'),
      '#description' => $this->t('This setting is ignored unless the wait list is enabled, with a capacity limit, for a given product variation.'),
    ];
    $element['show_spaces_available_message'] = [
      '#type' => 'item',
      '#description' => $this->t('If neither of the show spaces options are enabled, the standard variation title is displayed, and "(waiting list)" is appended if capacity is reached and the wait list is enabled.'),
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    if ($this->getSetting('show_spaces_available')) {
      $summary[] = $this->t("Show spaces available when there is room within standard capacity");
    }
    if ($this->getSetting('show_waitlist_spaces_available')) {
      $summary[] = $this->t("Show spaces available on the wait list when capacity is reached");
    }
    return $summary;
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
        // Default to the default title option.
        $variation_options[$variation->id()] = $element['variation']['#options'][$variation->id()];

        // See if an override is needed.
        $host_entity = $handler->createHostEntity($variation);
        if ($host_entity->isConfiguredForRegistration()) {
          $capacity = $host_entity->getSettings()->getSetting('capacity');
          if ($capacity) {
            $spaces = $host_entity->getSpacesRemaining();
            if ($spaces && $this->getSetting('show_spaces_available')) {
              $variation_options[$variation->id()] = $this->formatPlural($spaces,
                '@label (1 space available)',
                '@label (@count spaces available)', [
                  '@label' => $host_entity->label(),
                ]);
            }
            elseif ($host_entity->shouldAddToWaitList()) {
              if ($this->getSetting('show_waitlist_spaces_available') && ($spaces = $host_entity->getWaitListSpacesRemaining())) {
                $variation_options[$variation->id()] = $this->formatPlural($spaces,
                  '@label (1 space available on the waiting list)',
                  '@label (@count spaces available on the waiting list)', [
                    '@label' => $host_entity->label(),
                  ]);
              }
              else {
                $variation_options[$variation->id()] = $this->t('@label (waiting list)', [
                  '@label' => $host_entity->label(),
                ]);
              }
            }
          }
          elseif ($this->getSetting('show_spaces_available')) {
            // Unlimited.
            $variation_options[$variation->id()] = $this->t('@label (unlimited space available)', [
              '@label' => $host_entity->label(),
            ]);
          }
        }
      }
      $element['variation']['#options'] = $variation_options;
    }
    return $element;
  }

}
