<?php

namespace Drupal\registration\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Migrates a settings reminder template.
 *
 * Example usage:
 *
 * process:
 *   reminder_template:
 *     plugin: registration_reminder_template
 *     source: reminder_template
 *     format: full_html
 *
 * @MigrateProcessPlugin(
 *   id = "registration_reminder_template"
 * )
 */
class RegistrationReminderTemplate extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $template = [];
    $template['value'] = $value;
    if (!empty($this->configuration['format'])) {
      // Use the format specified in the migration configuration.
      $template['format'] = $this->configuration['format'];
    }
    else {
      // Format not specified, use basic_html if available.
      $formats = filter_formats();
      if (!empty($formats['basic_html'])) {
        $template['format'] = 'basic_html';
      }
      else {
        // Use the fallback format if all else fails.
        $template['format'] = filter_fallback_format();
      }
    }
    return $template;
  }

}
