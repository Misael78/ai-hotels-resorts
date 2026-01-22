<?php

namespace Drupal\registration\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks new registration fields for errors.
 *
 * @Constraint(
 *   id = "registration_field",
 *   label = @Translation("Registration field", context = "Validation")
 * )
 */
class RegistrationFieldConstraint extends Constraint {

  /**
   * If the user tries to add two registration fields to the same bundle.
   *
   * @var string
   */
  public string $disallowedCardinalityMessage = 'An entity can only have one registration field';

  /**
   * If the user tries to add a registration field to a registration type.
   *
   * @var string
   */
  public string $disallowedTargetTypeMessage = 'A registration field cannot be added to a registration type';

  /**
   * If the user tries to add a registration field to registration settings.
   *
   * @var string
   */
  public string $disallowedTargetSettingsMessage = 'A registration field cannot be added to registration settings';

  /**
   * If the target entity type does not have an "id" key in its annotation.
   *
   * The "id" key is required for views relationships to work for registrations.
   * It would be highly unusual if a content entity type did not have one.
   *
   * @var string
   */
  public string $missingIdKeyMessage = 'A registration field can only be added to an entity with an "id" key';

}
