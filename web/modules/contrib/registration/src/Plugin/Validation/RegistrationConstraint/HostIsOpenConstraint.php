<?php

namespace Drupal\registration\Plugin\Validation\RegistrationConstraint;

use Drupal\registration\HostEntityInterface;
use Drupal\registration\Validation\RegistrationConstraintBase;

/**
 * Validates that a host entity is open for registration.
 *
 * @RegistrationConstraint(
 *   id = "HostIsOpen",
 *   label = @Translation("Validates that a host entity is open for registration", context = "Validation")
 * )
 *
 * @phpcs:disable Drupal.Commenting.VariableComment.Missing
 */
class HostIsOpenConstraint extends RegistrationConstraintBase {

  /**
   * This constraint requires a host entity with settings.
   */
  protected array $dependencies = ['HostHasSettings'];

  /**
   * The host entity passed as a constraint option.
   */
  public ?HostEntityInterface $hostEntity;

  /**
   * Registration is not open yet.
   */
  public string $notOpenYetMessage = "Registration for %label is not open yet.";
  public string $notOpenYetCode = "open";
  public string $notOpenYetCause = "Not open yet.";

  /**
   * Registration is already closed.
   */
  public string $closedMessage = "Registration for %label is closed.";
  public string $closedCode = "close";
  public string $closedCause = "Closed.";

}
