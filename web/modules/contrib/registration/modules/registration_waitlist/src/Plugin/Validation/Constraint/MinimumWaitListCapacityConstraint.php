<?php

namespace Drupal\registration_waitlist\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Prevents wait list capacity less than the number of wait list registrations.
 *
 * @Constraint(
 *   id = "MinimumWaitListCapacity",
 *   label = @Translation("Enforce minimum registration settings wait list capacity", context = "Validation")
 * )
 */
class MinimumWaitListCapacityConstraint extends Constraint {

  /**
   * Violation message.
   *
   * @var string
   */
  public string $message = "The minimum wait list capacity for this @type is @capacity due to existing wait listed registrations.";

}
