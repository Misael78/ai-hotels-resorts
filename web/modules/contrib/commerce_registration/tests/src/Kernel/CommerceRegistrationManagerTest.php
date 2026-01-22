<?php

namespace Drupal\Tests\commerce_registration\Kernel;

use Drupal\Tests\commerce_registration\Traits\ProductCreationTrait;
use Drupal\Tests\commerce_registration\Traits\ProductVariationCreationTrait;
use Drupal\commerce_registration\CommerceRegistrationManager;

/**
 * Tests the commerce registration manager.
 *
 * @coversDefaultClass \Drupal\commerce_registration\CommerceRegistrationManager
 *
 * @group commerce_registration
 */
class CommerceRegistrationManagerTest extends CommerceRegistrationKernelTestBase {

  use ProductCreationTrait;
  use ProductVariationCreationTrait;

  /**
   * @covers ::getProductIdFromArgument
   */
  public function testCommerceRegistrationManager() {
    $manager = new CommerceRegistrationManager($this->entityTypeManager);

    $product1 = $this->createAndSaveProduct();
    $variation1 = $this->createAndSaveVariation($product1);
    $variation2 = $this->createAndSaveVariation($product1);
    $variation3 = $this->createAndSaveVariation($product1);

    $product2 = $this->createAndSaveProduct();
    $variation4 = $this->createAndSaveVariation($product2);

    $string1 = (string) $variation1->id();
    $string2 = (string) $variation2->id();
    $string3 = (string) $variation3->id();
    $string4 = (string) $variation4->id();

    // Product 1.
    $this->assertEquals($product1->id(), $manager->getProductIdFromArgument($string1));
    $this->assertEquals($product1->id(), $manager->getProductIdFromArgument($string2));
    $this->assertEquals($product1->id(), $manager->getProductIdFromArgument($string3));
    $this->assertEquals($product1->id(), $manager->getProductIdFromArgument($string1 . '+' . $string2 . '+' . $string3));

    // Product 2.
    $this->assertEquals($product2->id(), $manager->getProductIdFromArgument($string4 . '+' . $string1 . '+' . $string2));
    $this->assertEquals($product2->id(), $manager->getProductIdFromArgument($string4 . '+999'));

    // No matching product.
    $this->assertNull($manager->getProductIdFromArgument('999'));
  }

}
