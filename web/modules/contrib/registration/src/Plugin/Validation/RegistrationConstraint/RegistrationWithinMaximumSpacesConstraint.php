<?php

namespace Drupal\registration\Plugin\Validation\RegistrationConstraint;

use Drupal\registration\HostEntityInterface;
use Drupal\registration\Validation\RegistrationConstraintBase;

/**
 * Validates that a registration does not exceed the maximum spaces setting.
 *
 * @RegistrationConstraint(
 *   id = "RegistrationWithinMaximumSpaces",
 *   label = @Translation("Validates that a registration does not exceed the maximum spaces setting", context = "Validation")
 * )
 *
 * @phpcs:disable Drupal.Commenting.VariableComment.Missing
 */
class RegistrationWithinMaximumSpacesConstraint extends RegistrationConstraintBase {

  /**
   * This constraint requires a host entity with settings.
   */
  protected array $dependencies = ['HostHasSettings'];

  /**
   * The host entity passed as a constraint option.
   */
  public ?HostEntityInterface $hostEntity;

  /**
   * The number of spaces passed as a constraint option.
   */
  public ?int $spaces;

  /**
   * Exceeds maximum spaces per registration.
   */
  public string $tooManySpacesMessage = "You may not register for more than 1 space.|You may not register for more than @count spaces.";
  public string $tooManySpacesCode = "maximum_spaces";
  public string $tooManySpacesCause = "Too many spaces requested.";

}
