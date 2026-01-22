<?php

namespace Drupal\registration\Notify;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\registration\Entity\RegistrationInterface;
use Drupal\registration\Event\RegistrationDataAlterEvent;
use Drupal\registration\Event\RegistrationEvents;
use Drupal\registration\HostEntityInterface;
use Drupal\registration\RegistrationHelper;
use Drupal\registration\RegistrationManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Defines the class for the registration notification service.
 */
class RegistrationMailer implements RegistrationMailerInterface {

  use StringTranslationTrait;

  /**
   * The configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected AccountProxy $currentUser;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Contracts\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected MailManagerInterface $mailManager;

  /**
   * The queue.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected QueueInterface $queue;

  /**
   * The registration manager.
   *
   * @var \Drupal\registration\RegistrationManagerInterface
   */
  protected RegistrationManagerInterface $registrationManager;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected Renderer $renderer;

  /**
   * Creates a RegistrationMailer object.
   *
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Session\AccountProxy $current_user
   *   The current user.
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory.
   * @param \Drupal\registration\RegistrationManagerInterface $registration_manager
   *   The registration manager.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   The renderer.
   */
  public function __construct(ConfigFactory $config_factory, AccountProxy $current_user, EventDispatcherInterface $event_dispatcher, LoggerInterface $logger, MailManagerInterface $mail_manager, QueueFactory $queue_factory, RegistrationManagerInterface $registration_manager, Renderer $renderer) {
    $this->config = $config_factory->get('registration.settings');
    $this->currentUser = $current_user;
    $this->eventDispatcher = $event_dispatcher;
    $this->logger = $logger;
    $this->mailManager = $mail_manager;
    $this->queue = $queue_factory->get('registration.notify');
    $this->registrationManager = $registration_manager;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public function getRecipientList(HostEntityInterface $host_entity, array $data = []): array {
    $langcode = !empty($data['langcode']) ? $data['langcode'] : NULL;

    if (!empty($data['test'])) {
      $registrations = [$host_entity->generateSampleRegistration()];
    }
    elseif (!empty($data['states'])) {
      $registrations = $host_entity->getRegistrationList($data['states'], $langcode);
    }
    else {
      $registrations = $host_entity->getRegistrationList([], $langcode);
    }

    // The list is built as an associative array, indexed by email address.
    // The value is an array for emails with multiple registrations, or
    // a RegistrationInterface entity if the email has a single registration.
    $recipients = [];
    foreach ($registrations as $registration) {
      $email = $registration->getEmail();
      if (isset($recipients[$email])) {
        if (is_array($recipients[$email])) {
          // Already multiple, append to the list.
          $recipients[$email][] = $registration;
        }
        else {
          // Convert to multiple.
          $previous_registration = $recipients[$email];
          $recipients[$email] = [];
          $recipients[$email][] = $previous_registration;
          $recipients[$email][] = $registration;
        }
      }
      else {
        // Single registration.
        $recipients[$email] = $registration;
      }
    }

    // Allow other modules to alter the recipient list.
    $event = new RegistrationDataAlterEvent($recipients, [
      'host_entity' => $host_entity,
      'settings' => $host_entity->getSettings(),
      'data' => $data,
    ]);
    $this->eventDispatcher->dispatch($event, RegistrationEvents::REGISTRATION_ALTER_RECIPIENTS);
    return $event->getData();
  }

  /**
   * {@inheritdoc}
   */
  public function notify(HostEntityInterface $host_entity, array $data = []): int {
    // Ensure email subject and message are present. The data variable is
    // optional in the function signature because other implementations may
    // not need it. But in this default implementation for sending an email,
    // subject and message are required.
    if (!isset($data['subject']) || !isset($data['message'])) {
      throw new \InvalidArgumentException("Email notifications require subject and message data.");
    }

    $success_count = 0;
    $settings = $host_entity->getSettings();
    $user_langcode = $this->currentUser->getPreferredLangcode(TRUE);

    // Build parameters. These are common to every email sent.
    $params = [];
    $params['subject'] = $data['subject'];
    $params['from'] = RegistrationHelper::getMailboxHeader('From', $settings->getSetting('from_address'));
    $build = [
      '#type' => 'processed_text',
      '#text' => $data['message']['value'],
      '#format' => $data['message']['format'],
    ];
    $message = $this->renderer->renderInIsolation($build);
    $params['message'] = new FormattableMarkup($message, []);
    $params['token_entities'] = [
      $host_entity->getEntityTypeId() => $host_entity->getEntity(),
      'registration_settings' => $settings,
    ];
    if (!empty($data['token_entities'])) {
      $params['token_entities'] += $data['token_entities'];
    }

    // Get the recipients and send to each.
    $recipients = $this->getRecipientList($host_entity, $data);
    // Queue notifications depending on how many recipients there are.
    $queue = (count($recipients) > $this->config->get('queue_notifications'));
    foreach ($recipients as $email => $registrations) {
      // Convert singleton to array.
      if (!is_array($registrations)) {
        $registrations = [$registrations];
      }
      foreach ($registrations as $registration) {
        // Set registration entity for token replacement if available.
        if ($registration instanceof RegistrationInterface) {
          $params['token_entities']['registration'] = $registration;
        }
        else {
          // Clear what is there from the previous loop iteration.
          unset($params['token_entities']['registration']);
        }

        // Allow other modules to alter the parameters.
        // Token replacement should not be done in the event subscriber
        // because the registration_mail function already handles this.
        $event = new RegistrationDataAlterEvent($params, [
          'host_entity' => $host_entity,
          'settings' => $settings,
          'registration' => $registration,
          'data' => $data,
        ]);
        $this->eventDispatcher->dispatch($event, RegistrationEvents::REGISTRATION_ALTER_MAIL);
        $params = $event->getData();

        $langcode = $registration->getLangcode() ?? $user_langcode;
        if ($queue) {
          $item = [
            'label' => $host_entity->label(),
            'langcode' => $langcode,
            'registration' => $registration,
            'params' => $params,
            'target' => $email,
          ];
          $this->queue->createItem($item);
          $success_count++;
        }
        else {
          // Send the mail and count successes.
          $result = $this->mailManager->mail('registration', 'broadcast', $email, $langcode, $params);
          if ($result['result'] !== FALSE) {
            $success_count++;
          }
          else {
            $this->logger->error('Failed to send registration broadcast email for %label to %email.', [
              '%label' => $host_entity->label(),
              '%email' => $email,
            ]);
          }
        }
      }
    }
    if ($success_count) {
      if ($queue) {
        $this->logger->info('Queued registration broadcast for %label for @count recipient(s).', [
          '%label' => $host_entity->label(),
          '@count' => $success_count,
        ]);
      }
      else {
        $this->logger->info('Registration broadcast for %label sent to @count recipient(s).', [
          '%label' => $host_entity->label(),
          '@count' => $success_count,
        ]);
      }
    }
    return $success_count;
  }

}
