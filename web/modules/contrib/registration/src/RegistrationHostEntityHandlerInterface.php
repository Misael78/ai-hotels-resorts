<?php

namespace Drupal\registration;

use Drupal\Core\Entity\EntityInterface;

/**
 * Defines the interface for the "registration_host_entity" handler.
 *
 * Using a handler allows other modules to override the host
 * entity functions and integrate with third party data sources.
 *
 * @see \Drupal\registration\Entity\Registration
 */
interface RegistrationHostEntityHandlerInterface {

  /**
   * Creates a host entity object given a real entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The real entity.
   * @param string|null $langcode
   *   (optional) The language the host entity should use.
   *
   * @return \Drupal\registration\HostEntityInterface
   *   The host entity. This is a wrapper and not a real entity.
   */
  public function createHostEntity(EntityInterface $entity, ?string $langcode = NULL): HostEntityInterface;

}
