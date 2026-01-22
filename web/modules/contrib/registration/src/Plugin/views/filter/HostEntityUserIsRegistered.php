<?php

namespace Drupal\registration\Plugin\views\filter;

use Drupal\registration\Plugin\views\UserContextualFilterTrait;
use Drupal\views\Plugin\views\filter\BooleanOperator;

/**
 * Filter on whether a user account is registered for a host entity.
 *
 * This plugin requires a contextual filter containing a User ID. The
 * recommended setup when the host entities are nodes is a content listing at
 * path "/user/%user/content", and a Global: Null contextual filter with User ID
 * validation enabled.
 *
 * @ViewsFilter("host_entity_user_is_registered")
 */
class HostEntityUserIsRegistered extends BooleanOperator {

  use UserContextualFilterTrait;

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    $position = $this->options['user_argument'];
    $uid = $this->view->args[$position] ?? NULL;
    if ($uid && ($entity_type = $this->view->getBaseEntityType())) {
      $this->ensureMyTable();

      $id_field = $this->tableAlias . '.' . $entity_type->getKey('id');

      $expression = "(SELECT 1 FROM {registration} WHERE entity_type_id = :entity_type_id AND entity_id = $id_field AND user_uid = :uid AND state IN (:states[]))";
      $args = [
        ':entity_type_id' => $entity_type->id(),
        ':states[]' => array_filter($this->options['registration_states']),
        ':uid' => $uid,
      ];
      if (!empty($this->value)) {
        $this->query->addWhereExpression($this->options['group'], "EXISTS " . $expression, $args);
      }
      else {
        $this->query->addWhereExpression($this->options['group'], "NOT EXISTS " . $expression, $args);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    $cache_tags = parent::getCacheTags();
    $cache_tags[] = 'registration_list';
    return $cache_tags;
  }

}
