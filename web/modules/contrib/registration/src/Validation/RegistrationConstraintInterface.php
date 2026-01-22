<?php

namespace Drupal\registration\Validation;

/**
 * Defines the interface for a registration constraint.
 */
interface RegistrationConstraintInterface {

  /**
   * Gets the dependencies for the constraint.
   *
   * @return array
   *   The dependencies as a list of constraint plugin IDs.
   */
  public function getDependencies(): array;

  /**
   * Determines if a constraint has unmet dependencies.
   *
   * An unmet dependency exists when the constraint is executed before its
   * dependencies in an execution pipeline, or if any of its dependencies are
   * not included in the pipeline at all. This results in less code duplication,
   * avoiding the need to repeat data consistency checks in each constraint.
   * For example, many registration constraints rely on the HostEntity
   * constraint to ensure that a host entity and its settings exist. Without
   * the dependency mechanism, each constraint would need to embed these
   * checks separately to prevent null pointer exceptions.
   *
   * @param string $constraint_id
   *   The plugin ID of the constraint.
   * @param array $pipeline
   *   The current execution pipeline, as a list of constraint plugin IDs.
   *
   * @return bool
   *   TRUE if there are unmet dependencies, FALSE otherwise.
   */
  public function hasUnmetDependencies(string $constraint_id, array $pipeline): bool;

}
