<?php

namespace Drupal\registration\Plugin\Validation\RegistrationConstraint;

use Drupal\registration\HostEntityInterface;
use Drupal\registration\Validation\RegistrationConstraintBase;

/**
 * Validates that a host entity is enabled for registration in settings.
 *
 * @RegistrationConstraint(
 *   id = "HostIsEnabled",
 *   label = @Translation("Validates that a host entity is enabled for registration in settings", context = "Validation")
 * )
 *
 * @phpcs:disable Drupal.Commenting.VariableComment.Missing
 */
class HostIsEnabledConstraint extends RegistrationConstraintBase {

  /**
   * This constraint requires a host entity with settings.
   */
  protected array $dependencies = ['HostHasSettings'];

  /**
   * The host entity passed as a constraint option.
   */
  public ?HostEntityInterface $hostEntity;

  /**
   * The host entity is disabled.
   */
  public string $disabledMessage = "Registration for %label is disabled.";
  public string $disabledCode = "status";
  public string $disabledCause = "Disabled.";

}
