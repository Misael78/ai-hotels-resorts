<?php

namespace Drupal\registration_test_validator\Plugin\Validation\RegistrationConstraint;

use Drupal\registration\Validation\RegistrationConstraintBase;

/**
 * Validates constraint 2.
 *
 * @RegistrationConstraint(
 *   id = "Constraint2",
 *   label = @Translation("Validates constraint 2", context = "Validation")
 * )
 */
class Constraint2 extends RegistrationConstraintBase {

  /**
   * This constraint relies on constraint 1.
   */
  protected array $dependencies = ['Constraint1'];

}
