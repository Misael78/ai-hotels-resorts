<?php

namespace Drupal\registration\Plugin\Field;

use Drupal\registration\HostEntityInterface;

/**
 * Defines the interface for an item list class for registration fields.
 */
interface RegistrationItemFieldItemListInterface {

  /**
   * Creates a host entity object.
   *
   * @param string|null $langcode
   *   (optional) The language the host entity should use.
   *
   * @return \Drupal\registration\HostEntityInterface
   *   The host entity. This is a wrapper and not a real entity.
   */
  public function createHostEntity(?string $langcode = NULL): HostEntityInterface;

}
