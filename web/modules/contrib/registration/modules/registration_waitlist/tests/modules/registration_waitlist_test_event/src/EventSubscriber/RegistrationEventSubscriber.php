<?php

namespace Drupal\registration_waitlist_test_event\EventSubscriber;

use Drupal\registration\Event\RegistrationEvent;
use Drupal\registration_waitlist\Event\RegistrationWaitListEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides a registration event subscriber.
 *
 * Use string fields that are not validated to store literals reflecting the
 * event being processed. These literals can then be asserted against in a
 * test class.
 */
class RegistrationEventSubscriber implements EventSubscriberInterface {

  /**
   * Process preautofill.
   *
   * @param \Drupal\registration\Event\RegistrationEvent $event
   *   The registration event.
   */
  public function preautofill(RegistrationEvent $event) {
    $registration = $event->getRegistration();
    $registration->set('anon_mail', 'preautofill@example.org');
  }

  /**
   * Process autofill.
   *
   * @param \Drupal\registration\Event\RegistrationEvent $event
   *   The registration event.
   */
  public function autofilled(RegistrationEvent $event) {
    $registration = $event->getRegistration();
    $registration->set('langcode', 'autofilled');
    $registration->save();
  }

  /**
   * Process a waitlisted registration.
   *
   * @param \Drupal\registration\Event\RegistrationEvent $event
   *   The registration event.
   */
  public function waitlisted(RegistrationEvent $event) {
    $registration = $event->getRegistration();
    $registration->set('langcode', 'waitlisted');
    $registration->save();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      RegistrationWaitListEvents::REGISTRATION_WAITLIST_PREAUTOFILL => 'preautofill',
      RegistrationWaitListEvents::REGISTRATION_WAITLIST_AUTOFILL => 'autofilled',
      RegistrationWaitListEvents::REGISTRATION_WAITLIST_WAITLISTED => 'waitlisted',
    ];
  }

}
