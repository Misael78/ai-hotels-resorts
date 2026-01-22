<?php

namespace Drupal\registration_waitlist\EventSubscriber;

use Drupal\registration\Event\RegistrationEvents;
use Drupal\registration\Event\RegistrationSettingsEvent;
use Drupal\registration_waitlist\RegistrationWaitListManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides a registration settings event subscriber.
 */
class RegistrationSettingsEventSubscriber implements EventSubscriberInterface {

  /**
   * The wait list manager.
   *
   * @var \Drupal\registration_waitlist\RegistrationWaitListManagerInterface
   */
  protected RegistrationWaitListManagerInterface $waitListManager;

  /**
   * Constructs a new RegistrationEventSubscriber.
   *
   * @param \Drupal\registration_waitlist\RegistrationWaitListManagerInterface $wait_list_manager
   *   The wait list manager.
   */
  public function __construct(RegistrationWaitListManagerInterface $wait_list_manager) {
    $this->waitListManager = $wait_list_manager;
  }

  /**
   * Processes load of a registration settings entity.
   *
   * @param \Drupal\registration\Event\RegistrationSettingsEvent $event
   *   The registration settings event.
   */
  public function onSettingsLoad(RegistrationSettingsEvent $event) {
    $settings = $event->getSettings();
    // Ensure wait list capacity is initialized. Although the base field
    // definition has a default value of zero defined, if the module is
    // enabled with settings already stored in the database, the default
    // will never get loaded. Set the default here if needed, since the
    // field is conditionally required on the registration settings form,
    // and it will fail validation otherwise.
    // @see registration_waitlist_entity_base_field_info()
    if (is_null($settings->getSetting('registration_waitlist_capacity'))) {
      $settings->set('registration_waitlist_capacity', 0);
    }
  }

  /**
   * Processes update of a registration settings entity.
   *
   * @param \Drupal\registration\Event\RegistrationSettingsEvent $event
   *   The registration settings event.
   */
  public function onSettingsUpdate(RegistrationSettingsEvent $event) {
    // If the capacity was increased and autofill is enabled, fill the newly
    // available spots from the wait list.
    $settings = $event->getSettings();
    if ($settings->original && ($settings->getSetting('capacity') > $settings->original->getSetting('capacity'))) {
      $host_entity = $settings->getHostEntity();
      if ((bool) $host_entity->getSetting('registration_waitlist_autofill')) {
        $this->waitListManager->autoFill($host_entity);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      RegistrationEvents::REGISTRATION_SETTINGS_LOAD => 'onSettingsLoad',
      RegistrationEvents::REGISTRATION_SETTINGS_UPDATE => 'onSettingsUpdate',
    ];
  }

}
