<?php

namespace Drupal\registration_scheduled_action\Cron;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;

/**
 * Queue objects with active registration schedules.
 *
 * @see \Drupal\registration_scheduled_action\Plugin\QueueWorker\ScheduledActionWorker
 */
class RegistrationSchedule {

  /**
   * The database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

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
   * The queue.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected QueueInterface $queue;

  /**
   * Constructs a new RegistrationSchedule object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface $key_value_factory
   *   The key value factory.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory.
   */
  public function __construct(Connection $database, EntityTypeManagerInterface $entity_type_manager, KeyValueExpirableFactoryInterface $key_value_factory, QueueFactory $queue_factory) {
    $this->database = $database;
    $this->entityTypeManager = $entity_type_manager;
    $this->keyValueFactory = $key_value_factory;
    $this->queue = $queue_factory->get('registration_scheduled_action.process_schedule');
  }

  /**
   * Run this task.
   */
  public function run() {
    $scheduled_actions = $this->entityTypeManager
      ->getStorage('registration_scheduled_action')
      ->loadByProperties([
        'status' => TRUE,
      ]);
    foreach ($scheduled_actions as $scheduled_action) {
      $plugin = $scheduled_action->getPlugin();
      $query = $plugin->getQuery($scheduled_action);
      $collection = $plugin->getKeyValueStoreCollectionName();
      $key_value_store = $this->keyValueFactory->get($collection);
      $key_field_name = $plugin->getQueryUniqueKeyColumnName();

      $result = $query->execute();
      foreach ($result as $object) {
        $record = (array) $object;

        // Only queue the item if it wasn't previously processed. The queue
        // worker records each processed item in the key value store.
        $key = $scheduled_action->getKeyValueStoreKeyName($record[$key_field_name]);
        if (!$key_value_store->has($key)) {
          $data = [
            'scheduled_action_id' => $scheduled_action->id(),
            'query_result_record' => $record,
          ];
          $this->queue->createItem($data);

          // Mark the item as queued to prevent duplicate processing.
          $key_value_store->setWithExpire($key, 'queued', $plugin->getKeyValueStoreExpirationTime());
        }
      }
    }
  }

}
