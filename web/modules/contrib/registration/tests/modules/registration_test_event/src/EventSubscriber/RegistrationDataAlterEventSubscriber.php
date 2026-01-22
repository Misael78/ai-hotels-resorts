<?php

namespace Drupal\registration_test_event\EventSubscriber;

use Drupal\Tests\RandomGeneratorTrait;
use Drupal\node\NodeInterface;
use Drupal\registration\Event\RegistrationDataAlterEvent;
use Drupal\registration\Event\RegistrationEvents;
use Drupal\registration\RegistrationValidationResult;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides a registration data alter event subscriber.
 */
class RegistrationDataAlterEventSubscriber implements EventSubscriberInterface {

  use RandomGeneratorTrait;

  /**
   * Alters the registration count.
   *
   * @param \Drupal\registration\Event\RegistrationDataAlterEvent $event
   *   The registration data alter event.
   */
  public function alterCount(RegistrationDataAlterEvent $event) {
    $count = $event->getData();
    $event->setData($count + 1);
  }

  /**
   * Alters enabled status.
   *
   * @param \Drupal\registration\Event\RegistrationDataAlterEvent $event
   *   The registration data alter event.
   */
  public function alterEnabled(RegistrationDataAlterEvent $event) {
    $context = $event->getContext();
    if ($host_entity = $context['host_entity']) {
      if ($host_entity->getEntity()->id() == 2) {
        $event->setData(FALSE);
      }
      elseif ($host_entity->getEntity()->id() == 4) {
        $event->setData(TRUE);
      }
    }
  }

  /**
   * Alters email recipients.
   *
   * @param \Drupal\registration\Event\RegistrationDataAlterEvent $event
   *   The registration data alter event.
   */
  public function alterRecipients(RegistrationDataAlterEvent $event) {
    $recipients = $event->getData();
    $context = $event->getContext();
    if ($host_entity = $context['host_entity']) {
      $registration = $host_entity->generateSampleRegistration();
      $email_address = $this->randomMachineName() . '@example.com';
      $registration->set('user_uid', NULL);
      $registration->set('anon_mail', $email_address);
      $recipients[$email_address] = $registration;
      $event->setData($recipients);
    }
  }

  /**
   * Alters the number of spaces remaining.
   *
   * @param \Drupal\registration\Event\RegistrationDataAlterEvent $event
   *   The registration data alter event.
   */
  public function alterRemaining(RegistrationDataAlterEvent $event) {
    $count = $event->getData();
    $event->setData($count - 1);
  }

  /**
   * Alters the number of spaces reserved.
   *
   * @param \Drupal\registration\Event\RegistrationDataAlterEvent $event
   *   The registration data alter event.
   */
  public function alterUsage(RegistrationDataAlterEvent $event) {
    $event->setData(3);
  }

  /**
   * Provides host validation for a node.
   *
   * @param \Drupal\registration\Event\RegistrationDataAlterEvent $event
   *   The registration data alter event.
   */
  public function alterHostValidation(RegistrationDataAlterEvent $event) {
    $context = $event->getContext();
    if ($context['value'] instanceof NodeInterface) {
      $validation_result = new RegistrationValidationResult([], $context['value']);
      $validation_result->addViolation('This is an example error message 1.', [], NULL, NULL, NULL, 'example_error_code1');
      $event->setData($validation_result);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      RegistrationEvents::REGISTRATION_ALTER_COUNT => 'alterCount',
      // @phpstan-ignore-next-line
      RegistrationEvents::REGISTRATION_ALTER_ENABLED => 'alterEnabled',
      RegistrationEvents::REGISTRATION_ALTER_RECIPIENTS => 'alterRecipients',
      RegistrationEvents::REGISTRATION_ALTER_SPACES_REMAINING => 'alterRemaining',
      RegistrationEvents::REGISTRATION_ALTER_USAGE => 'alterUsage',
      RegistrationEvents::REGISTRATION_ALTER_HOST_VALIDATION => 'alterHostValidation',
    ];
  }

}
