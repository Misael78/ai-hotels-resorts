<?php

namespace Drupal\registration_cancel_by\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Prevents an invalid "cancel by" date from being saved.
 *
 * @Constraint(
 *   id = "CancelByConstraint",
 *   label = @Translation("Enforce constraints on the registration settings cancel by date", context = "Validation")
 * )
 */
class CancelByConstraint extends Constraint {

  /**
   * Cancel by before open.
   *
   * @var string
   */
  public string $beforeOpenMessage = "The cancel by date cannot be before the open date.";

}
