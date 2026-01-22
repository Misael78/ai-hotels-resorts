<?php

namespace Drupal\Tests\commerce_registration\Traits;

use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\commerce_product\Entity\ProductVariationInterface;

/**
 * Defines a trait for creating a test product variation and saving it.
 */
trait ProductVariationCreationTrait {

  /**
   * Creates a product variation.
   *
   * @return \Drupal\commerce_product\Entity\ProductVariationInterface
   *   The created (unsaved) product variation.
   */
  protected function createVariation(): ProductVariationInterface {
    return ProductVariation::create([
      'type' => 'event',
      'title' => 'My event',
      'event_registration' => 'conference',
      'sku' => strtolower($this->randomMachineName()),
      'price' => [
        'number' => 75.00,
        'currency_code' => 'USD',
      ],
    ]);
  }

  /**
   * Saves a product variation that is configured for registration.
   *
   * @param \Drupal\commerce_product\Entity\ProductInterface $product
   *   The product.
   *
   * @return \Drupal\commerce_product\Entity\ProductVariationInterface
   *   The created and saved product variation.
   */
  protected function createAndSaveVariation(ProductInterface $product): ProductVariationInterface {
    $variation = $this->createVariation();
    $variation->save();
    $product->addVariation($variation);
    $product->save();
    return $variation;
  }

}
