<?php

namespace Drupal\registration\Plugin\Validation\RegistrationConstraint;

use Drupal\registration\Validation\RegistrationConstraintBase;

/**
 * Validates that a person has not already registered.
 *
 * This constraint is not enforced when the host entity settings allow multiple
 * registrations per person.
 *
 * @RegistrationConstraint(
 *   id = "UniqueRegistrant",
 *   label = @Translation("Validates that a person has not already registered", context = "Validation")
 * )
 *
 * @phpcs:disable Drupal.Commenting.VariableComment.Missing
 */
class UniqueRegistrantConstraint extends RegistrationConstraintBase {

  /**
   * This constraint requires a host entity with settings.
   */
  protected array $dependencies = ['HostHasSettings'];

  /**
   * Email address is already registered.
   */
  public string $emailAlreadyRegisteredMessage = "%mail is already registered for this event.";
  public string $emailAlreadyRegisteredCode = "email";
  public string $emailAlreadyRegisteredCause = "Email already registered.";

  /**
   * You are already registered.
   */
  public string $youAreAlreadyRegisteredMessage = "You are already registered for this event.";
  public string $youAreAlreadyRegisteredCode = "user";
  public string $youAreAlreadyRegisteredCause = "Already registered.";

  /**
   * User is already registered.
   */
  public string $userAlreadyRegisteredMessage = "%user is already registered for this event.";
  public string $userAlreadyRegisteredCode = "user";
  public string $userAlreadyRegisteredCause = "User already registered.";

}
