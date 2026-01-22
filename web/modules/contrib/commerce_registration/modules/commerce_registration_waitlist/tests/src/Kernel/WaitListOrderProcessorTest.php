<?php

namespace Drupal\Tests\commerce_registration_waitlist\Kernel;

use Drupal\Tests\commerce_registration\Traits\OrderCreationTrait;
use Drupal\Tests\commerce_registration\Traits\OrderItemCreationTrait;
use Drupal\Tests\commerce_registration\Traits\ProductCreationTrait;
use Drupal\Tests\commerce_registration\Traits\ProductVariationCreationTrait;
use Drupal\Tests\commerce_registration\Traits\RegistrationCreationTrait;
use Drupal\commerce_registration_waitlist\OrderProcessor\WaitListOrderProcessor;

/**
 * Tests the commerce registration wait list order processor.
 *
 * @coversDefaultClass \Drupal\commerce_registration_waitlist\OrderProcessor\WaitListOrderProcessor
 *
 * @group commerce_registration
 */
class WaitListOrderProcessorTest extends CommerceRegistrationWaitListKernelTestBase {

  use OrderCreationTrait;
  use OrderItemCreationTrait;
  use ProductCreationTrait;
  use ProductVariationCreationTrait;
  use RegistrationCreationTrait;

  /**
   * The wait list order processor.
   *
   * @var \Drupal\commerce_registration_waitlist\OrderProcessor\WaitListOrderProcessor
   */
  protected WaitListOrderProcessor $processor;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->processor = $this->container->get('commerce_registration_waitlist.waitlist_order_processor');
  }

  /**
   * @covers ::process
   */
  public function testWaitListOrderProcessor() {
    // Create an order with one item.
    $product = $this->createAndSaveProduct();
    $variation = $this->createAndSaveVariation($product);
    $item = $this->createAndSaveOrderItem($variation);
    $order = $this->createAndSaveOrder();
    $order->addItem($item);
    $order->save();
    $this->assertCount(1, $order->getItems());

    // The cart does not have any items adding to the wait list.
    $this->processor->process($order);
    $item = $order->getItems()[0];
    $this->assertNull($item->getData('commerce_registration_waitlist'));

    $registration = $this->createAndSaveRegistration($variation);
    $item->set('registration', $registration->id());
    $item->save();
    $this->processor->process($order);
    $item = $order->getItems()[0];
    $this->assertNull($item->getData('commerce_registration_waitlist'));

    // The item contains a wait listed registration.
    $registration->set('state', 'waitlist');
    $registration->save();
    $this->processor->process($order);
    $item = $order->getItems()[0];
    $this->assertTrue($item->getData('commerce_registration_waitlist'));

    $item->set('registration', NULL);
    $item->save();
    $this->processor->process($order);
    $item = $order->getItems()[0];
    $this->assertNull($item->getData('commerce_registration_waitlist'));

    $handler = $this->entityTypeManager->getHandler('commerce_product_variation', 'registration_host_entity');
    $host_entity = $handler->createHostEntity($variation);
    /** @var \Drupal\registration\RegistrationSettingsStorage $storage */
    $storage = $this->entityTypeManager->getStorage('registration_settings');
    $settings = $storage->loadSettingsForHostEntity($host_entity);
    $settings->set('registration_waitlist_enable', TRUE);
    $settings->set('registration_waitlist_capacity', 10);
    $settings->save();
    $this->processor->process($order);
    $item = $order->getItems()[0];
    $this->assertNull($item->getData('commerce_registration_waitlist'));

    // Capacity is full, so the next registration will add to the wait list.
    $registration->set('state', 'complete');
    $registration->save();
    $settings->set('capacity', 1);
    $settings->save();
    $this->processor->process($order);
    $item = $order->getItems()[0];
    $this->assertTrue($item->getData('commerce_registration_waitlist'));
  }

}
