<?php

namespace Drupal\commerce_registration\Plugin\migrate\source;

use Drupal\registration\Plugin\migrate\source\Registration as BaseRegistration;

/**
 * Extends the Drupal 7 registration source from database.
 */
class Registration extends BaseRegistration {

  /**
   * {@inheritdoc}
   */
  public function fields(): array {
    return parent::fields() + [
      'order_id' => $this->t('Order ID'),
    ];
  }

}
