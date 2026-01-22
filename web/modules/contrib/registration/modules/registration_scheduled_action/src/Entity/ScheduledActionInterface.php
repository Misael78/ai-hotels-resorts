<?php

namespace Drupal\registration_scheduled_action\Entity;

use Drupal\Core\Action\ActionInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Defines the interface for scheduled actions.
 */
interface ScheduledActionInterface extends ConfigEntityInterface {

  /**
   * Gets the datetime for a scheduled action.
   *
   * The datetime is returned in an internal format as an array with the
   * following elements:
   *
   *   ['length'] = the number of periods
   *   ['type'] = hours, minutes, days or months
   *   ['position'] = before or after
   *
   * For example, for an action that should occur 4 hours before an event, the
   * array is returned as follows:
   *
   *   ['length'] = 4
   *   ['type'] = hours
   *   ['position'] = before
   *
   * @return array|null
   *   The datetime, if available.
   */
  public function getDateTime(): ?array;

  /**
   * Gets the datetime array for a scheduled action for use in a query.
   *
   * This converts from internal format to an array with two elements that can
   * be used in a BETWEEN query condition. For example, if the action should
   * occur 4 hours before an event, then the array will be returned with the
   * first element 3 hours and one second after the current time, and the
   * second element exactly 4 hours after the current time. The more frequently
   * cron is set up to run, the closer the action executes to the requested
   * time.
   *
   * The two elements are returned in the standard date storage format of
   * Y-m-d\TH:i:s (ISO varchar) and in the standard storage timezone (UTC).
   *
   * @return array|null
   *   The datetime array, if available.
   *
   * @see \Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface::DATETIME_STORAGE_FORMAT
   * @see \Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface::STORAGE_TIMEZONE
   */
  public function getDateTimeArrayForQuery(): ?array;

  /**
   * Gets the timestamp array for a scheduled action for use in a query.
   *
   * This is the same as getDateTimeArrayForQuery but returns the elements as
   * Unix timestamps instead of in ISO format.
   *
   * @return array|null
   *   The timestamp array, if available.
   */
  public function getTimestampArrayForQuery(): ?array;

  /**
   * Gets the datetime for a scheduled action for use in a display.
   *
   * This converts from an array to a display format such as '7 days before'.
   *
   * @return string
   *   The formatted datetime string, or a blank string if not available.
   */
  public function getDateTimeForDisplay(): string;

  /**
   * Gets the key name for a given query result record.
   *
   * @param string $record_key
   *   The key field value from a query result record.
   *
   * @return string
   *   The key name to use in key value store lookups.
   */
  public function getKeyValueStoreKeyName(string $record_key): string;

  /**
   * Sets the datetime for a scheduled action.
   *
   * @param array $datetime
   *   The datetime.
   *
   * @return $this
   */
  public function setDateTime(array $datetime): ScheduledActionInterface;

  /**
   * Gets the plugin for a scheduled action.
   *
   * @return \Drupal\Core\Action\ActionInterface|null
   *   The action plugin, if available.
   */
  public function getPlugin(): ?ActionInterface;

  /**
   * Gets the configuration for a scheduled action plugin.
   *
   * @return array
   *   The plugin configuration.
   */
  public function getPluginConfiguration(): array;

  /**
   * Sets the configuration for a scheduled action plugin.
   *
   * @param array $configuration
   *   The plugin configuration.
   *
   * @return $this
   */
  public function setPluginConfiguration(array $configuration): ScheduledActionInterface;

  /**
   * Gets the plugin ID for a scheduled action.
   *
   * @return string|null
   *   The action plugin ID, if available.
   */
  public function getPluginId(): ?string;

  /**
   * Sets the plugin ID for a scheduled action.
   *
   * @param string $plugin_id
   *   The action plugin ID.
   *
   * @return $this
   */
  public function setPluginId(string $plugin_id): ScheduledActionInterface;

  /**
   * Gets the code for the language the scheduled action targets.
   *
   * @return string
   *   The scheduled action language code.
   */
  public function getTargetLangcode(): string;

  /**
   * Sets the language code for the language that the scheduled action targets.
   *
   * @param string $langcode
   *   The language code.
   *
   * @return $this
   */
  public function setTargetLangcode(string $langcode): ScheduledActionInterface;

  /**
   * Gets the scheduled action weight.
   *
   * @return string|null
   *   The scheduled action weight, if available.
   */
  public function getWeight(): ?string;

  /**
   * Sets the scheduled action weight.
   *
   * @param int $weight
   *   The payment gateway weight.
   *
   * @return $this
   */
  public function setWeight(int $weight): ScheduledActionInterface;

  /**
   * Determines if the scheduled action is enabled or disabled.
   *
   * @return bool
   *   TRUE if the scheduled action is enabled, FALSE otherwise.
   */
  public function isEnabled(): bool;

  /**
   * Sets the enabled or disabled status of the scheduled action.
   *
   * @param bool $enabled
   *   TRUE if the scheduled action should be enabled, FALSE otherwise.
   *
   * @return $this
   */
  public function setEnabled(bool $enabled = TRUE): ScheduledActionInterface;

}
