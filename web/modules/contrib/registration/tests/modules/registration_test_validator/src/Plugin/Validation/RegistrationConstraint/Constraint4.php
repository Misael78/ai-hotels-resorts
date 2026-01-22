<?php

namespace Drupal\registration_test_validator\Plugin\Validation\RegistrationConstraint;

use Drupal\registration\Validation\RegistrationConstraintBase;

/**
 * Validates constraint 4.
 *
 * @RegistrationConstraint(
 *   id = "Constraint4",
 *   label = @Translation("Validates constraint 4", context = "Validation")
 * )
 */
class Constraint4 extends RegistrationConstraintBase {

  /**
   * This constraint relies on constraint 1.
   */
  protected array $dependencies = ['Constraint1'];

}
