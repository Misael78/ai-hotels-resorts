<?php

namespace Drupal\commerce_registration\EventSubscriber;

use Drupal\commerce_product\Event\FilterVariationsEvent;
use Drupal\commerce_product\Event\ProductEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides a product event subscriber.
 */
class ProductEventSubscriber implements EventSubscriberInterface {

  /**
   * Processes filtering of variations.
   *
   * Ensures only variations enabled for registration are listed in add to cart
   * forms, for variations with a registration field set.
   *
   * @param \Drupal\commerce_product\Event\FilterVariationsEvent $event
   *   The filter variations event.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function onFilterVariations(FilterVariationsEvent $event) {
    $handler = \Drupal::entityTypeManager()->getHandler('commerce_product_variation', 'registration_host_entity');

    $variations = $event->getVariations();
    $enabled_variations = [];
    /** @var \Drupal\commerce_product\Entity\ProductVariationInterface $variation */
    foreach ($variations as $key => $variation) {
      $host_entity = $handler->createHostEntity($variation);
      if ($host_entity->isConfiguredForRegistration()) {
        // For variations configured for registration, only include them if
        // they can currently take new registrations.
        if ($host_entity->isAvailableForRegistration()) {
          $enabled_variations[$key] = $variation;
        }
      }
      else {
        // Not configured for registration, no additional check needed.
        $enabled_variations[$key] = $variation;
      }
    }
    $event->setVariations($enabled_variations);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ProductEvents::FILTER_VARIATIONS => 'onFilterVariations',
    ];
  }

}
