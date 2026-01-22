<?php

namespace Drupal\registration\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Prevents invalid confirmation redirects from being saved.
 *
 * @Constraint(
 *   id = "RedirectConstraint",
 *   label = @Translation("Ensure confirmation redirects are valid", context = "Validation")
 * )
 */
class RedirectConstraint extends Constraint {

  /**
   * Violation message.
   *
   * @var string
   */
  public string $message = "Confirmation redirect path must be a valid URL. Internal paths must start with a forward slash.";

}
