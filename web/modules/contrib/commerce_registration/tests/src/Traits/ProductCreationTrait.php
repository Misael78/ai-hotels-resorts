<?php

namespace Drupal\Tests\commerce_registration\Traits;

use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductInterface;

/**
 * Defines a trait for creating a test product and saving it.
 */
trait ProductCreationTrait {

  /**
   * Creates a product.
   *
   * @return \Drupal\commerce_product\Entity\ProductInterface
   *   The created (unsaved) product.
   */
  protected function createProduct(): ProductInterface {
    return Product::create([
      'type' => 'event',
      'title' => 'My event',
      'stores' => [1],
    ]);
  }

  /**
   * Creates a product and saves it.
   *
   * @return \Drupal\commerce_product\Entity\ProductInterface
   *   The created and saved product.
   */
  protected function createAndSaveProduct(): ProductInterface {
    $product = $this->createProduct();
    $product->save();
    return $product;
  }

}
