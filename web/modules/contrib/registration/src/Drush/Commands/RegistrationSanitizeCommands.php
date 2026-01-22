<?php

declare(strict_types=1);

namespace Drupal\registration\Drush\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\registration\Entity\RegistrationTypeInterface;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Drush\Drupal\Commands\sql\SanitizePluginInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Registration entities sanitization.
 *
 * Based on the:
 *  - Drush\Drupal\Commands\sql\SanitizeUserTableCommands
 *  - Drush\Drupal\Commands\sql\SanitizeUserFieldsCommands.
 */
final class RegistrationSanitizeCommands extends DrushCommands implements SanitizePluginInterface {

  /**
   * Constructs a RegistrationSanitizeCommands object.
   */
  public function __construct(
    protected Connection $database,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected FieldTypePluginManagerInterface $fieldTypePluginManager,
  ) {
    parent::__construct();
  }

  /**
   * Creates a RegistrationSanitizeCommands object.
   */
  public static function create(ContainerInterface $container): RegistrationSanitizeCommands {
    return new RegistrationSanitizeCommands(
      $container->get('database'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.field.field_type'),
    );
  }

  /**
   * Sanitize string fields associated with the registration entities.
   */
  #[CLI\Hook(type: HookManager::POST_COMMAND_HOOK, target: 'sql:sanitize')]
  public function sanitize($result, CommandData $commandData): void {
    $registration_types = $this->entityTypeManager->getStorage('registration_type')->loadMultiple();
    foreach ($registration_types as $registration_type) {
      $this->logger()->success(dt('Starting to process registration type "!registration_type" ...', ['!registration_type' => $registration_type->label()]));
      /** @var \Drupal\registration\Entity\RegistrationTypeInterface $registration_type */
      $this->sanitizeType($registration_type);
      $this->logger()->success(dt('Registration type "!registration_type" sanitized.', ['!registration_type' => $registration_type->label()]));
    }

    $this->logger()->success(dt('Starting to process registration emails ...'));

    // We need a different sanitization query for MS SQL, PostgreSQL and MySQL.
    $db_driver = $this->database->databaseType();
    if ($db_driver == 'pgsql') {
      $new_mail = "'registration+' || registration_id || '@localhost.localdomain'";
    }
    elseif ($db_driver == 'mssql') {
      $new_mail = "'registration+' + CAST(registration_id AS varchar) + '@localhost.localdomain'";
    }
    else {
      $new_mail = "concat('registration+', registration_id, '@localhost.localdomain')";
    }

    // Sanitize the calculated email field for all registrations.
    $query = $this->database->update('registration');
    $query->expression('mail', $new_mail);
    $query->execute();

    // Only sanitize the anonymous email field for rows where it exists.
    $query = $this->database->update('registration');
    $query->isNotNull('anon_mail');
    $query->expression('anon_mail', $new_mail);
    $query->execute();

    $this->logger()->success(dt('Registration emails sanitized.'));
  }

  /**
   * {@inheritdoc}
   */
  #[CLI\Hook(type: HookManager::ON_EVENT, target: 'sql-sanitize-confirms')]
  public function messages(&$messages, InputInterface $input): void {
    $messages[] = dt('Sanitize text fields associated with registrations.');
    $messages[] = dt('Sanitize registration emails.');
  }

  /**
   * Sanitize text fields related to a specific registration type.
   */
  protected function sanitizeType(RegistrationTypeInterface $registration_type) {
    $processed_fields = 0;
    $field_definitions = $this->entityFieldManager->getFieldDefinitions('registration', $registration_type->id());
    $field_storage = $this->entityFieldManager->getFieldStorageDefinitions('registration');

    foreach ($field_definitions as $key => $def) {
      $execute = FALSE;
      if (!isset($field_storage[$key]) || $field_storage[$key]->isBaseField()) {
        continue;
      }

      $value = '';
      $value_array = ['summary' => ''];
      $table = 'registration__' . $key;
      $query = $this->database->update($table);
      $name = $def->getName();
      $field_type_class = $this->fieldTypePluginManager->getPluginClass($def->getType());
      $supported_field_types = [
        'email',
        'string',
        'string_long',
        'telephone',
        'text',
        'text_long',
        'text_with_summary',
      ];
      if (in_array($def->getType(), $supported_field_types, TRUE)) {
        /** @var \Drupal\Core\Field\FieldItemInterface $field_type_class */
        $value_array = $field_type_class::generateSampleValue($def);
        $value = $value_array['value'];
      }
      switch ($def->getType()) {
        case 'email':
        case 'string':
        case 'string_long':
        case 'telephone':
        case 'text':
        case 'text_long':
          $query->fields([$name . '_value' => $value]);
          $execute = TRUE;
          break;

        case 'text_with_summary':
          $query->fields([
            $name . '_value' => $value,
            $name . '_summary' => $value_array['summary'],
          ]);
          $execute = TRUE;
          break;
      }
      if ($execute) {
        $query->execute();
        $processed_fields++;
        $this->logger()->success(dt(' - !table table sanitized.', ['!table' => $table]));
      }
    }

    if (empty($processed_fields)) {
      $this->logger()->success(dt('No text fields for the registration type "!registration_type" need sanitizing.', ['!registration_type' => $registration_type->label()]));
    }

    $this->entityTypeManager->getStorage('registration')->resetCache();
  }

}
