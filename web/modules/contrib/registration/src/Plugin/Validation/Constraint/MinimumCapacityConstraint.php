<?php

namespace Drupal\registration\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Prevents event capacity less than the number of existing registrations.
 *
 * @Constraint(
 *   id = "MinimumCapacity",
 *   label = @Translation("Enforce minimum registration settings capacity", context = "Validation")
 * )
 */
class MinimumCapacityConstraint extends Constraint {

  /**
   * Violation message.
   *
   * @var string
   */
  public string $message = "The minimum capacity for this @type is @capacity due to existing registrations.";

}
