<?php

namespace Drupal\registration_test_validator\Plugin\Validation\RegistrationConstraint;

use Drupal\registration\Validation\RegistrationConstraintBase;

/**
 * Validates constraint 1.
 *
 * @RegistrationConstraint(
 *   id = "Constraint1",
 *   label = @Translation("Validates constraint 1", context = "Validation")
 * )
 */
class Constraint1 extends RegistrationConstraintBase {}
