<?php

namespace Drupal\Tests\commerce_registration\Traits;

use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_product\Entity\ProductVariationInterface;

/**
 * Defines a trait for creating a test order item and saving it.
 */
trait OrderItemCreationTrait {

  /**
   * Creates an order item.
   *
   * @return \Drupal\commerce_order\Entity\OrderItemInterface
   *   The created (unsaved) order item.
   */
  protected function createOrderItem(): OrderItemInterface {
    return OrderItem::create([
      'type' => 'default',
      'title' => 'My order item',
    ]);
  }

  /**
   * Creates an order item and saves it.
   *
   * @param \Drupal\commerce_product\Entity\ProductVariationInterface $variation
   *   The product variation that is the purchased entity for the order item.
   *
   * @return \Drupal\commerce_order\Entity\OrderItemInterface
   *   The created and saved order item.
   */
  protected function createAndSaveOrderItem(ProductVariationInterface $variation): OrderItemInterface {
    $item = $this->createOrderItem();
    $item->set('purchased_entity', $variation->id());
    $item->save();
    return $item;
  }

}
