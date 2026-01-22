<?php

namespace Drupal\registration_scheduled_action\Entity;

use Drupal\Core\Action\ActionInterface;
use Drupal\Core\Action\ConfigurableActionBase;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\StringTranslation\PluralTranslatableMarkup;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

/**
 * Defines the scheduled action entity class.
 *
 * @ConfigEntityType(
 *   id = "registration_scheduled_action",
 *   label = @Translation("Scheduled action"),
 *   label_collection = @Translation("Registration schedule"),
 *   label_singular = @Translation("scheduled action"),
 *   label_plural = @Translation("scheduled actions"),
 *   label_count = @PluralTranslation(
 *     singular = "@count scheduled action",
 *     plural = "@count scheduled actions",
 *   ),
 *   handlers = {
 *     "access" = "Drupal\Core\Entity\EntityAccessControlHandler",
 *     "list_builder" = "Drupal\registration_scheduled_action\ScheduledActionListBuilder",
 *     "form" = {
 *       "add" = "Drupal\registration_scheduled_action\Form\ScheduledActionForm",
 *       "edit" = "Drupal\registration_scheduled_action\Form\ScheduledActionForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "default" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   admin_permission = "administer registration scheduled action",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "weight" = "weight",
 *     "status" = "status",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "weight",
 *     "status",
 *     "datetime",
 *     "target_langcode",
 *     "plugin",
 *     "configuration",
 *   },
 *   links = {
 *     "add-form" = "/admin/structure/registration/schedule/add",
 *     "edit-form" = "/admin/structure/registration/schedule/{registration_scheduled_action}/edit",
 *     "delete-form" = "/admin/structure/registration/schedule/{registration_scheduled_action}/delete",
 *     "collection" = "/admin/structure/registration/schedule"
 *   }
 * )
 */
class ScheduledAction extends ConfigEntityBase implements ScheduledActionInterface {

  /**
   * The scheduled action weight.
   *
   * @var string|null
   */
  protected ?string $weight;

  /**
   * The target language for the action.
   *
   * The langcode 'und' (LANGCODE_NOT_SPECIFIED) means 'all'.
   *
   * @var string
   */
  protected string $target_langcode;

  /**
   * The date and time when the action should occur, relative to another date.
   *
   * @var array|null
   */
  protected ?array $datetime;

  /**
   * The ID of the action plugin to execute.
   *
   * @var string|null
   */
  protected ?string $plugin;

  /**
   * The plugin configuration.
   *
   * @var array
   */
  protected array $configuration = [];

