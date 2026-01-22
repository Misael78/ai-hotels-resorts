<?php

namespace Drupal\registration;

/**
 * Defines the interface for a registration validator.
 */
interface RegistrationValidatorInterface {

  /**
   * Executes a validation pipeline against one or more constraints.
   *
   * @param string $pipeline_id
   *   An identifier for the constraint pipeline.
   * @param string|array $constraint_ids
   *   A constraint plugin ID, or a list of constraint plugin IDs. Constraints
   *   with options can be passed as follows:
   *   ['MyConstraintName' => ['my_option' => $my_option_value]]
   *   Other forms for this parameter:
   *   A single constraint name: 'Constraint1'.
   *   An array of constraint names: ['Constraint1', 'Constraint2'].
   * @param mixed $value
   *   The value to validate, e.g. an entity or other object. This is most
   *   often a registration entity, but can be any value or object relevant
   *   to registrations.
   *
   * @return \Drupal\registration\RegistrationValidationResultInterface
   *   The result of the validation check.
   */
  public function execute(string $pipeline_id, string|array $constraint_ids, mixed $value): RegistrationValidationResultInterface;

}
