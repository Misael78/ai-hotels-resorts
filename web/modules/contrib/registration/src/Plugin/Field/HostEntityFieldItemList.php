<?php

namespace Drupal\registration\Plugin\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Defines an item list class for host entity reference fields.
 *
 * @see https://www.drupal.org/docs/drupal-apis/entity-api/dynamicvirtual-field-values-using-computed-field-property-classes
 */
class HostEntityFieldItemList extends FieldItemList {

  use ComputedItemListTrait;

  /**
   * Resets the calculated state of the field.
   */
  public function reset(): void {
    if (isset($this->list[0])) {
      $this->list[0]->reset();
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    if (!isset($this->list[0])) {
      $this->list[0] = $this->createItem();
    }
  }

}
