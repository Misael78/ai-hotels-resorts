<?php

namespace Drupal\Tests\commerce_registration\Traits;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderInterface;

/**
 * Defines a trait for creating a test order and saving it.
 */
trait OrderCreationTrait {

  /**
   * Creates an order.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface
   *   The created (unsaved) order.
   */
  protected function createOrder(): OrderInterface {
    return Order::create([
      'type' => 'default',
      'store_id' => 1,
      'order_number' => 'TEST_ORDER_' . $this->randomMachineName(),
    ]);
  }

  /**
   * Creates an order and saves it.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface
   *   The created and saved order.
   */
  protected function createAndSaveOrder(): OrderInterface {
    $order = $this->createOrder();
    $order->save();
    return $order;
  }

}
