<?php

namespace Drupal\registration\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Migrates the completed base field for registrations.
 *
 * Since the D7 entity does not have a completed field, this plugin calculates
 * the value based on the values of the state and updated fields. If the state
 * is "complete", the value of the "updated" (last updated date/time) property
 * is placed into the completed property. Otherwise the completed property is
 * set to NULL.
 *
 * Example usage:
 *
 * process:
 *   completed:
 *     plugin: registration_completed
 *     source: updated
 *
 * @MigrateProcessPlugin(
 *   id = "registration_completed"
 * )
 */
class RegistrationCompleted extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $source = $row->getSource();
    $state = $source['state'];
    if ($state === 'complete') {
      $value = $source['updated'];
    }
    else {
      $value = NULL;
    }
    return $value;
  }

}
