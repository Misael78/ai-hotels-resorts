<?php

namespace Drupal\Tests\commerce_registration\Kernel;

use Drupal\commerce_store\CurrentStore as BaseCurrentStore;

/**
 * Overrides the current_store service for testing.
 */
class CurrentStore extends BaseCurrentStore {

  /**
   * {@inheritdoc}
   */
  public function getStore() {
    return \Drupal::entityTypeManager()->getStorage('commerce_store')->load(1);
  }

}
