<?php

namespace Drupal\Tests\commerce_registration\Kernel;

use Drupal\Tests\commerce_registration\Traits\OrderCreationTrait;
use Drupal\Tests\commerce_registration\Traits\OrderItemCreationTrait;
use Drupal\Tests\commerce_registration\Traits\ProductCreationTrait;
use Drupal\Tests\commerce_registration\Traits\ProductVariationCreationTrait;
use Drupal\Tests\commerce_registration\Traits\RegistrationCreationTrait;
use Drupal\commerce_registration\OrderProcessor\RegistrationOrderProcessor;

/**
 * Tests the commerce registration order processor.
 *
 * @coversDefaultClass \Drupal\commerce_registration\OrderProcessor\RegistrationOrderProcessor
 *
 * @group commerce_registration
 */
class RegistrationOrderProcessorTest extends CommerceRegistrationKernelTestBase {

  use OrderCreationTrait;
  use OrderItemCreationTrait;
  use ProductCreationTrait;
  use ProductVariationCreationTrait;
  use RegistrationCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $admin_user = $this->createUser();
    $this->setCurrentUser($admin_user);
  }

  /**
   * @covers ::process
   */
  public function testRegistrationOrderProcessor() {
    $processor = new RegistrationOrderProcessor($this->entityTypeManager);

    // Create an order with one item.
    $product = $this->createAndSaveProduct();
    $variation = $this->createAndSaveVariation($product);
    $item = $this->createAndSaveOrderItem($variation);
    $order = $this->createAndSaveOrder();
    $order->addItem($item);
    $order->save();
    $this->assertCount(1, $order->getItems());

    // The processed order still contains the item.
    $processor->process($order);
    $this->assertCount(1, $order->getItems());

    // The processed order still contains the item after registration.
    $registration = $this->createAndSaveRegistration($variation);
    $item->set('registration', $registration->id());
    $item->save();
    $processor->process($order);
    $this->assertCount(1, $order->getItems());

    // The processed order still contains the item if new registrations are
    // disabled.
    $host_entity = $registration->getHostEntity();
    $settings = $host_entity->getSettings();
    $settings->set('status', FALSE);
    $settings->save();
    $processor->process($order);
    $this->assertCount(1, $order->getItems());

    // The item is removed when the host is no longer configured for
    // registration.
    $variation->set('event_registration', NULL);
    $variation->save();
    $processor->process($order);
    $this->assertEmpty($order->getItems());

    // Create another order with one item.
    $product = $this->createAndSaveProduct();
    $variation = $this->createAndSaveVariation($product);
    $item = $this->createAndSaveOrderItem($variation);
    $order = $this->createAndSaveOrder();
    $order->addItem($item);
    $order->save();
    $this->assertCount(1, $order->getItems());

    // The processed order still contains the item.
    $processor->process($order);
    $this->assertCount(1, $order->getItems());

    // The processed order still contains the item after registration.
    $registration = $this->createAndSaveRegistration($variation);
    $item->set('registration', $registration->id());
    $item->save();
    $processor->process($order);
    $this->assertCount(1, $order->getItems());

    // The item is removed if the registration is canceled.
    $registration->set('state', 'canceled');
    $registration->save();
    $processor->process($order);
    $this->assertEmpty($order->getItems());

    // Create another order with one item.
    $product = $this->createAndSaveProduct();
    $variation = $this->createAndSaveVariation($product);
    $item = $this->createAndSaveOrderItem($variation);
    $order = $this->createAndSaveOrder();
    $order->addItem($item);
    $order->save();
    $this->assertCount(1, $order->getItems());

    // The processed order still contains the item.
    $processor->process($order);
    $this->assertCount(1, $order->getItems());

    // The processed order still contains the item after registration.
    $registration = $this->createAndSaveRegistration($variation);
    $item->set('registration', $registration->id());
    $item->save();
    $processor->process($order);
    $this->assertCount(1, $order->getItems());

    // The item is not removed if the registration is held.
    $registration->set('state', 'held');
    $registration->save();
    $processor->process($order);
    $this->assertCount(1, $order->getItems());
  }

}
