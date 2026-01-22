<?php

namespace Drupal\registration\Cron;

use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

/**
 * Maintain the per-entity "status" setting automatically.
 *
 * This is the "Enabled" checkbox on the Settings form. This cron job keeps
 * the field in sync with the "Open" and "Close" dates on the same form.
 *
 * @deprecated in registration:3.4.0 and is removed from registration:4.0.0.
 *   No replacement is provided. Sites that rely on updates to the registration
 *   settings at open and close can subscribe to the newly added events
 *   REGISTRATION_SETTINGS_OPEN and REGISTRATION_SETTINGS_CLOSE.
 *
 * @see https://www.drupal.org/node/3506953
 *
 * @see \Drupal\registration\Form\RegistrationSettingsForm
 * @see \Drupal\registration\Plugin\QueueWorker\SetAndForget
 */
class SetAndForget {

  /**
   * The database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * The queue.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected QueueInterface $queue;

  /**
   * Constructs a new SetAndForget object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory.
   */
  public function __construct(Connection $database, QueueFactory $queue_factory) {
    @trigger_error(__CLASS__ . ' is deprecated in registration:3.4.0 and is removed from registration:4.0.0. See https://www.drupal.org/node/3506953', E_USER_DEPRECATED);
    $this->database = $database;
    $this->queue = $queue_factory->get('registration.set_and_forget');
  }

  /**
   * Run this task.
   */
  public function run() {
    // Establish the current time in the storage timezone and format.
    $storage_format = DateTimeItemInterface::DATETIME_STORAGE_FORMAT;
    $storage_timezone = new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE);
    $now = new DrupalDateTime('now', $storage_timezone);
    $now_date = $now->format($storage_format);

    // Clear existing queue items to avoid reprocessing.
    $this->queue->deleteQueue();

    // Registrations that should be enabled but aren't.
    $query = $this->database->select('registration_settings_field_data', 'r')
      ->fields('r')
      ->condition('status', 0)
      ->condition('open', $now_date, '<=')
      ->condition('close', $now_date, '>=');
    $result = $query->execute();

    foreach ($result as $record) {
      $item = [
        'settings_id' => $record->settings_id,
        'new_status' => 1,
      ];
      $this->queue->createItem($item);
    }

    // Registrations that are enabled but shouldn't be.
    $query = $this->database->select('registration_settings_field_data', 'r')
      ->fields('r')
      ->condition('status', 1);
    $orGroup = $query->orConditionGroup()
      ->condition('open', $now_date, '>')
      ->condition('close', $now_date, '<');
    $query->condition($orGroup);
    $result = $query->execute();

    foreach ($result as $record) {
      $item = [
        'settings_id' => $record->settings_id,
        'new_status' => 0,
      ];
      $this->queue->createItem($item);
    }
  }

}
