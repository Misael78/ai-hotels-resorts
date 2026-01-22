<?php

namespace Drupal\commerce_registration_waitlist\Resolver;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\commerce\Context;
use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_price\Price;
use Drupal\commerce_price\Resolver\PriceResolverInterface;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\commerce_store\CurrentStoreInterface;

/**
 * Provides the wait list price resolver for variations with a waiting list.
 */
class WaitListPriceResolver implements PriceResolverInterface {

  /**
   * The current store.
   *
   * @var \Drupal\commerce_store\CurrentStoreInterface
   */
  protected CurrentStoreInterface $currentStore;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Creates a WaitListPriceResolver object.
   *
   * @param \Drupal\commerce_store\CurrentStoreInterface $current_store
   *   The current store.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(CurrentStoreInterface $current_store, EntityTypeManagerInterface $entity_type_manager) {
    $this->currentStore = $current_store;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(PurchasableEntityInterface $entity, $quantity, Context $context): ?Price {
    if ($entity instanceof ProductVariationInterface) {
      $spaces = 1;
      $registration = NULL;
      if ($order_item = $context->getData('order_item')) {
        if (!$order_item->get('registration')->isEmpty()) {
          $referenced_entities = $order_item->get('registration')->referencedEntities();
          $registration = reset($referenced_entities);
          if ($registration) {
            if ($registration->getState()->id() == 'waitlist') {
              // Get the currency from the order or current store.
              $store = $order_item->getOrder()?->getStore();
              if (is_null($store)) {
                $store = $this->currentStore->getStore();
              }
              // Wait listed registrations are free until space is available.
              return new Price('0', $store->getDefaultCurrency()->getCurrencyCode());
            }
            else {
              // Let other resolvers determine the price.
              return NULL;
            }
          }
        }
      }
      $handler = $this->entityTypeManager->getHandler('commerce_product_variation', 'registration_host_entity');
      $host_entity = $handler->createHostEntity($entity);
      if ($host_entity->isAvailableForRegistration()) {
        if ($host_entity->shouldAddToWaitList($spaces, $registration)) {
          // Wait listed registrations are free until space is available.
          return new Price('0', $this->currentStore->getStore()->getDefaultCurrency()->getCurrencyCode());
        }
      }
    }
    // Let other resolvers determine the price.
    return NULL;
  }

}
