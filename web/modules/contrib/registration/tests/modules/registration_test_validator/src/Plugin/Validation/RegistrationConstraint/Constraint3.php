<?php

namespace Drupal\registration_test_validator\Plugin\Validation\RegistrationConstraint;

use Drupal\registration\Validation\RegistrationConstraintBase;

/**
 * Validates constraint 3.
 *
 * @RegistrationConstraint(
 *   id = "Constraint3",
 *   label = @Translation("Validates constraint 3", context = "Validation")
 * )
 */
class Constraint3 extends RegistrationConstraintBase {

  /**
   * This constraint relies on constraint 1.
   */
  protected array $dependencies = ['Constraint1'];

}
