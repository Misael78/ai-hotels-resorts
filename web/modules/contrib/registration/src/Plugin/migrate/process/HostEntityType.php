<?php

namespace Drupal\registration\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Migrates the entity type for a host entity.
 *
 * @MigrateProcessPlugin(
 *   id = "host_entity_type"
 * )
 */
class HostEntityType extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    // Commerce 1 products became Commerce 2 product variations.
    if ($value == 'commerce_product') {
      $value = 'commerce_product_variation';
    }
    return $value;
  }

}
