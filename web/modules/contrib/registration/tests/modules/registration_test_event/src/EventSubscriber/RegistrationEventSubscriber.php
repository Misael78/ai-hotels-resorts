<?php

namespace Drupal\registration_test_event\EventSubscriber;

use Drupal\registration\Event\RegistrationEvent;
use Drupal\registration\Event\RegistrationEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides a registration event subscriber.
 *
 * Use string fields that are not validated to store literals reflecting the
 * event being processed. These literals can then be asserted against in a
 * test class.
 *
 * In a real application, these events would be used to update related custom
 * entities, or perform other custom business logic.
 */
class RegistrationEventSubscriber implements EventSubscriberInterface {

  /**
   * Process create.
   *
   * @param \Drupal\registration\Event\RegistrationEvent $event
   *   The registration event.
   */
  public function create(RegistrationEvent $event) {
    $registration = $event->getRegistration();
    $host_entity = $registration->getHostEntity();
    $settings = $host_entity->getSettings();
    $settings->set('from_address', 'create');
    $settings->save();
  }

  /**
   * Process delete.
   *
   * @param \Drupal\registration\Event\RegistrationEvent $event
   *   The registration event.
   */
  public function delete(RegistrationEvent $event) {
    $registration = $event->getRegistration();
    $host_entity = $registration->getHostEntity();
    $settings = $host_entity->getSettings();
    $settings->set('confirmation', 'delete');
    $settings->save();
  }

  /**
   * Process insert.
   *
   * @param \Drupal\registration\Event\RegistrationEvent $event
   *   The registration event.
   */
  public function insert(RegistrationEvent $event) {
    $registration = $event->getRegistration();
    $host_entity = $registration->getHostEntity();
    $settings = $host_entity->getSettings();
    $settings->set('confirmation', 'insert');
    $settings->save();
  }

  /**
   * Process load.
   *
   * @param \Drupal\registration\Event\RegistrationEvent $event
   *   The registration event.
   */
  public function load(RegistrationEvent $event) {
    $registration = $event->getRegistration();
    $registration->set('langcode', 'load');
  }

  /**
   * Process predelete.
   *
   * @param \Drupal\registration\Event\RegistrationEvent $event
   *   The registration event.
   */
  public function predelete(RegistrationEvent $event) {
    $registration = $event->getRegistration();
    $host_entity = $registration->getHostEntity();
    $settings = $host_entity->getSettings();
    $settings->set('from_address', 'predelete');
    $settings->save();
  }

  /**
   * Process presave.
   *
   * @param \Drupal\registration\Event\RegistrationEvent $event
   *   The registration event.
   */
  public function presave(RegistrationEvent $event) {
    $registration = $event->getRegistration();
    if (!$registration->isNew()) {
      $registration->set('langcode', 'presave');
    }
  }

  /**
   * Process update.
   *
   * @param \Drupal\registration\Event\RegistrationEvent $event
   *   The registration event.
   */
  public function update(RegistrationEvent $event) {
    $registration = $event->getRegistration();
    $host_entity = $registration->getHostEntity();
    $settings = $host_entity->getSettings();
    $settings->set('confirmation', 'update');
    $settings->save();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      RegistrationEvents::REGISTRATION_CREATE => 'create',
      RegistrationEvents::REGISTRATION_DELETE => 'delete',
      RegistrationEvents::REGISTRATION_INSERT => 'insert',
      RegistrationEvents::REGISTRATION_LOAD => 'load',
      RegistrationEvents::REGISTRATION_PREDELETE => 'predelete',
      RegistrationEvents::REGISTRATION_PRESAVE => 'presave',
      RegistrationEvents::REGISTRATION_UPDATE => 'update',
    ];
  }

}
