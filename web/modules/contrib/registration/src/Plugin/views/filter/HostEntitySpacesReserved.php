<?php

namespace Drupal\registration\Plugin\views\filter;

/**
 * Filter on the number of reserved registration spaces for the host entity.
 *
 * This filter might not be accurate if custom event subscribers alter the
 * standard calculation of spaces reserved. For example, if an application
 * uses the REGISTRATION_ALTER_USAGE event, the logic in that subscriber
 * is not applied here, and the filter might not provide results that match
 * the output of the associated views field plugin.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("host_entity_spaces_reserved")
 */
class HostEntitySpacesReserved extends HostEntityFilterBase {

  /**
   * Gets the SQL SELECT for host entity spaces reserved.
   *
   * @return string
   *   The SELECT statement.
   *
   * @see \Drupal\registration\HostEntity::getActiveSpacesReserved()
   */
  protected function getMainSelect(): string {
    $entity_type = $this->view->getBaseEntityType();

    $id_field = $this->tableAlias . '.' . $entity_type->getKey('id');

    return "(SELECT SUM(count) FROM {registration} WHERE entity_type_id = :entity_type_id AND entity_id = $id_field AND state IN (:states[]))";
  }

}
