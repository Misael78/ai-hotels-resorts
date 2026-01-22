<?php

namespace Drupal\registration\Plugin\migrate\source;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;

/**
 * Drupal 7 registration settings source from database.
 *
 * @MigrateSource(
 *   id = "d7_registration_settings",
 *   source_module = "registration"
 * )
 */
class RegistrationSettings extends DrupalSqlBase {

  /**
   * {@inheritdoc}
   */
  public function query(): SelectInterface {
    return $this->select('registration_entity', 're')->fields('re');
  }

  /**
   * {@inheritdoc}
   */
  public function fields(): array {
    return [
      'entity_id' => $this->t('Entity ID'),
      'entity_type' => $this->t('Entity Type'),
      'capacity' => $this->t('Capacity'),
      'status' => $this->t('Status'),
      'send_reminder' => $this->t('Send reminder'),
      'reminder_date' => $this->t('Reminder date'),
      'reminder_template' => $this->t('Reminder template'),
      'open' => $this->t('Open'),
      'close' => $this->t('Close'),
      'settings' => $this->t('Additional settings'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds(): array {
    $ids['entity_id']['type'] = 'integer';
    $ids['entity_id']['alias'] = 're';
    $ids['entity_type']['type'] = 'string';
    $ids['entity_type']['alias'] = 're';
    return $ids;
  }

}
