<?php

namespace Drupal\registration_test_errors\EventSubscriber;

use Drupal\registration\Event\RegistrationDataAlterEvent;
use Drupal\registration\Event\RegistrationEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides a registration data alter event subscriber.
 */
class RegistrationDataAlterEventSubscriber implements EventSubscriberInterface {

  /**
   * Alter enabled status and the errors array.
   *
   * @param \Drupal\registration\Event\RegistrationDataAlterEvent $event
   *   The registration data alter event.
   */
  public function alterEnabled(RegistrationDataAlterEvent $event) {
    $context = $event->getContext();
    if ($host_entity = $context['host_entity']) {
      if ($host_entity->getEntity()->id() == 2) {
        $errors = $context['errors'];
        $errors['special_field'] = 'A special field has an error.';
        $event->setErrors($errors);
        $event->setData(FALSE);
      }
      elseif ($host_entity->getEntity()->id() == 3) {
        $errors = $context['errors'];
        $errors['special_field'] = 'A special field has an error.';
        unset($errors['capacity']);
        $event->setErrors($errors);
        $event->setData(FALSE);
      }
      elseif ($host_entity->getEntity()->id() == 4) {
        $errors = $context['errors'];
        unset($errors['capacity']);
        $event->setErrors($errors);
        $event->setData(empty($errors));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      // @phpstan-ignore-next-line
      RegistrationEvents::REGISTRATION_ALTER_ENABLED => 'alterEnabled',
    ];
  }

}
