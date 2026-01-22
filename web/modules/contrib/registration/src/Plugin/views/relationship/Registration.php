<?php

namespace Drupal\registration\Plugin\views\relationship;

use Drupal\views\Plugin\views\relationship\RelationshipPluginBase;

/**
 * Relationship handler to return the registrations for host entities.
 *
 * @ingroup views_relationship_handlers
 *
 * @ViewsRelationship("registration")
 */
class Registration extends RelationshipPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();

    $def = $this->definition;
    $def['left_table'] = $this->tableAlias;
    $def['left_field'] = \Drupal::entityTypeManager()->getDefinition($def['entity_type'])->getKey('id');
    $def['table'] = 'registration';
    $def['field'] = 'entity_id';
    $def['type'] = empty($this->options['required']) ? 'LEFT' : 'INNER';
    $def['extra'] = [
      0 => [
        'field' => 'entity_type_id',
        'value' => $def['entity_type'],
      ],
    ];

    $join = \Drupal::service('plugin.manager.views.join')->createInstance('standard', $def);

    $alias = $def['table'] . '_' . $this->table;

    $this->alias = $this->query->addRelationship($alias, $join, 'registration', $this->relationship);
  }

}
