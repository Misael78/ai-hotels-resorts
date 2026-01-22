<?php

namespace Drupal\registration_workflow\EventSubscriber;

use Drupal\registration\Event\RegistrationEvent;
use Drupal\registration\Event\RegistrationEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides a registration event subscriber.
 */
class RegistrationEventSubscriber implements EventSubscriberInterface {

  /**
   * Process update.
   *
   * @param \Drupal\registration\Event\RegistrationEvent $event
   *   The registration event.
   */
  public function onUpdate(RegistrationEvent $event) {
    // Integrate with ECA Workflow if the module is installed.
    // @see https://www.drupal.org/project/eca
    if (\Drupal::moduleHandler()->moduleExists('eca_workflow')) {
      $registration = $event->getRegistration();
      $from_state = isset($registration->original) ? $registration->original->getState()->id() : NULL;
      $to_state = $registration->getState()->id();
      if ($from_state !== $to_state) {
        $eca_content_types = \Drupal::service('eca.service.content_entity_types');
        \Drupal::service('eca.trigger_event')->dispatchFromPlugin('workflow:transition', $registration, $from_state, $to_state, $eca_content_types);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      RegistrationEvents::REGISTRATION_UPDATE => 'onUpdate',
    ];
  }

}
