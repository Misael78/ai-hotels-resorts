<?php

namespace Drupal\registration\Plugin\Validation\RegistrationConstraint;

use Drupal\registration\Validation\RegistrationConstraintBase;

/**
 * Validates that a registration does not exceed the capacity setting.
 *
 * @RegistrationConstraint(
 *   id = "RegistrationWithinCapacity",
 *   label = @Translation("Validates that a registration does not exceed the capacity setting", context = "Validation")
 * )
 *
 * @phpcs:disable Drupal.Commenting.VariableComment.Missing
 */
class RegistrationWithinCapacityConstraint extends RegistrationConstraintBase {

  /**
   * This constraint requires a host entity with settings.
   */
  protected array $dependencies = ['HostHasSettings'];

  /**
   * Would exceed event capacity.
   */
  public string $noRoomMessage = "Sorry, unable to register for %label due to: insufficient spaces remaining.";
  public string $noRoomCode = "capacity";
  public string $noRoomCause = "No room.";

}
