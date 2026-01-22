<?php

namespace Drupal\registration\Plugin\migrate\source;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;

/**
 * Drupal 7 registration type source from database.
 *
 * @MigrateSource(
 *   id = "d7_registration_type",
 *   source_module = "registration"
 * )
 */
class RegistrationType extends DrupalSqlBase {

  /**
   * {@inheritdoc}
   */
  public function query(): SelectInterface {
    return $this->select('registration_type', 'rt')->fields('rt');
  }

  /**
   * {@inheritdoc}
   */
  public function fields(): array {
    return [
      'id' => $this->t('Type ID'),
      'name' => $this->t('Machine name'),
      'label' => $this->t('Display label'),
      'weight' => $this->t('Weight'),
      'locked' => $this->t('Locked'),
      'data' => $this->t('Serialized data'),
      'status' => $this->t('Status'),
      'module' => $this->t('Module'),
      'default_state' => $this->t('Default state'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds(): array {
    $ids['id']['type'] = 'integer';
    $ids['id']['alias'] = 'rt';
    return $ids;
  }

}
