<?php

namespace Drupal\registration_waitlist\EventSubscriber;

use Drupal\Core\Action\ActionManager;
use Drupal\registration\Event\RegistrationEvent;
use Drupal\registration\Event\RegistrationEvents;
use Drupal\registration_waitlist\Event\RegistrationWaitListEvents;
use Drupal\registration_waitlist\RegistrationWaitListManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Provides a registration event subscriber.
 */
class RegistrationEventSubscriber implements EventSubscriberInterface {

  /**
   * The action plugin manager.
   *
   * @var \Drupal\Core\Action\ActionManager
   */
  protected ActionManager $actionManager;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Contracts\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * The wait list manager.
   *
   * @var \Drupal\registration_waitlist\RegistrationWaitListManagerInterface
   */
  protected RegistrationWaitListManagerInterface $waitListManager;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * Constructs a new RegistrationEventSubscriber.
   *
   * @param \Drupal\Core\Action\ActionManager $action_manager
   *   The action manager.
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\registration_waitlist\RegistrationWaitListManagerInterface $wait_list_manager
   *   The wait list manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(ActionManager $action_manager, EventDispatcherInterface $event_dispatcher, RegistrationWaitListManagerInterface $wait_list_manager, LoggerInterface $logger) {
    $this->actionManager = $action_manager;
    $this->eventDispatcher = $event_dispatcher;
    $this->waitListManager = $wait_list_manager;
    $this->logger = $logger;
  }

  /**
   * Processes delete.
   *
   * @param \Drupal\registration\Event\RegistrationEvent $event
   *   The registration event.
   */
  public function onDelete(RegistrationEvent $event) {
    $registration = $event->getRegistration();

    // Auto fill when an active registration is deleted and the auto fill
    // setting is enabled.
    if ($registration->getState()->isActive()) {
      /** @var \Drupal\registration_waitlist\HostEntityInterface $host_entity */
      $host_entity = $registration->getHostEntity();
      if ($host_entity->getSetting('registration_waitlist_autofill')) {
        $this->waitListManager->autoFill($host_entity);
      }
    }
  }

  /**
   * Processes update.
   *
   * @param \Drupal\registration\Event\RegistrationEvent $event
   *   The registration event.
   */
  public function onUpdate(RegistrationEvent $event) {
    $registration = $event->getRegistration();
    $from_state = isset($registration->original) ? $registration->original->getState()->id() : NULL;
    $to_state = $registration->getState()->id();
    if (($from_state !== $to_state) && ($to_state == 'waitlist')) {
      // Dispatch an event indicating a registration was just wait listed.
      $event = new RegistrationEvent($registration);
      $this->eventDispatcher->dispatch($event, RegistrationWaitListEvents::REGISTRATION_WAITLIST_WAITLISTED);

      // Send a confirmation email for newly wait listed registrations if this
      // is enabled for the registration type.
      $registration_type = $registration->getType();
      if ($registration_type->getThirdPartySetting('registration_waitlist', 'confirmation_email')) {
        $configuration['recipient'] = $registration->getEmail();
        $configuration['subject'] = $registration_type->getThirdPartySetting('registration_waitlist', 'confirmation_email_subject');
        $configuration['message'] = $registration_type->getThirdPartySetting('registration_waitlist', 'confirmation_email_message');
        $configuration['log_message'] = FALSE;
        $action = $this->actionManager->createInstance('registration_send_email_action');
        $action->setConfiguration($configuration);
        if ($action->execute($registration)) {
          $this->logger->info('Sent wait list confirmation email to %recipient', [
            '%recipient' => $configuration['recipient'],
          ]);
        }
      }
    }

    // Auto fill when an existing registration moves to an inactive state and
    // the auto fill setting is enabled.
    if (!$registration->isNew() && ($from_state !== $to_state)) {
      if ($registration->original && $registration->original->getState()->isActive()) {
        if (!$registration->getState()->isActive()) {
          /** @var \Drupal\registration_waitlist\HostEntityInterface $host_entity */
          $host_entity = $registration->getHostEntity();
          if ($host_entity->getSetting('registration_waitlist_autofill')) {
            $this->waitListManager->autoFill($host_entity);
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      RegistrationEvents::REGISTRATION_DELETE => 'onDelete',
      RegistrationEvents::REGISTRATION_INSERT => 'onUpdate',
      RegistrationEvents::REGISTRATION_UPDATE => 'onUpdate',
    ];
  }

}
