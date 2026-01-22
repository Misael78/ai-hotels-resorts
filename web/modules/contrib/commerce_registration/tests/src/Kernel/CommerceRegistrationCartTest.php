<?php

namespace Drupal\Tests\commerce_registration\Kernel;

use Drupal\Tests\commerce_registration\Traits\OrderCreationTrait;
use Drupal\Tests\commerce_registration\Traits\OrderItemCreationTrait;
use Drupal\Tests\commerce_registration\Traits\ProductCreationTrait;
use Drupal\Tests\commerce_registration\Traits\ProductVariationCreationTrait;
use Drupal\Tests\commerce_registration\Traits\RegistrationCreationTrait;
use Drupal\commerce_cart\CartManagerInterface;

/**
 * Tests cart operations with products enabled for registration.
 *
 * @group commerce_registration
 */
class CommerceRegistrationCartTest extends CommerceRegistrationKernelTestBase {

  use OrderCreationTrait;
  use OrderItemCreationTrait;
  use ProductCreationTrait;
  use ProductVariationCreationTrait;
  use RegistrationCreationTrait;

  /**
   * The cart manager.
   *
   * @var \Drupal\commerce_cart\CartManagerInterface
   */
  protected CartManagerInterface $cartManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $admin_user = $this->createUser();
    $this->setCurrentUser($admin_user);

