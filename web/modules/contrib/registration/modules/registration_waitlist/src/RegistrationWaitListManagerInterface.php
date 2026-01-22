<?php

namespace Drupal\registration_waitlist;

use Drupal\registration\Entity\RegistrationTypeInterface;

/**
 * Defines the interface for the registration wait list manager service.
 */
interface RegistrationWaitListManagerInterface {

  /**
   * Automatically fills spots in standard capacity from the wait list.
   *
   * @param \Drupal\registration_waitlist\HostEntityInterface $host_entity
   *   The host entity to automatically fill.
   */
  public function autoFill(HostEntityInterface $host_entity);

  /**
   * Gets the autofill field options for a given registration type.
   *
   * @param \Drupal\registration\Entity\RegistrationTypeInterface $registration_type
   *   The registration type.
   *
   * @return array
   *   The options as an array of field titles indexed by field machine name.
   */
  public function getAutoFillSortFieldOptions(RegistrationTypeInterface $registration_type): array;

}
