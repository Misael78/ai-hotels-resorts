<?php

namespace Drupal\registration\Plugin\migrate\field;

use Drupal\migrate_drupal\Plugin\migrate\field\FieldPluginBase;

/**
 * Migrates a registration field.
 *
 * @MigrateField(
 *   id = "registration",
 *   core = {7},
 *   source_module = "registration",
 *   destination_module = "registration"
 * )
 */
class RegistrationField extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getFieldFormatterMap() {
    return [
      'registration_default' => 'registration_type',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldWidgetMap(): array {
    return [
      'registration_select' => 'registration_type',
    ];
  }

}
