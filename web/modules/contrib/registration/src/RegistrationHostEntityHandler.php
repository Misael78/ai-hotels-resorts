<?php

namespace Drupal\registration;

use Drupal\Core\Entity\EntityHandlerBase;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a default "registration_host_entity" handler class.
 *
 * Using a handler allows other modules to override the host
 * entity functions and integrate with third party data sources.
 *
 * @see \Drupal\registration\Entity\Registration
 */
class RegistrationHostEntityHandler extends EntityHandlerBase implements RegistrationHostEntityHandlerInterface {

  /**
   * {@inheritdoc}
   */
  public function createHostEntity(EntityInterface $entity, ?string $langcode = NULL): HostEntityInterface {
    if ($entity->getEntityTypeId() === 'registration') {
      @trigger_error("Using the host_entity handler of the registration entity type is deprecated in registration:3.1.5 and is removed from registration:4.0.0. Use the registration_host_entity handler for the host entity type instead. See https://www.drupal.org/node/3462126", E_USER_DEPRECATED);
    }
    return new HostEntity($entity, $langcode);
  }

}
