<?php

namespace Drupal\registration\Plugin\Validation\RegistrationConstraint;

use Drupal\Core\Session\AccountInterface;
use Drupal\registration\Validation\RegistrationConstraintBase;

/**
 * Validates that a registration can be edited by an account.
 *
 * @RegistrationConstraint(
 *   id = "RegistrationIsEditable",
 *   label = @Translation("Validates that a registration can be edited by an account", context = "Validation")
 * )
 *
 * @phpcs:disable Drupal.Commenting.VariableComment.Missing
 */
class RegistrationIsEditableConstraint extends RegistrationConstraintBase {

  /**
   * This constraint requires a host entity with settings.
   */
  protected array $dependencies = ['HostHasSettings'];

  /**
   * The account passed as a constraint option.
   */
  public ?AccountInterface $account;

  /**
   * Registration is disabled.
   */
  public string $disabledMessage = "Registration for %label is disabled.";
  public string $disabledCode = "status";
  public string $disabledCause = "Disabled.";

  /**
   * Registration is closed.
   */
  public string $closedMessage = "Registration for %label is closed.";
  public string $closedCode = "close";
  public string $closedCause = "Closed.";

}
