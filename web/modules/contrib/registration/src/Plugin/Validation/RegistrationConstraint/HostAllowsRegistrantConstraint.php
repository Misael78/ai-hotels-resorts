<?php

namespace Drupal\registration\Plugin\Validation\RegistrationConstraint;

use Drupal\registration\Validation\RegistrationConstraintBase;

/**
 * Validates that a host entity allows the current user as a registrant.
 *
 * @RegistrationConstraint(
 *   id = "HostAllowsRegistrant",
 *   label = @Translation("Validates that a host entity allows the current user as a registrant.", context = "Validation")
 * )
 *
 * @phpcs:disable Drupal.Commenting.VariableComment.Missing
 */
class HostAllowsRegistrantConstraint extends RegistrationConstraintBase {

  /**
   * This constraint requires a host entity with settings.
   */
  protected array $dependencies = ['HostHasSettings'];

  /**
   * You are already registered.
   */
  public string $youAreAlreadyRegisteredMessage = "You are already registered for this event.";
  public string $youAreAlreadyRegisteredCode = "user";
  public string $youAreAlreadyRegisteredCause = "Already registered.";

}
