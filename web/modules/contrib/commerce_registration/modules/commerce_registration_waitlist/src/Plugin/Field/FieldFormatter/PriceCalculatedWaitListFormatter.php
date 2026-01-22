<?php

namespace Drupal\commerce_registration_waitlist\Plugin\Field\FieldFormatter;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_order\Plugin\Field\FieldFormatter\PriceCalculatedFormatter;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'commerce_price_calculated_waitlist' formatter.
 *
 * Adds wait list support to the standard calculated price formatter.
 *
 * @FieldFormatter(
 *   id = "commerce_price_calculated_waitlist",
 *   label = @Translation("Calculated, with wait list support"),
 *   field_types = {
 *     "commerce_price"
 *   }
 * )
 */
class PriceCalculatedWaitListFormatter extends PriceCalculatedFormatter {

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
  public static function defaultSettings(): array {
    return [
      'waitlist_message' => [
        'value' => '',
        'format' => filter_default_format(),
      ],
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements = parent::settingsForm($form, $form_state);

    $elements['waitlist_message'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Message'),
      '#required' => TRUE,
      '#description' => $this->t('Message to display when the item will be added to the waiting list'),
      '#default_value' => $this->getSetting('waitlist_message')['value'],
      '#format' => $this->getSetting('waitlist_message')['format'],
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = parent::viewElements($items, $langcode);
    if (!empty($elements) && isset($elements[0])) {
      if ($entity = $items->getEntity()) {
        if ($entity instanceof ProductVariationInterface) {
          $handler = $this->entityTypeManager->getHandler('commerce_product_variation', 'registration_host_entity');
          $host_entity = $handler->createHostEntity($entity, $langcode);
          $validation_result = $host_entity->isAvailableForRegistration(TRUE);

          if ($validation_result->isValid() && $host_entity->shouldAddToWaitList()) {
            // Registration is full and the item will be added to the waiting
            // list. Use a special template showing the regular price and the
            // calculated price for the item, plus a configurable message.
            $price = $entity->getPrice();
            $options = $this->getFormattingOptions();
            $elements[0]['#theme'] = 'commerce_price_calculated_waitlist';
            $elements[0]['#base_price'] = $this->currencyFormatter->format($price->getNumber(), $price->getCurrencyCode(), $options);
            $elements[0]['#waitlist_message'] = [
              '#type' => 'processed_text',
              '#text' => $this->getSetting('waitlist_message')['value'],
              '#format' => $this->getSetting('waitlist_message')['format'],
            ];
          }

          // Add the cacheability of the validation result to the output.
          $metadata = CacheableMetadata::createFromRenderArray($elements);
          $metadata->addCacheableDependency($validation_result)->applyTo($elements);
        }
      }
    }

    return $elements;
  }

}
