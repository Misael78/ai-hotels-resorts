<?php

namespace Drupal\registration\Plugin\Validation\RegistrationConstraint;

use Drupal\registration\HostEntityInterface;
use Drupal\registration\Validation\RegistrationConstraintBase;

/**
 * Validates that a host entity has room for a new registration.
 *
 * @RegistrationConstraint(
 *   id = "HostHasRoom",
 *   label = @Translation("Validates that a host entity has room for a new registration", context = "Validation")
 * )
 *
 * @phpcs:disable Drupal.Commenting.VariableComment.Missing
 */
class HostHasRoomConstraint extends RegistrationConstraintBase {

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
   * Would exceed event capacity.
   */
  public string $noRoomMessage = "Sorry, unable to register for %label due to: insufficient spaces remaining.";
  public string $noRoomCode = "capacity";
  public string $noRoomCause = "No room.";

}
