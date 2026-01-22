<?php

namespace Drupal\registration_purger;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\registration\HostEntityInterface;
use Drupal\registration\RegistrationManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service to coordinate the purging of registration related entities.
 */
class RegistrationPurger {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * Registration manager.
   *
   * @var \Drupal\registration\RegistrationManagerInterface
   */
  protected RegistrationManagerInterface $registrationManager;

  /**
   * Constructs a new RegistrationPurger.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\registration\RegistrationManagerInterface $registration_manager
   *   Registration manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, LoggerInterface $logger, RegistrationManagerInterface $registration_manager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->logger = $logger;
    $this->registrationManager = $registration_manager;
  }

  /**
   * Respond to entity deletion.
   *
   * Check if this entity has associated registration data, and purges it if
   * it does.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being deleted.
   *
   * @see hook_entity_delete()
   */
  public function onEntityDelete(EntityInterface $entity): void {
    if (!$this->isApplicable($entity)) {
      return;
    }

    /** @var \Drupal\registration\HostEntityInterface $host_entity */
    $host_entity = $this->entityTypeManager
      ->getHandler($entity->getEntityTypeId(), 'registration_host_entity')
      ->createHostEntity($entity);

    $this->purgeSettings($host_entity);
    $this->purgeRegistrations($host_entity);
  }

  /**
   * Check if registration data should be purged for an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being deleted.
   *
   * @return bool
   *   TRUE if the purger should act on this entity.
   */
  protected function isApplicable(EntityInterface $entity): bool {
    return $this->registrationManager->hasRegistrationField($entity->getEntityType(), $entity->bundle());
  }

  /**
   * Purge the settings entity.
   *
   * @param \Drupal\registration\HostEntityInterface $host_entity
   *   The host entity wrapper for the entity being deleted.
   */
  protected function purgeSettings(HostEntityInterface $host_entity): void {
    $settings_storage = $this->entityTypeManager->getStorage('registration_settings');
    $settings = $settings_storage->loadByProperties([
      'entity_type_id' => $host_entity->getEntityTypeId(),
      'entity_id' => $host_entity->id(),
    ]);

    if (empty($settings)) {
      return;
    }

    $settings_storage->delete($settings);
    $this->logger->notice('Purged registration settings entity for %type %id', [
      '%type' => $host_entity->getEntityTypeId(),
      '%id' => $host_entity->id(),
    ]);
  }

  /**
   * Purge the registration entities.
   *
   * @param \Drupal\registration\HostEntityInterface $host_entity
   *   The host entity wrapper for the entity being deleted.
   */
  protected function purgeRegistrations(HostEntityInterface $host_entity): void {
    $registration_storage = $this->entityTypeManager->getStorage('registration');
    $registrations = $registration_storage->loadByProperties([
      'entity_type_id' => $host_entity->getEntityTypeId(),
      'entity_id' => $host_entity->id(),
    ]);

    if (empty($registrations)) {
      return;
    }

    $registration_storage->delete($registrations);
    $this->logger->notice('Purged registration entities (%count) for %type %id', [
      '%count' => count($registrations),
      '%type' => $host_entity->getEntityTypeId(),
      '%id' => $host_entity->id(),
    ]);
  }

}
