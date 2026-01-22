<?php

namespace Drupal\registration\Validation;

use Symfony\Component\Validator\Constraint;

/**
 * Provides a base class for registration constraints.
 *
 * Constraints using this class can define dependencies, and these dependencies
 * can be checked by the registration validator. The purpose of the dependency
 * check is to ensure that dependent constraints are executed first. This allows
 * constraint validators that come later in the execution pipeline to avoid
 * performing redundant checks, and reduces code duplication.
 *
 * @see Drupal\registration\RegistrationValidator
 */
abstract class RegistrationConstraintBase extends Constraint implements RegistrationConstraintInterface {

  /**
   * The constraints that this constraint depends on.
   */
  protected array $dependencies = [];

  /**
   * {@inheritdoc}
   */
  public function getDependencies(): array {
    return $this->dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function hasUnmetDependencies(string $constraint_id, array $pipeline): bool {
    $dependencies = $this->getDependencies();
    if (!empty($dependencies)) {
      $constraint_ids = array_keys($pipeline);
      $this_constraint_index = array_search($constraint_id, $constraint_ids);

      foreach ($dependencies as $dependency) {
        $dependency_constraint_index = array_search($dependency, $constraint_ids);
        if ($dependency_constraint_index === FALSE) {
          // The dependency is not part of the active pipeline.
          return TRUE;
        }
        if ($dependency_constraint_index > $this_constraint_index) {
          // The dependency is in the active pipeline, but it is executed after
          // the constraint, which is too late.
          return TRUE;
        }
      }
    }
    return FALSE;
  }

}
