<?php

/**
 * @file
 * Hooks related to registration.
 */

use Drupal\Core\Access\AccessResult;

/**
 * Control host entity operation access.
 *
 * This hook controls access to special registration operations on registration
 * host entities. The available operations are:
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
 *   The host entity to check access to.
 * @param string $operation
 *   The operation that is to be performed on $host_entity.
 * @param \Drupal\Core\Session\AccountInterface $account
 *   The account trying to access the host entity.
 *
 * @return \Drupal\Core\Access\AccessResultInterface
 *   The access result. The final result is calculated by using
 *   \Drupal\Core\Access\AccessResultInterface::orIf() on the result of every
 *   hook_registration_host__access() implementation, and the
 *   result of the entity-specific checkAccess() method in the host entity
 *   access control handler.
 *
 * @see \Drupal\registration\RegistrationHostAccessControlHandler
 *
 * @ingroup registration_api
 */
function hook_registration_host__access(\Drupal\registration\HostEntityInterface $host_entity, $operation, \Drupal\Core\Session\AccountInterface $account) {
  // No opinion.
  return AccessResult::neutral();
}
