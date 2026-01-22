<?php

namespace Drupal\registration\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\migrate\process\MigrationLookup;
use Drupal\migrate\Row;

/**
 * Migrates a host entity ID.
 *
 * Uses the host entity type to look up the ID in the appropriate migration.
 * This process plugin is necessary since the target type is unknown until
 * run time - the standard migration lookup requires the type to be known
 * in advance, so it can be specified in the migration configuration file.
 *
 * @MigrateProcessPlugin(
 *   id = "host_entity_migration_lookup"
 * )
 */
class HostEntityMigrationLookup extends MigrationLookup {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property): array|string|NULL {
    $entity_type_id = $row->getSourceProperty('entity_type');
    // Find migrations for the host entity type.
    $migrations = match ($entity_type_id) {
      'commerce_product' => $this->getDestinationPlugins('commerce_product_variation'),
      default => $this->getDestinationPlugins($entity_type_id),
    };

    $this->configuration['migration'] = $migrations;

    return parent::transform($value, $migrate_executable, $row, $destination_property);
  }

  /**
   * Retrieve migration plugins with the given destination entity type ID.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return array
   *   An array of migration plugin IDs.
   */
  protected function getDestinationPlugins(string $entity_type_id): array {
    $all_definitions = \Drupal::service('plugin.manager.migration')
      ->getDefinitions();
    $definitions = [];
    foreach ($all_definitions as $definition) {
      if (isset($definition['destination'], $definition['destination']['plugin'])) {
        if ($definition['destination']['plugin'] == ('entity:' . $entity_type_id)) {
          $definitions[$definition['id']] = $definition['id'];
        }
        // Handle complete migrations, for example node_complete.
        elseif ($definition['destination']['plugin'] == ('entity_complete:' . $entity_type_id)) {
          $definitions[$definition['id']] = $definition['id'];
        }
      }
    }
    return array_values($definitions);
  }

}
