<?php

namespace Drupal\registration\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Migrates a property in an additional settings field.
 *
 * The properties are stored in a serialized "settings" field in Drupal 7.
 *
 * @MigrateProcessPlugin(
 *   id = "registration_additional_settings"
 * )
 */
class RegistrationAdditionalSettings extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $key = $this->configuration['key'];
    $settings = unserialize($value);
    if (isset($settings[$key])) {
      return $settings[$key];
    }
    return NULL;
  }

}
