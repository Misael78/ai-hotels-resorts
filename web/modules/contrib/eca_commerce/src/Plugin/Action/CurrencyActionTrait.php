<?php

namespace Drupal\eca_commerce\Plugin\Action;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_store\Entity\EntityStoreInterface;
use Drupal\commerce_store\Entity\StoreInterface;
use Drupal\commerce_store\Resolver\StoreResolverInterface;

/**
 * Trait for resolving the default currency to use for a price adjustment.
 */
trait CurrencyActionTrait {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The default store resolver.
   *
   * @var \Drupal\commerce_store\Resolver\StoreResolverInterface|null
   */
  protected ?StoreResolverInterface $defaultStoreResolver;

  /**
   * Gets all available currencies configured on the site.
   *
   * Ensures format is compatible with currency select form element.
   *
   * @return array
   *   Array of currency codes with both keys and values populated. For example,
   *   @code
   *   ['USD' => 'USD']
   *   @endcode
   */
  protected function getAvailableCurrencies(): array {
    $currencies = $this->entityTypeManager->getStorage('commerce_currency')->loadMultiple();
    $currency_codes = array_keys($currencies);
    return array_combine($currency_codes, $currency_codes);
  }

  /**
   * Gets the default currency to use if the action was not configured with one.
   *
   * @param mixed $entity
   *   The entity used as a reference e.g. Order, Order Item.
   *   The currency of the entity's total price is used if available.
   *   Otherwise, the default currency of the respective Store is used.
   *
   * @return string
   *   The default currency.
   */
  protected function getFallbackCurrency(mixed $entity = NULL): string {
    return $entity?->getTotalPrice()?->getCurrencyCode()
      ?? $this->getFallbackStore($entity)?->getDefaultCurrencyCode()
      ?? 'USD';
  }

  /**
   * Gets the given Order or Order Item entity's associated Store.
   *
   * Otherwise return the default Store.
   *
   * @param mixed $entity
   *   The entity used as a reference e.g. Order, Order Item.
   *
   * @return \Drupal\commerce_store\Entity\StoreInterface|null
   *   The respective Store.
   */
  protected function getFallbackStore(mixed $entity = NULL) : ?StoreInterface {
    if ($entity instanceof EntityStoreInterface) {
      $store = $entity->getStore();
    }
    elseif ($entity instanceof OrderItemInterface) {
      $store = $entity->getOrder()?->getStore();
    }
    $store ??= $this->defaultStoreResolver?->resolve();
    return $store;
  }

}
