<?php

namespace Drupal\registration\Plugin\Validation\RegistrationConstraint;

use Drupal\Core\Session\AccountInterface;
use Drupal\registration\Validation\RegistrationConstraintBase;

/**
 * Validates that changes to a registration are allowed for a given account.
 *
 * @RegistrationConstraint(
 *   id = "RegistrationAllowsUpdate",
 *   label = @Translation("Validates that changes to a registration are allowed for a given account", context = "Validation")
 * )
 *
 * @phpcs:disable Drupal.Commenting.VariableComment.Missing
 */
class RegistrationAllowsUpdateConstraint extends RegistrationConstraintBase {

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
  public string $disabledSpacesMessage = "The number of spaces cannot be increased because registration for %label is disabled.";
  public string $disabledSpacesCode = "status_count";
  public string $disabledSpacesCause = "Disabled.";
  public string $disabledStatusMessage = "The status cannot be changed because registration for %label is disabled.";
  public string $disabledStatusCode = "status_status";
  public string $disabledStatusCause = "Disabled.";
  public string $disabledRegistrantMessage = "The registrant cannot be changed because registration for %label is disabled.";
  public string $disabledRegistrantCode = "status_registrant";
  public string $disabledRegistrantCause = "Disabled.";

  /**
   * Registration is closed.
   */
  public string $closedSpacesMessage = "The number of spaces cannot be increased because registration for %label is closed.";
  public string $closedSpacesCode = "close_count";
  public string $closedSpacesCause = "Closed.";
  public string $closedStatusMessage = "The status cannot be changed because registration for %label is closed.";
  public string $closedStatusCode = "close_status";
  public string $closedStatusCause = "Closed.";
  public string $closedRegistrantMessage = "The registrant cannot be changed because registration for %label is closed.";
  public string $closedRegistrantCode = "close_registrant";
  public string $closedRegistrantCause = "Disabled.";

}
