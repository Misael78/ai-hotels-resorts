<?php

namespace Drupal\registration_scheduled_action\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Process scheduled actions.
 *
 * @QueueWorker(
 *  id = "registration_scheduled_action.process_schedule",
 *  title = @Translation("Process scheduled actions"),
 *  cron = {"time" = 30}
 * )
 */
class ProcessSchedule extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The key value factory.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface
   */
  protected KeyValueExpirableFactoryInterface $keyValueFactory;

  /**
   * Constructs a new ProcessSchedule object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface $key_value_factory
   *   The key value factory.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, KeyValueExpirableFactoryInterface $key_value_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
    $this->keyValueFactory = $key_value_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): ProcessSchedule {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('keyvalue.expirable')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $scheduled_action = $this->entityTypeManager
      ->getStorage('registration_scheduled_action')
      ->load($data['scheduled_action_id']);
    if ($scheduled_action) {
      // Execute the scheduled action.
      $plugin = $scheduled_action->getPlugin();
      $record = $data['query_result_record'];
      $object = (object) $record;
      $object->scheduled_action = $scheduled_action;
      if ($plugin->access($object)) {
        $plugin->execute($object);
      }

      // Mark the item as processed without changing its expiration.
      $collection = $plugin->getKeyValueStoreCollectionName();
      $key_value_store = $this->keyValueFactory->get($collection);
      $key_field_name = $plugin->getQueryUniqueKeyColumnName();
      $key = $scheduled_action->getKeyValueStoreKeyName($record[$key_field_name]);
      $key_value_store->set($key, 'processed');
    }
  }

}
