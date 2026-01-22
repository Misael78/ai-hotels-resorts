<?php

namespace Drupal\registration_change_host;

use Drupal\registration\Entity\RegistrationInterface;
use Drupal\registration\HostEntityInterface;

/**
 * Defines the interface for the registration change host manager service.
 */
interface RegistrationChangeHostManagerInterface {

  /**
   * Gets the possible hosts for a registration.
   *
   * Returns an associative array of possible hosts where the key is type:id
   * and the values are are PossibleHostEntity entities.
   *
   * @param \Drupal\registration\Entity\RegistrationInterface $registration
   *   The registration.
   *
   * @return \Drupal\registration_change_host\PossibleHostSetInterface
   *   A set of possible hosts for the registration.
   */
  public function getPossibleHosts(RegistrationInterface $registration): PossibleHostSetInterface;

  /**
   * Change the registration host.
   *
   * The returned registration is unsaved. If the new host uses a different
   * registration type, the existing registration is cloned. The calling code
   * should delete the old registration before saving the new registration.
   *
   * This changes the host on the registration object, and therefore can
   * have unintended side effects in calling code. To avoid these, for example
   * when evaluating a hypothetical change of host, use the $always_clone
   * option which always returns a cloned object without modifying the
   * passed-in object.
   *
   * @param \Drupal\registration\Entity\RegistrationInterface $registration
   *   The registration.
   * @param string $host_entity_type_id
   *   The new host entity type id.
   * @param string|int $host_entity_id
   *   The new host entity id.
   * @param bool $always_clone
   *   Whether to always clone the registration even if same type.
   *
   * @return \Drupal\registration\Entity\RegistrationInterface
   *   A registration with the new host, unsaved.
   */
  public function changeHost(RegistrationInterface $registration, string $host_entity_type_id, string|int $host_entity_id, bool $always_clone = FALSE): RegistrationInterface;

  /**
   * Determines if data will be lost when changing the host.
   *
   * Data is lost when there is a non-empty field on the registration
   * that is not present on the new registration type.
   *
   * @param \Drupal\registration\Entity\RegistrationInterface $registration
   *   The current registration.
   * @param string $host_entity_type_id
   *   The type of the new host entity.
   * @param string|int $host_entity_id
   *   The id of the new host entity.
   * @param bool $ignore_data
   *   (Optional) Whether to check only for a field mismatch. Defaults to FALSE.
   *   If this parameter is skipped or set to FALSE, the registration is checked
   *   for non-empty values in any fields that are missing on the new
   *   registration type, and TRUE is returned if any are found. If this
   *   parameter is set to TRUE, and there any field differences between the old
   *   and new registration types, then TRUE is returned regardless of whether
   *   the registration has data in missing fields or not.
   *
   * @return bool
   *   TRUE if data will be lost, FALSE otherwise.
   */
  public function isDataLostWhenHostChanges(RegistrationInterface $registration, string $host_entity_type_id, string|int $host_entity_id, bool $ignore_data = FALSE): bool;

  /**
   * Saves a registration and deletes any old registration with same id.
   *
   * This attempts to rollback the deletion if the subsequent save fails.
   *
   * @param \Drupal\registration\Entity\RegistrationInterface $registration
   *   The registration to save.
   * @param \Drupal\registration\HostEntityInterface $old_host_entity
   *   The old host entity, this is needed for cache invalidation.
   * @param callable $save_callback
   *   The callback that performs the actual save operation.
   *
   * @return int
   *   SAVED_NEW or SAVED_UPDATED depending on the operation performed.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   In case of failures an exception is thrown.
   */
  public function saveChangedHost(RegistrationInterface $registration, HostEntityInterface $old_host_entity, callable $save_callback): int;

}
