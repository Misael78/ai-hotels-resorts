<?php

namespace Drupal\Tests\commerce_registration_waitlist\Kernel;

use Drupal\Tests\commerce_registration\Traits\OrderCreationTrait;
use Drupal\Tests\commerce_registration\Traits\OrderItemCreationTrait;
use Drupal\Tests\commerce_registration\Traits\ProductCreationTrait;
use Drupal\Tests\commerce_registration\Traits\ProductVariationCreationTrait;
use Drupal\Tests\commerce_registration\Traits\RegistrationCreationTrait;
use Drupal\commerce\Context;
use Drupal\commerce_price\Resolver\ChainPriceResolverInterface;

/**
 * Tests the WaitListPriceResolver class.
 *
 * @coversDefaultClass \Drupal\commerce_registration_waitlist\Resolver\WaitListPriceResolver
 *
 * @group commerce_registration
 */
class WaitListPriceResolverTest extends CommerceRegistrationWaitListKernelTestBase {

  use OrderCreationTrait;
  use OrderItemCreationTrait;
  use ProductCreationTrait;
  use ProductVariationCreationTrait;
  use RegistrationCreationTrait;

  /**
   * The chain price resolver.
   *
   * @var \Drupal\commerce_price\Resolver\ChainPriceResolverInterface
   */
  protected ChainPriceResolverInterface $chainPriceResolver;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->chainPriceResolver = $this->container->get('commerce_price.chain_price_resolver');
  }

  /**
   * @covers ::resolve
   */
  public function testWaitListPriceResolver() {
    $product = $this->createAndSaveProduct();
    $variation = $this->createAndSaveVariation($product);
    $item = $this->createAndSaveOrderItem($variation);
    $order = $this->createAndSaveOrder();
    $order->addItem($item);
    $order->save();
    $this->assertCount(1, $order->getItems());

    // Standard price.
    $time = $order->getCalculationDate()->format('U');
    $context = new Context($order->getCustomer(), $order->getStore(), $time, [
      'order_item' => $item,
    ]);
    $price = $this->chainPriceResolver->resolve($variation, $item->getQuantity(), $context);
    $this->assertTrue($price->equals($variation->getPrice()));

    $registration = $this->createAndSaveRegistration($variation);
    $item->set('registration', $registration->id());
    $item->save();
    $price = $this->chainPriceResolver->resolve($variation, $item->getQuantity(), $context);
    $this->assertTrue($price->equals($variation->getPrice()));

    // Wait list price.
    $registration->set('state', 'waitlist');
    $registration->save();
    $price = $this->chainPriceResolver->resolve($variation, $item->getQuantity(), $context);
    $this->assertTrue($price->isZero());

    // Standard price.
    $item->set('registration', NULL);
    $item->save();
    $price = $this->chainPriceResolver->resolve($variation, $item->getQuantity(), $context);
    $this->assertTrue($price->equals($variation->getPrice()));

    $handler = $this->entityTypeManager->getHandler('commerce_product_variation', 'registration_host_entity');
    $host_entity = $handler->createHostEntity($variation);
    /** @var \Drupal\registration\RegistrationSettingsStorage $storage */
    $storage = $this->entityTypeManager->getStorage('registration_settings');
    $settings = $storage->loadSettingsForHostEntity($host_entity);
    $settings->set('registration_waitlist_enable', TRUE);
    $settings->set('registration_waitlist_capacity', 10);
    $settings->save();

    $price = $this->chainPriceResolver->resolve($variation, $item->getQuantity(), $context);
    $this->assertTrue($price->equals($variation->getPrice()));

    // Capacity is full, so the wait list price is returned.
    $registration->set('state', 'complete');
    $registration->save();
    $settings->set('capacity', 1);
    $settings->save();
    $price = $this->chainPriceResolver->resolve($variation, $item->getQuantity(), $context);
    $this->assertTrue($price->isZero());

    // There is room again, so the standard price is returned.
    $settings->set('capacity', 10);
    $settings->save();
    $price = $this->chainPriceResolver->resolve($variation, $item->getQuantity(), $context);
    $this->assertFalse($price->isZero());
  }

}
