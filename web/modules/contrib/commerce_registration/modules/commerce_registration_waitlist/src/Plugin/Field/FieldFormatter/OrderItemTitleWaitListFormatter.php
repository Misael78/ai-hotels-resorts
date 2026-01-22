<?php

namespace Drupal\commerce_registration_waitlist\Plugin\Field\FieldFormatter;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\StringFormatter;
use Drupal\Core\Language\LanguageInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;

/**
 * Plugin implementation of the 'order_item_title_waitlist' formatter.
 *
 * @FieldFormatter(
 *   id = "order_item_title_waitlist",
 *   label = @Translation("Order item title, with wait list support"),
 *   field_types = {
 *     "string",
 *   }
 * )
 */
class OrderItemTitleWaitListFormatter extends StringFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = parent::viewElements($items, $langcode);
    $entity = $items->getEntity();
    if ($entity instanceof OrderItemInterface) {
      $settings = NULL;
      $variation = NULL;
      if ($variation = $entity->getPurchasedEntity()) {
        $handler = $this->entityTypeManager->getHandler('commerce_product_variation', 'registration_host_entity');
        $host_entity = $handler->createHostEntity($entity);
        if ($host_entity->isConfiguredForRegistration()) {
          $settings = $host_entity->getSettings();
        }
      }
      if ($entity->getData('commerce_registration_waitlist')) {
        $elements[0] = [
          '#theme' => 'order_item_title_waitlist',
          '#title' => $entity->getTitle(),
          '#order_item' => $entity,
          '#cache' => [
            'tags' => Cache::mergeTags($entity->getCacheTags(), $variation?->getCacheTags() ?? [], $settings?->getCacheTags() ?? [], [
              'registration_list',
            ]),
            'contexts' => Cache::mergeContexts($entity->getCacheContexts(), [
              'languages:' . LanguageInterface::TYPE_INTERFACE,
              'country',
            ]),
          ],
        ];
      }
      // Not on the waiting list, but include the product variation and the
      // settings in the cache tags, so the element rebuilds if those entities
      // are updated. This handles the case of an order item starting out
      // in standard capacity but being moved to the waiting list while the
      // customer is in checkout.
      elseif (isset($elements[0])) {
        $elements[0]['#cache']['tags'] = Cache::mergeTags(
          $entity->getCacheTags(),
          $variation?->getCacheTags() ?? [],
          $settings?->getCacheTags() ?? [],
        );
      }
    }
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition): bool {
    $entity_type_id = $field_definition->getTargetEntityTypeId();
    $field_name = $field_definition->getName();
    return ($entity_type_id == 'commerce_order_item') && ($field_name == 'title');
  }

}
