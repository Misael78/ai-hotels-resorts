<?php

namespace Drupal\registration_waitlist;

use Drupal\Core\Entity\EntityInterface;
use Drupal\registration\RegistrationHostEntityHandler;

/**
 * Extends the "host_entity" handler class for registrations.
 *
 * Placing this class file in the same folder as the custom host entity class
 * allows the base host entity class to be extended.
 */
class HostEntityHandler extends RegistrationHostEntityHandler {

  /**
   * {@inheritdoc}
   */
  public function createHostEntity(EntityInterface $entity, ?string $langcode = NULL): HostEntityInterface {
    return new HostEntity($entity, $langcode);
  }

}
