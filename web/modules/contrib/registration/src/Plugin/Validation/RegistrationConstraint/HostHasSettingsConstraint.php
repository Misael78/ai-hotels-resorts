<?php

namespace Drupal\registration\Plugin\Validation\RegistrationConstraint;

use Drupal\registration\HostEntityInterface;
use Drupal\registration\Validation\RegistrationConstraintBase;

/**
 * Validates that a host entity has settings.
 *
 * @RegistrationConstraint(
 *   id = "HostHasSettings",
 *   label = @Translation("Validates that a host entity has settings", context = "Validation")
 * )
 *
 * @phpcs:disable Drupal.Commenting.VariableComment.Missing
 */
class HostHasSettingsConstraint extends RegistrationConstraintBase {

  /**
   * The host entity passed as a constraint option.
   */
  public ?HostEntityInterface $hostEntity;

  /**
   * Missing host entity.
   */
  public string $noHostEntityMessage = "Missing host entity.";
  public string $noHostEntityCode = "host_entity";
  public string $noHostEntityCause = "No host entity.";

  /**
   * The host entity is not configured for registration.
   */
  public string $disabledMessage = "Registration for %label is disabled.";
  public string $disabledCode = "configuration";
  public string $disabledCause = "Disabled.";

  /**
   * No registration settings.
   */
  public string $noSettingsMessage = "Host entity settings not available for %label.";
  public string $noSettingsCode = "settings";
  public string $noSettingsCause = "No settings.";

}
