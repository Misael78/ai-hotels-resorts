<?php

namespace Drupal\registration\Plugin\views\filter;

/**
 * Filter on the number of registration spaces remaining on the host entity.
 *
 * This filter might not be accurate if custom event subscribers alter the
 * standard calculation of remaining spaces. For example, if an application
 * uses the REGISTRATION_ALTER_USAGE or REGISTRATION_ALTER_SPACES_REMAINING
 * events, the logic in those subscribers is not applied here, and the filter
 * might not provide results that match the output of the associated views
 * field plugin.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("host_entity_spaces_remaining")
 */
class HostEntitySpacesRemaining extends HostEntityFilterBase {

  /**
   * Filters by "between" or "not between" operator.
   *
   * @param object $field
   *   The views field.
   */
  protected function opBetween($field): void {
    if ($entity_type = $this->view->getBaseEntityType()) {
      $this->ensureMyTable();

      $expression = NULL;
      $args = [];
      $operator = '';
      $field = $this->getMainSelect();

      if (is_numeric($this->value['min']) && is_numeric($this->value['max'])) {
        $operator = $this->operator == 'between' ? 'BETWEEN' : 'NOT BETWEEN';
        $args = [
          ':min' => $this->value['min'],
          ':max' => $this->value['max'],
        ];
        $expression = "$field $operator :min AND :max";
      }
      elseif (is_numeric($this->value['min'])) {
        $operator = $this->operator == 'between' ? '>=' : '<';
        $args = [
          ':min' => $this->value['min'],
        ];
        $expression = "$expression $operator :min";
      }
      elseif (is_numeric($this->value['max'])) {
        $operator = $this->operator == 'between' ? '<=' : '>';
        $args = [
          ':max' => $this->value['max'],
        ];
        $expression = "$expression $operator :max";
      }

      if ($expression) {
        $args += [
          ':entity_type_id' => $entity_type->id(),
          ':states[]' => array_filter($this->options['registration_states']),
        ];
        $this->alterForUnlimitedCapacity($expression, $operator);
        $this->query->addWhereExpression($this->options['group'], $expression, $args);
      }
    }
  }

  /**
   * Filters by simple operator.
   *
   * @param object $field
   *   The views field.
   */
  protected function opSimple($field): void {
    if ($entity_type = $this->view->getBaseEntityType()) {
      $this->ensureMyTable();

      $field = $this->getMainSelect();
      $args = [
        ':entity_type_id' => $entity_type->id(),
        ':states[]' => array_filter($this->options['registration_states']),
        ':remaining' => $this->value['value'],
      ];
      $expression = "$field $this->operator :remaining";
      $this->alterForUnlimitedCapacity($expression, $this->operator);
      $this->query->addWhereExpression($this->options['group'], $expression, $args);
    }
  }

  /**
   * Alters a SQL expression to adjust it for unlimited capacity.
   *
   * @param string $expression
   *   The expression to modify.
   * @param string $operator
   *   The operator to use in making the adjustment.
   */
  protected function alterForUnlimitedCapacity(string &$expression, string $operator): void {
    $capacity = $this->getCapacitySelect();
    switch ($operator) {
      case '>':
      case '>=':
      case '!=':
      case 'NOT BETWEEN':
        // OR unlimited capacity.
        $expression = "$expression OR $capacity = 0";
        break;

      case '<':
      case '<=':
      case '=':
      case 'BETWEEN':
        // AND NOT unlimited capacity.
        $expression = "$expression AND $capacity <> 0";
        break;
    }
  }

  /**
   * Gets the SQL SELECT for host entity capacity.
   *
   * @return string
   *   The select statement.
   */
  protected function getCapacitySelect(): string {
    $entity_type = $this->view->getBaseEntityType();

    $id_field = $this->tableAlias . '.' . $entity_type->getKey('id');

    return "(SELECT capacity FROM {registration_settings_field_data} WHERE entity_type_id = :entity_type_id AND entity_id = $id_field)";
  }

  /**
   * Gets the SQL SELECT for host entity spaces remaining.
   *
   * @return string
   *   The SELECT statement.
   *
   * @see \Drupal\registration\HostEntity::getSpacesRemaining()
   */
  protected function getMainSelect(): string {
    $entity_type = $this->view->getBaseEntityType();

    $id_field = $this->tableAlias . '.' . $entity_type->getKey('id');

    $capacity = $this->getCapacitySelect();
    $spaces_reserved = "(SELECT SUM(count) FROM {registration} WHERE entity_type_id = :entity_type_id AND entity_id = $id_field AND state IN (:states[]))";
    return "($capacity - $spaces_reserved)";
  }

}
