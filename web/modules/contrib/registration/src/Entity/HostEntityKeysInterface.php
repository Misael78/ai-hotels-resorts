<?php

namespace Drupal\registration\Entity;

/**
 * Defines the interface for retrieving host entity keys.
 */
interface HostEntityKeysInterface {

  /**
   * Gets the entity ID of the host entity.
   *
   * @return int
   *   The host entity ID.
   */
  public function getHostEntityId(): int|string|NULL;

  /**
   * Gets the entity type ID of the host entity.
   *
   * @return string
   *   The host entity type ID, for example "node".
   */
  public function getHostEntityTypeId(): string;

  /**
   * Gets the registration language code.
   *
   * This is the site language in use at the time of the registration. For
   * example, if the site visitor registered on page /es/node/1, then the
   * language code would be "es" (Spanish).
   *
   * @return string|null
   *   The language code, if available.
   */
  public function getLangcode(): ?string;

}
