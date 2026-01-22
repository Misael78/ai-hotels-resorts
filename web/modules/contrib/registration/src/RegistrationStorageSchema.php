<?php

namespace Drupal\registration;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorageSchema;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * Defines the registration schema handler.
 */
class RegistrationStorageSchema extends SqlContentEntityStorageSchema {

  /**
   * {@inheritdoc}
   */
  protected function getEntitySchema(ContentEntityTypeInterface $entity_type, $reset = FALSE): array {
    $schema = parent::getEntitySchema($entity_type, $reset);

    if ($base_table = $this->storage->getBaseTable()) {
      if ($base_table == 'registration') {
        $schema[$base_table]['indexes'] += [
          'registration__host_entity' => ['entity_type_id', 'entity_id'],
        ];
      }
    }

    if ($data_table = $this->storage->getDataTable()) {
      if ($data_table == 'registration_settings_field_data') {
        $schema[$data_table]['indexes'] += [
          'registration__host_entity' => [
            'entity_type_id',
            'entity_id',
            'langcode',
          ],
        ];
        $schema[$data_table]['unique keys'] = [
          'registration__host_entity_unique' => [
            'entity_type_id',
            'entity_id',
            'langcode',
          ],
        ];
      }
    }

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  protected function getSharedTableFieldSchema(FieldStorageDefinitionInterface $storage_definition, $table_name, array $column_mapping): array {
    $schema = parent::getSharedTableFieldSchema($storage_definition, $table_name, $column_mapping);
    $field_name = $storage_definition->getName();

    switch ($field_name) {
      case 'entity_type_id':
      case 'entity_id':
        // Improves the performance of the indexes defined
        // in getEntitySchema().
        $schema['fields'][$field_name]['not null'] = TRUE;
        break;
    }

    return $schema;
  }

}
