<?php

namespace Drupal\registration_test_open_close\EventSubscriber;

use Drupal\registration\Event\RegistrationEvents;
use Drupal\registration\Event\RegistrationSettingsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides a registration settings event subscriber.
 */
class RegistrationSettingsEventSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      RegistrationEvents::REGISTRATION_SETTINGS_OPEN => 'onOpen',
      RegistrationEvents::REGISTRATION_SETTINGS_CLOSE => 'onClose',
    ];
  }

  /**
   * Processes open.
   *
   * @param \Drupal\registration\Event\RegistrationSettingsEvent $event
   *   The registration settings event.
   */
  public function onOpen(RegistrationSettingsEvent $event) {
    $settings = $event->getSettings();
    $settings->set('status', 1);
    $maximum_spaces = (int) $settings->getSetting('maximum_spaces');
    $maximum_spaces++;
    $settings->set('maximum_spaces', $maximum_spaces);
    $settings->save();
  }

  /**
   * Processes close.
   *
   * @param \Drupal\registration\Event\RegistrationSettingsEvent $event
   *   The registration settings event.
   */
  public function onClose(RegistrationSettingsEvent $event) {
    $settings = $event->getSettings();
    $maximum_spaces = (int) $settings->getSetting('maximum_spaces');
    $maximum_spaces--;
    $settings->set('maximum_spaces', $maximum_spaces);
    $settings->set('status', 0);
    $settings->save();
  }

}
