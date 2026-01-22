<?php

namespace Drupal\registration\EventSubscriber;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\registration\Event\RegistrationEvent;
use Drupal\registration\Event\RegistrationEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides an event subscriber to set the completed timestamp.
 */
class TimestampEventSubscriber implements EventSubscriberInterface {

  /**
   * The time.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected TimeInterface $time;

  /**
   * Constructs a new TimestampEventSubscriber object.
   *
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time.
   */
  public function __construct(TimeInterface $time) {
    $this->time = $time;
  }

  /**
   * Sets the registration's completed timestamp.
   *
   * @param \Drupal\registration\Event\RegistrationEvent $event
   *   The registration event.
   */
  public function onPreSave(RegistrationEvent $event) {
    $registration = $event->getRegistration();

    if ($registration->get('completed')->isEmpty()) {
      // Check if a new registration is starting out in complete state.
      if ($registration->isNew() && $registration->isComplete()) {
        $registration->set('completed', $this->time->getRequestTime());
      }
      elseif ($registration->original) {
        // Check if an updated registration is transitioning to complete state.
        if (!$registration->original->isComplete() && $registration->isComplete()) {
          $registration->set('completed', $this->time->getRequestTime());
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      // Use a low priority so that this subscriber runs last.
      RegistrationEvents::REGISTRATION_PRESAVE => ['onPreSave', -1000],
    ];
  }

}
