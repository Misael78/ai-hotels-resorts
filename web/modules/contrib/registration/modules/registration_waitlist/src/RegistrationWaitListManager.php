<?php

namespace Drupal\registration_waitlist;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\registration\Entity\RegistrationInterface;
use Drupal\registration\Entity\RegistrationTypeInterface;
use Drupal\registration\Event\RegistrationEvent;
use Drupal\registration_waitlist\Event\RegistrationWaitListEvents;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Defines the class for the registration wait list manager service.
 */
class RegistrationWaitListManager implements RegistrationWaitListManagerInterface {

  use StringTranslationTrait;

  /**
   * The entity field manager.
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * The event dispatcher.
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * The logger.
   */
  protected LoggerInterface $logger;

  /**
   * Creates a RegistrationWaitListManager object.
   *
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   */
  public function __construct(EventDispatcherInterface $event_dispatcher, LoggerInterface $logger, EntityFieldManagerInterface $entity_field_manager) {
    $this->entityFieldManager = $entity_field_manager;
    $this->eventDispatcher = $event_dispatcher;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public function autoFill(HostEntityInterface $host_entity) {
    $spaces_to_fill = $host_entity->getSpacesRemaining();
    if ($spaces_to_fill && $host_entity->isAvailableForRegistration()) {
      if ($new_state = $host_entity->getSetting('registration_waitlist_autofill_state')) {
        $count = 0;
        $spaces_filled = 0;
        $wait_listed_registrations = $host_entity->getRegistrationList(['waitlist']);
        uasort($wait_listed_registrations, [$this, 'sortRegistrations']);
        foreach ($wait_listed_registrations as $registration) {
          if ($host_entity->hasRoomOffWaitList($registration->getSpacesReserved())) {
            $event = new RegistrationEvent($registration);
            $this->eventDispatcher->dispatch($event, RegistrationWaitListEvents::REGISTRATION_WAITLIST_PREAUTOFILL);
            $registration->set('state', $new_state);
            $registration->save();
            $this->eventDispatcher->dispatch($event, RegistrationWaitListEvents::REGISTRATION_WAITLIST_AUTOFILL);
            $count++;
            $spaces_filled += $registration->getSpacesReserved();
          }

          // Stop filling when there is no room left. This is checked even if
          // the registration was not updated, since other processes could add
          // to standard capacity while this loop is executing.
          if (!$host_entity->getSpacesRemaining()) {
            break;
          }
        }

        if ($count) {
          if ($spaces_filled == 1) {
            $this->logger->info($this->formatPlural($count, 'Automatically filled 1 registration from the wait list.', 'Automatically filled @count registrations from the wait list.'));

          }
          else {
            $this->logger->info($this->formatPlural($count, 'Automatically filled 1 registration and @spaces_filled spaces from the wait list.', 'Automatically filled @count registrations and @spaces_filled spaces from the wait list.', [
              '@spaces_filled' => $spaces_filled,
            ]));
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getAutoFillSortFieldOptions(RegistrationTypeInterface $registration_type): array {
    $compatible_types = [
      'created',
      'changed',
      'integer',
      'string',
      'timestamp',
      'weight',
    ];
    $fields = [];
    $field_definitions = $this->entityFieldManager->getFieldDefinitions('registration', $registration_type->id());
    foreach ($field_definitions as $field_definition) {
      if (in_array($field_definition->getType(), $compatible_types)) {
        $fields[$field_definition->getName()] = $field_definition->getLabel();
      }
    }

    // Retitle the ID field which is otherwise ambiguous.
    $fields['registration_id'] = $this->t('Registration ID');

    // Remove host entity fields that are nonsensical for this usage.
    unset($fields['entity_id']);
    unset($fields['entity_type_id']);

    // Remove registration state since only wait listed registrations are
    // eligible for autofill.
    unset($fields['state']);

    // Remove completed since completed registrations are not wait listed.
    unset($fields['completed']);

    asort($fields);
    return $fields;
  }

  /**
   * Sort registrations prior to autofill.
   *
   * @param \Drupal\registration\Entity\RegistrationInterface $a
   *   The first registration.
   * @param \Drupal\registration\Entity\RegistrationInterface $b
   *   The second registration.
   *
   * @return int
   *   An integer less than, equal to, or greater than zero.
   *
   * @see https://www.php.net/manual/en/function.uasort.php
   */
  protected function sortRegistrations(RegistrationInterface $a, RegistrationInterface $b): int {
    $registration_type = $a->getType();
    $sort_field = $registration_type->getThirdPartySetting('registration_waitlist', 'autofill_sort_field');
    $sort_order = $registration_type->getThirdPartySetting('registration_waitlist', 'autofill_sort_order');
    $a_value = $a->get($sort_field)->isEmpty() ? NULL : $a->get($sort_field)->getValue()[0]['value'];
    $b_value = $b->get($sort_field)->isEmpty() ? NULL : $b->get($sort_field)->getValue()[0]['value'];
    return ($sort_order == 'ASC') ? $a_value <=> $b_value : $b_value <=> $a_value;
  }

}
