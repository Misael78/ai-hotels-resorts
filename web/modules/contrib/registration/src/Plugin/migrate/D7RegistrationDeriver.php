<?php

namespace Drupal\registration\Plugin\migrate;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\migrate\Exception\RequirementsException;
use Drupal\migrate\Plugin\MigrationDeriverTrait;
use Drupal\migrate_drupal\FieldDiscoveryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Deriver for Drupal 7 registration migrations based on registration types.
 */
class D7RegistrationDeriver extends DeriverBase implements ContainerDeriverInterface {

  use MigrationDeriverTrait;

  /**
   * The base plugin ID this derivative is for.
   *
   * @var string
   */
  protected string $basePluginId;

  /**
   * The migration field discovery service.
   *
   * @var \Drupal\migrate_drupal\FieldDiscoveryInterface
   */
  protected FieldDiscoveryInterface $fieldDiscovery;

  /**
   * D7RegistrationDeriver constructor.
   *
   * @param string $base_plugin_id
   *   The base plugin ID for the plugin ID.
   * @param \Drupal\migrate_drupal\FieldDiscoveryInterface $field_discovery
   *   The migration field discovery service.
   */
  public function __construct(string $base_plugin_id, FieldDiscoveryInterface $field_discovery) {
    $this->basePluginId = $base_plugin_id;
    $this->fieldDiscovery = $field_discovery;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id): ContainerDeriverInterface|D7RegistrationDeriver|static {
    // Translations don't make sense unless we have content_translation.
    return new static(
      $base_plugin_id,
      $container->get('migrate_drupal.field_discovery')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    $registration_types = static::getSourcePlugin('d7_registration_type');
    try {
      $registration_types->checkRequirements();
    }
    catch (RequirementsException) {
      // If the d7_registration_type requirements failed, that means we do
      // not have a Drupal source database configured - there is nothing
      // to generate.
      return $this->derivatives;
    }

    try {
      foreach ($registration_types as $row) {
        $registration_type = $row->getSourceProperty('name');
        $values = $base_plugin_definition;

        $values['label'] = t('@label (@type)', [
          '@label' => $values['label'],
          '@type' => $row->getSourceProperty('name'),
        ]);
        $values['source']['registration_type'] = $registration_type;
        $values['destination']['default_bundle'] = $registration_type;

        /** @var \Drupal\migrate\Plugin\MigrationInterface $migration */
        $migration = \Drupal::service('plugin.manager.migration')->createStubMigration($values);
        $this->fieldDiscovery->addBundleFieldProcesses($migration, 'registration', $registration_type);
        $this->derivatives[$registration_type] = $migration->getPluginDefinition();
      }
    }
    catch (DatabaseExceptionWrapper) {
      // Once we begin iterating the source plugin it is possible that the
      // source tables will not exist. This can happen when the
      // MigrationPluginManager gathers the migration definitions, but we do
      // not actually have a Drupal 7 source database.
    }
    return $this->derivatives;
  }

}
