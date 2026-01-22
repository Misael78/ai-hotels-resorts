<?php

namespace Drupal\registration;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines an interface for host entity access control handlers.
 */
interface RegistrationHostAccessControlHandlerInterface {

  /**
   * Checks access to an operation on a given host entity.
   *
   * The available operations are:
   * - "view registrations" to view any registration for the host;
   * - "update registrations" to update any registration for the host;
   * - "delete registrations" to delete any registration for the host;
   * - "administer registrations" to administer any registration for the host;
   * - "manage registrations" to view the list of registrations for the host;
   * - "manage settings" to view & update registration settings for the host;
   * - "manage broadcast" to send emails to all registrants for the host;
   * - "register self", "register other users" & "register anonymous" to
   *   register for the host.
   * - "manage" to indicate that the user has generic access to the management
   *   of registrations for the host entity. This typically does not give
   *   access to any particular functionality on its own, but is used as part
   *   of other access checks.
   *
   * @param \Drupal\registration\HostEntityInterface $host_entity
   *   The host entity for which to check access.
   * @param string $operation
   *   The operation access should be checked for.
   *   Usually one of "view", "view label", "update" or "delete".
   * @param \Drupal\Core\Session\AccountInterface $account
   *   (optional) The user session for which to check access, or NULL to check
   *   access for the current user. Defaults to NULL.
   * @param bool $return_as_object
   *   (optional) Defaults to FALSE.
   *
   * @return bool|\Drupal\Core\Access\AccessResultInterface
   *   The access result. Returns a boolean if $return_as_object is FALSE (this
   *   is the default) and otherwise an AccessResultInterface object.
   *   When a boolean is returned, the result of AccessInterface::isAllowed() is
   *   returned, i.e. TRUE means access is explicitly allowed, FALSE means
   *   access is either explicitly forbidden or "no opinion".
   */
  public function access(HostEntityInterface $host_entity, $operation, ?AccountInterface $account = NULL, $return_as_object = FALSE);

  /**
   * Clears all cached access checks.
   */
  public function resetCache();

  /**
   * Sets the module handler for this access control handler.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   *
   * @return $this
   */
  public function setModuleHandler(ModuleHandlerInterface $module_handler);

}