    $this->cartManager = \Drupal::service('commerce_cart.cart_manager');
  }

  /**
   * Tests emptying the cart.
   */
  public function testCommerceRegistrationEmptyCart() {
    // Create a cart with one item and an attached registration.
    $product = $this->createAndSaveProduct();
    $variation = $this->createAndSaveVariation($product);
    $item = $this->createAndSaveOrderItem($variation);
    $order = $this->createAndSaveOrder();
    $order->set('cart', TRUE);
    $order->addItem($item);
    $order->save();
    $item->set('order_id', $order->id());
    $item->save();
    $this->assertCount(1, $order->getItems());

    $registration = $this->createAndSaveRegistration($variation);
    $registration->set('order_id', $order->id());
    $registration->save();
    $item->set('registration', $registration->id());
    $item->save();

    // Empty the cart. The registration should be deleted.
    $this->cartManager->emptyCart($order);
    $registration = $this->entityTypeManager->getStorage('registration')->load($registration->id());
    $this->assertNull($registration);
  }

  /**
   * Tests removing a cart item.
   */
  public function testCommerceRegistrationRemoveOrderItem() {
    // Create a cart with one item and an attached registration.
    $product = $this->createAndSaveProduct();
    $variation = $this->createAndSaveVariation($product);
    $item = $this->createAndSaveOrderItem($variation);
    $order = $this->createAndSaveOrder();
    $order->set('cart', TRUE);
    $order->addItem($item);
    $order->save();
    $item->set('order_id', $order->id());
    $item->save();
    $this->assertCount(1, $order->getItems());

    $registration = $this->createAndSaveRegistration($variation);
    $registration->set('order_id', $order->id());
    $registration->save();
    $item->set('registration', $registration->id());
    $item->save();

    // Remove the item from the cart. The registration should be deleted.
    $this->cartManager->removeOrderItem($order, $item);
    $registration = $this->entityTypeManager->getStorage('registration')->load($registration->id());
    $this->assertNull($registration);
  }

  /**
   * Tests deleting a cart item.
   */
  public function testCommerceRegistrationDeleteOrderItem() {
    // Create a cart with one item and an attached registration.
    $product = $this->createAndSaveProduct();
    $variation = $this->createAndSaveVariation($product);
    $item = $this->createAndSaveOrderItem($variation);
    $order = $this->createAndSaveOrder();
    $order->set('cart', TRUE);
    $order->addItem($item);
    $order->save();
    $item->set('order_id', $order->id());
    $item->save();
    $this->assertCount(1, $order->getItems());

    $registration = $this->createAndSaveRegistration($variation);
    $registration->set('order_id', $order->id());
    $registration->save();
    $item->set('registration', $registration->id());
    $item->save();

    // Delete the item. The registration should be deleted.
    $item->delete();
    $registration = $this->entityTypeManager->getStorage('registration')->load($registration->id());
    $this->assertNull($registration);
  }

  /**
   * Tests deleting a cart item with a complete registration.
   */
  public function testCommerceRegistrationDeleteOrderItemComplete() {
    // Create a cart with one item and an attached complete registration.
    $product = $this->createAndSaveProduct();
    $variation = $this->createAndSaveVariation($product);
    $item = $this->createAndSaveOrderItem($variation);
    $order = $this->createAndSaveOrder();
    $order->set('cart', TRUE);
    $order->addItem($item);
    $order->save();
    $item->set('order_id', $order->id());
    $item->save();
    $this->assertCount(1, $order->getItems());

    $registration = $this->createAndSaveRegistration($variation);
    $registration->set('order_id', $order->id());
    $registration->set('state', 'complete');
    $registration->save();
    $item->set('registration', $registration->id());
    $item->save();

    // Delete the item. The registration should not be deleted because it is
    // complete.
    $item->delete();
    $registration = $this->entityTypeManager->getStorage('registration')->load($registration->id());
    $this->assertNotNull($registration);
  }

  /**
   * Tests deleting an order item from a non-cart order.
   */
  public function testCommerceRegistrationDeleteOrderItemNonCart() {
    // Create an order with one item and an attached registration.
    $product = $this->createAndSaveProduct();
    $variation = $this->createAndSaveVariation($product);
    $item = $this->createAndSaveOrderItem($variation);
    $order = $this->createAndSaveOrder();
    $order->addItem($item);
    $order->save();
    $item->set('order_id', $order->id());
    $item->save();
    $this->assertCount(1, $order->getItems());

    $registration = $this->createAndSaveRegistration($variation);
    $registration->set('order_id', $order->id());
    $registration->save();
    $item->set('registration', $registration->id());
    $item->save();

    // Delete the item. The registration should not be deleted because the
    // order does not represent a cart.
    $item->delete();
    $registration = $this->entityTypeManager->getStorage('registration')->load($registration->id());
    $this->assertNotNull($registration);
  }

  /**
   * Tests deleting a cart order with a pending registration.
   */
  public function testCommerceRegistrationDeleteOrderCartPendingRegistration() {
    // Create an order with one item and an attached registration.
    $product = $this->createAndSaveProduct();
    $variation = $this->createAndSaveVariation($product);
    $item = $this->createAndSaveOrderItem($variation);
    $order = $this->createAndSaveOrder();
    $order->addItem($item);
    $order->set('cart', TRUE);
    $order->save();
    $item->set('order_id', $order->id());
    $item->save();
    $this->assertCount(1, $order->getItems());

    $registration = $this->createAndSaveRegistration($variation);
    $registration->set('order_id', $order->id());
    $registration->save();
    $item->set('registration', $registration->id());
    $item->save();

    // Delete the order. The pending registration should be deleted as the
    // order was deleted.
    $order->delete();
    $registration = $this->entityTypeManager->getStorage('registration')->load($registration->id());
    $this->assertNull($registration);
  }

  /**
   * Tests deleting a cart order with a complete registration.
   */
  public function testCommerceRegistrationDeleteOrderCartCompleteRegistration() {
    // Create an order with one item and an attached registration.
    $product = $this->createAndSaveProduct();
    $variation = $this->createAndSaveVariation($product);
    $item = $this->createAndSaveOrderItem($variation);
    $order = $this->createAndSaveOrder();
    $order->addItem($item);
    $order->set('cart', TRUE);
    $order->save();
    $item->set('order_id', $order->id());
    $item->save();
    $this->assertCount(1, $order->getItems());

    $registration = $this->createAndSaveRegistration($variation);
    $registration->set('order_id', $order->id());
    $registration->set('state', 'complete');
    $registration->save();
    $item->set('registration', $registration->id());
    $item->save();

    // Delete the order. The registration should not be deleted as the
    // registration is complete.
    $order->delete();
    $registration = $this->entityTypeManager->getStorage('registration')->load($registration->id());
    $this->assertNotNull($registration);
  }

  /**
   * Tests deleting a non-cart order with a pending registration.
   */
  public function testCommerceRegistrationDeleteOrderNonCartPendingRegistration() {
    // Create an order with one item and an attached registration.
    $product = $this->createAndSaveProduct();
    $variation = $this->createAndSaveVariation($product);
    $item = $this->createAndSaveOrderItem($variation);
    $order = $this->createAndSaveOrder();
    $order->addItem($item);
    $order->set('cart', FALSE);
    $order->save();
    $item->set('order_id', $order->id());
    $item->save();
    $this->assertCount(1, $order->getItems());

    $registration = $this->createAndSaveRegistration($variation);
    $registration->set('order_id', $order->id());
    $registration->save();
    $item->set('registration', $registration->id());
    $item->save();

    // Delete the order. The pending registration should be deleted
    // (as the order, even though it was not a cart, was deleted).
    $order->delete();
    $registration = $this->entityTypeManager->getStorage('registration')->load($registration->id());
    $this->assertNull($registration);
  }

  /**
   * Tests deleting a non-cart order with a complete registration.
   */
  public function testCommerceRegistrationDeleteOrderNonCartCompleteRegistration() {
    // Create an order with one item and an attached registration.
    $product = $this->createAndSaveProduct();
    $variation = $this->createAndSaveVariation($product);
    $item = $this->createAndSaveOrderItem($variation);
    $order = $this->createAndSaveOrder();
    $order->addItem($item);
    $order->set('cart', FALSE);
    $order->save();
    $item->set('order_id', $order->id());
    $item->save();
    $this->assertCount(1, $order->getItems());

    $registration = $this->createAndSaveRegistration($variation);
    $registration->set('order_id', $order->id());
    $registration->set('state', 'complete');
    $registration->save();
    $item->set('registration', $registration->id());
    $item->save();

    // Delete the order. The registration should not be deleted as the
    // registration is complete.
    $order->delete();
    $registration = $this->entityTypeManager->getStorage('registration')->load($registration->id());
    $this->assertNotNull($registration);
  }

}
