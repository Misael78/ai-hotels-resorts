<?php

namespace Drupal\registration\Plugin\Validation\RegistrationConstraint;

use Drupal\registration\Validation\RegistrationConstraintBase;

/**
 * Validates that a registration allows a registrant.
 *
 * @RegistrationConstraint(
 *   id = "RegistrationAllowsRegistrant",
 *   label = @Translation("Validates that a registration allows a registrant", context = "Validation")
 * )
 *
 * @phpcs:disable Drupal.Commenting.VariableComment.Missing
 */
class RegistrationAllowsRegistrantConstraint extends RegistrationConstraintBase {

  /**
   * This constraint requires a host entity with settings.
   */
  protected array $dependencies = ['HostHasSettings'];

  /**
   * Registrant is not allowed.
   */
  public string $selfMessage = "You are not allowed to register yourself.";
  public string $otherMessage = "You are not allowed to register other users.";
  public string $otherAnonymousMessage = "You are not allowed to register other people.";
  public string $registrantNotAllowedCode = "registrant_not_allowed";
  public string $registrantNotAllowedCause = "Not allowed.";

  /**
   * Missing registrant.
   */
  public string $registrantRequiredMessage = "You must specify who is registering.";
  public string $registrantRequiredCode = "registrant_required";
  public string $registrantRequiredCause = "Missing registrant.";

}
