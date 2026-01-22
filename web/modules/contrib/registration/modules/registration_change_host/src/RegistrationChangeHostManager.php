<?php

namespace Drupal\registration_change_host;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\registration\Entity\RegistrationInterface;
use Drupal\registration\HostEntityInterface;
use Drupal\registration_change_host\Event\RegistrationChangeHostEvents;
use Drupal\registration_change_host\Event\RegistrationChangeHostPossibleHostsEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Entity\EntityFieldManagerInterface;

/**
 * Manages changing the host on registrations.
 */
class RegistrationChangeHostManager implements RegistrationChangeHostManagerInterface {

  use StringTranslationTrait;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * Creates a RegistrationChangeHostManager object.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   */
  public function __construct(EventDispatcherInterface $event_dispatcher, EntityTypeManagerInterface $entity_type_manager, Connection $database, EntityFieldManagerInterface $entity_field_manager) {
    $this->eventDispatcher = $event_dispatcher;
    $this->entityTypeManager = $entity_type_manager;
    $this->database = $database;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getPossibleHosts(RegistrationInterface $registration): PossibleHostSetInterface {
    $event = new RegistrationChangeHostPossibleHostsEvent($registration);
    $this->eventDispatcher->dispatch($event, RegistrationChangeHostEvents::REGISTRATION_CHANGE_HOST_POSSIBLE_HOSTS);
    return $event->getPossibleHostsSet();
  }

  /**
   * {@inheritdoc}
   */
  public function changeHost(RegistrationInterface $registration, string $host_entity_type_id, string|int $host_entity_id, bool $always_clone = FALSE): RegistrationInterface {
    if ($registration->getHostEntity()->getEntityTypeId() === $host_entity_type_id && $registration->getHostEntity()->id() == $host_entity_id) {
      throw new \InvalidArgumentException("Host entity is unchanged.");
    }

    // Establish the old and new registration types.
    $old_host_entity = $registration->getHostEntity();
    $old_registration_type_id = $old_host_entity->getRegistrationType()->id();
    $handler = $this->entityTypeManager->getHandler($host_entity_type_id, 'registration_host_entity');
    $new_host = $this->entityTypeManager->getStorage($host_entity_type_id)->load($host_entity_id);
    $new_host_entity = $handler->createHostEntity($new_host);
    $new_registration_type_id = $new_host_entity->getRegistrationType()->id();

    if ($old_registration_type_id !== $new_registration_type_id) {
      $registration = $this->duplicateRegistration($registration, $new_registration_type_id);
    }
    elseif ($always_clone) {
      $registration = clone $registration;
    }

    $registration->set('entity_type_id', $host_entity_type_id);
    $registration->set('entity_id', $host_entity_id);

    return $registration;
  }

  /**
   * Duplicates a registration as a different type and copy its fields.
   *
   * This copies the existing id. It leaves it marked as 'new' because
   * entity saving depends on that.
   *
   * @param \Drupal\registration\Entity\RegistrationInterface $registration
   *   The registration.
   * @param string $new_registration_type_id
   *   The type of the new registration.
   *
   * @return \Drupal\registration\Entity\RegistrationInterface
   *   The new registration, not yet saved.
   */
  protected function duplicateRegistration(RegistrationInterface $registration, string $new_registration_type_id): RegistrationInterface {
    $entity_type = $this->entityTypeManager->getDefinition('registration');
    $storage = $this->entityTypeManager->getStorage('registration');

    $create_fields = [
      $entity_type->getKey('bundle') => $new_registration_type_id,
      $entity_type->getKey('id') => $registration->id(),
    ];

    /** @var \Drupal\registration\Entity\RegistrationInterface $new_registration */
    $new_registration = $storage->create($create_fields);

    // Exclude computed fields using TRUE parameter.
    $fields = $registration->getFields(FALSE);
    $excluded_fields = array_merge(array_keys($create_fields), [
      // The workflow should be derived from the registration type.
      'workflow',
    ]);

    // Copy field values.
    foreach ($fields as $fieldId => $fieldStorage) {
      if (!in_array($fieldId, $excluded_fields)
          && $new_registration->hasField($fieldId)) {
        $new_registration->set($fieldId, $registration->get($fieldId)->getValue());
      }
    }

    return $new_registration;
  }

  /**
   * {@inheritdoc}
   */
  public function isDataLostWhenHostChanges(RegistrationInterface $registration, string $host_entity_type_id, string|int $host_entity_id, bool $ignore_data = FALSE): bool {
    // Establish the old and new registration types.
    $storage = $this->entityTypeManager->getStorage('registration');
    $original_registration = $storage->loadUnchanged($registration->id());
    $original_host_entity = $original_registration->getHostEntity();
    $original_registration_type_id = $original_host_entity->getRegistrationType()->id();
    $new_host = $this->entityTypeManager->getStorage($host_entity_type_id)->load($host_entity_id);
    $handler = $this->entityTypeManager->getHandler($host_entity_type_id, 'registration_host_entity');
    $new_host_entity = $handler->createHostEntity($new_host);
    $new_registration_type_id = $new_host_entity->getRegistrationType()->id();

    // No chance of data loss if bundle is not changing.
    if ($original_registration_type_id === $new_registration_type_id) {
      return FALSE;
    }

    // Gather the fields excluding computed fields.
    // Exclude computed fields using TRUE parameter.
    $original_field_ids = array_keys($original_registration->getFields(FALSE));
    $new_field_definitions = $this->entityFieldManager->getFieldDefinitions('registration', $new_registration_type_id);
    $new_field_ids = array_keys(array_filter($new_field_definitions, fn($definition) => !$definition->isComputed()));

    // If only fields are being compared and not data, simply check for any
    // fields that are different between the old and new registration type.
    if ($ignore_data) {
      return (!empty(array_diff($original_field_ids, $new_field_ids)) || !empty(array_diff($new_field_ids, $original_field_ids)));
    }

    // If a field is set in the original registration, and missing in the new
    // registration, there is data loss.
    $missing_fields = array_diff($original_field_ids, $new_field_ids);
    foreach ($missing_fields as $field_id) {
      if (!$original_registration->get($field_id)->isEmpty()) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function saveChangedHost(RegistrationInterface $registration, HostEntityInterface $old_host_entity, callable $save_callback): int {
    // Start transaction to preserve data integrity.
    $transaction = $this->database->startTransaction();

    try {
      // If this is a cloned registration of a different type reusing an
      // existing id, then delete the existing registration with that id.
      if ($registration->isNew() && $registration->id()) {
        $old_registration = $this->entityTypeManager->getStorage('registration')->load($registration->id());
        $old_registration->delete();
      }

      // Execute the save callback.
      $result = $save_callback();

      // Invalidate the registration list cache tag for the old host entity. For
      // example, if the old host had no room for new registrations, it may now,
      // and forms that gave an error should rebuild so someone can register.
      Cache::invalidateTags([$old_host_entity->getRegistrationListCacheTag()]);
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }

    // Transaction is committed when it goes out of scope.
    unset($transaction);
    return $result;
  }

}
