<?php

namespace Drupal\registration\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Validates all aspects of a registration before it is saved.
 *
 * This constraint is invoked by the Entity Validation API.
 *
 * @Constraint(
 *   id = "RegistrationConstraint",
 *   label = @Translation("Validates a registration before it is saved", context = "Validation")
 * )
 *
 * @see \Drupal\registration\Entity\Registration
 * @see https://www.drupal.org/docs/drupal-apis/entity-api/entity-validation-api/entity-validation-api-overview
 */
class RegistrationConstraint extends Constraint {
  // This constraint does not have its own messages, as it executes other
  // constraints to perform its checks.
}
