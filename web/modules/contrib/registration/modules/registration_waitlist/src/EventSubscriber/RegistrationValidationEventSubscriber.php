<?php

namespace Drupal\registration_waitlist\EventSubscriber;

use Drupal\registration\Event\RegistrationEvents;
use Drupal\registration\Event\RegistrationDataAlterEvent;
use Drupal\registration_waitlist\HostEntityInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides a registration validation event subscriber.
 */
class RegistrationValidationEventSubscriber implements EventSubscriberInterface {

  /**
   * Alters a validation result.
   *
   * @param \Drupal\registration\Event\RegistrationDataAlterEvent $event
   *   The registration data alter event.
   */
  public function alterValidationResult(RegistrationDataAlterEvent $event): void {
    $context = $event->getContext();
    $host_entity = $context['host_entity'] ?? NULL;

    // Change the capacity violation if waitlist enabled.
    if ($host_entity instanceof HostEntityInterface) {
      if ($host_entity->isWaitListEnabled()) {
        $validation_result = $event->getData();
        if ($validation_result->hasViolationWithCode('capacity')) {
          $validation_result->removeViolationWithCode('capacity');
          $validation_result->addViolation('Sorry, unable to register for %label because the wait list is full.', [
            '%label' => $host_entity->label(),
          ], NULL, NULL, NULL, 'waitlist_capacity', t('No room on waitlist.'));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      RegistrationEvents::REGISTRATION_ALTER_VALIDATION_RESULT => 'alterValidationResult',
    ];
  }

}