  /**
   * {@inheritdoc}
   */
  public function getDateTime(): ?array {
    return $this->datetime ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getDateTimeArrayForQuery(): ?array {
    if ($datetime = $this->getDateTime()) {
      $storage_format = DateTimeItemInterface::DATETIME_STORAGE_FORMAT;
      $storage_timezone = new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE);

      // Get the first date relative to the current date.
      $strtotime = $this->getDateTimeStrToTime($datetime);
      $date1 = new DrupalDateTime($strtotime, $storage_timezone);
      $date1_value = $date1->format($storage_format);

      // Get the second date relative to the current date.
      if ($datetime['type'] == 'months') {
        // For months, adjust the second date by one day instead of one month.
        $php_date_time = $date1->getPhpDateTime();
        $interval = new \DateInterval('P1D');
        $interval->invert = ($datetime['position'] == 'before');
        $php_date_time->sub($interval);
        $date2 = DrupalDateTime::createFromDateTime($php_date_time);
      }
      elseif ($datetime['type'] == 'minutes') {
        // For minutes, adjust the second date by 1 hour, to allow sites with
        // cron running once per hour to get matches. If cron is set to run more
        // often, the more accurate the timing will be.
        $php_date_time = $date1->getPhpDateTime();
        $interval = new \DateInterval('PT1H');
        $interval->invert = ($datetime['position'] == 'before');
        $php_date_time->sub($interval);
        $date2 = DrupalDateTime::createFromDateTime($php_date_time);
      }
      else {
        // Adjust the second date by one period relative to the first date.
        $length = $datetime['length'];
        $datetime['length'] = ($datetime['position'] == 'before') ? $length - 1 : $length + 1;
        $strtotime = $this->getDateTimeStrToTime($datetime);
        $date2 = new DrupalDateTime($strtotime, $storage_timezone);
      }

      // Adjust the second date by one second to avoid executing an action too
      // early.
      $interval = new \DateInterval('PT1S');
      $date2->add($interval);

      // Return the dates. The second date is always earlier than the first
      // date, so it must be returned first in the array to meet the
      // requirements of the BETWEEN operator.
      $date2_value = $date2->format($storage_format);
      return [$date2_value, $date1_value];
    }

    // Return NULL if the action does not have a valid schedule yet.
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getTimestampArrayForQuery(): ?array {
    if ($datetime_array = $this->getDateTimeArrayForQuery()) {
      $storage_format = DateTimeItemInterface::DATETIME_STORAGE_FORMAT;
      $storage_timezone = new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE);
      $date1 = DrupalDateTime::createFromFormat($storage_format, $datetime_array[0], $storage_timezone);
      $php_date_time = $date1->getPhpDateTime();
      $timestamp1 = $php_date_time->getTimestamp();
      $date2 = DrupalDateTime::createFromFormat($storage_format, $datetime_array[1], $storage_timezone);
      $php_date_time = $date2->getPhpDateTime();
      $timestamp2 = $php_date_time->getTimestamp();
      return [$timestamp1, $timestamp2];
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getDateTimeForDisplay(): string {
    // Use a switch statement to ensure proper translatability.
    if ($datetime = $this->getDateTime()) {
      if ($datetime['position'] == 'before') {
        switch ($datetime['type']) {
          case 'minutes':
            return new PluralTranslatableMarkup(
              $datetime['length'],
              '1 minute before',
              '@count minutes before'
            );

          case 'hours':
            return new PluralTranslatableMarkup(
              $datetime['length'],
              '1 hour before',
              '@count hours before'
            );

          case 'days':
            return new PluralTranslatableMarkup(
              $datetime['length'],
              '1 day before',
              '@count days before'
            );

          case 'months':
            return new PluralTranslatableMarkup(
              $datetime['length'],
              '1 month before',
              '@count months before'
            );
        }
      }
      else {
        switch ($datetime['type']) {
          case 'minutes':
            return new PluralTranslatableMarkup(
              $datetime['length'],
              '1 minute after',
              '@count minutes after'
            );

          case 'hours':
            return new PluralTranslatableMarkup(
              $datetime['length'],
              '1 hour after',
              '@count hours after'
            );

          case 'days':
            return new PluralTranslatableMarkup(
              $datetime['length'],
              '1 day after',
              '@count days after'
            );

          case 'months':
            return new PluralTranslatableMarkup(
              $datetime['length'],
              '1 month after',
              '@count months after'
            );
        }
      }
    }

    // Return a blank string if the action does not have a valid schedule yet.
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getKeyValueStoreKeyName(string $record_key): string {
    return $this->id() . '.' . $this->getTargetLangcode() . ':' . $record_key;
  }

  /**
   * {@inheritdoc}
   */
  public function setDateTime(array $datetime): ScheduledActionInterface {
    $this->datetime = $datetime;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPlugin(): ?ActionInterface {
    if ($plugin_id = $this->getPluginId()) {
      return \Drupal::service('plugin.manager.action')->createInstance($plugin_id);
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginConfiguration(): array {
    $configuration = $this->configuration;
    // If no configuration exists yet, use the plugin's default configuration
    // if the plugin is configurable.
    if (empty($configuration) && ($plugin = $this->getPlugin())) {
      if ($plugin instanceof ConfigurableActionBase) {
        $configuration = $plugin->defaultConfiguration();
      }
    }
    return $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setPluginConfiguration(array $configuration): ScheduledActionInterface {
    $this->configuration = $configuration;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginId(): ?string {
    return $this->plugin ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setPluginId(string $plugin_id): ScheduledActionInterface {
    $this->plugin = $plugin_id;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetLangcode(): string {
    return $this->target_langcode ?? LanguageInterface::LANGCODE_NOT_SPECIFIED;
  }

  /**
   * {@inheritdoc}
   */
  public function setTargetLangcode(string $langcode): ScheduledActionInterface {
    $this->target_langcode = $langcode;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight(): ?string {
    return $this->weight ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setWeight(int $weight): ScheduledActionInterface {
    $this->weight = $weight;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled(): bool {
    return $this->status;
  }

  /**
   * {@inheritdoc}
   */
  public function setEnabled(bool $enabled = TRUE): ScheduledActionInterface {
    $this->status = $enabled;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): ScheduledActionInterface {
    parent::calculateDependencies();

    // The scheduled action depends on the module that provides the plugin.
    if ($plugin = $this->getPlugin()) {
      $this->calculatePluginDependencies($plugin);
    }

    return $this;
  }

  /**
   * Gets a datetime in strtotime format.
   *
   * @param array $datetime
   *   The datetime as an array.
   *
   * @return string
   *   The datetime in strtotime format.
   *
   * @see https://www.php.net/manual/en/function.strtotime.php
   */
  protected function getDateTimeStrToTime(array $datetime): string {
    $sign = ($datetime['position'] == 'before') ? '+' : '-';
    return $sign . $datetime['length'] . ' ' . $datetime['type'];
  }

}
