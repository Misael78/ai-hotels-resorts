<?php

namespace Drupal\registration\Plugin\migrate\source;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\migrate\source\d7\FieldableEntity;

/**
 * Drupal 7 registration source from database.
 *
 * @MigrateSource(
 *   id = "d7_registration",
 *   source_module = "registration"
 * )
 */
class Registration extends FieldableEntity {

  /**
   * {@inheritdoc}
   */
  public function query(): SelectInterface {
    $query = $this
      ->select('registration', 'r')
      ->fields('r');

    if (isset($this->configuration['registration_type'])) {
      $query->condition('r.type', (array) $this->configuration['registration_type'], 'IN');
    }

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $registration_id = $row->getSourceProperty('registration_id');
    $type = $row->getSourceProperty('type');

    // Get Field API field values.
    foreach ($this->getFields('registration', $type) as $field_name => $field) {
      $row->setSourceProperty($field_name, $this->getFieldValues('registration', $field_name, $registration_id));
    }

    return parent::prepareRow($row);
  }

  /**
   * {@inheritdoc}
   */
  public function fields(): array {
    return [
      'registration_id' => $this->t('Registration ID'),
      'type' => $this->t('Type'),
      'entity_id' => $this->t('Entity ID'),
      'entity_type' => $this->t('Entity Type ID'),
      'anon_mail' => $this->t('Anonymous email'),
      'count' => $this->t('Spaces'),
      'user_uid' => $this->t('User ID'),
      'author_uid' => $this->t('Author'),
      'state' => $this->t('Registration state'),
      'created' => $this->t('Created timestamp'),
      'updated' => $this->t('Modified timestamp'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds(): array {
    $ids['registration_id']['type'] = 'integer';
    $ids['registration_id']['alias'] = 'r';
    return $ids;
  }

}
