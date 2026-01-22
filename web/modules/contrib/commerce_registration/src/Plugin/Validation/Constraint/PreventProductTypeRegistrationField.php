<?php

namespace Drupal\commerce_registration\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Prevents adding a registration field to a product type.
 *
 * @Constraint(
 *   id = "PreventProductTypeRegistrationField",
 *   label = @Translation("Prevent product type registration field", context = "Validation")
 * )
 */
class PreventProductTypeRegistrationField extends Constraint {

  /**
   * If the user tries to add a registration field to a product type.
   *
   * @var string
   */
  public string $message = 'A registration field cannot be added to a product type. Add to a product variation type instead.';

}
