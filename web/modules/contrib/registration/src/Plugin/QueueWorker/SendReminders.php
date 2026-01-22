<?php

namespace Drupal\registration\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\registration\Notify\RegistrationMailerInterface;
use Drupal\registration\RegistrationManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Send reminders.
 *
 * @QueueWorker(
 *  id = "registration.send_reminders",
 *  title = @Translation("Send reminders"),
 *  cron = {"time" = 30}
 * )
 */
class SendReminders extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * The registration mailer.
   *
   * @var \Drupal\registration\Notify\RegistrationMailerInterface
   */
  protected RegistrationMailerInterface $registrationMailer;

  /**
   * The registration manager.
   *
   * @var \Drupal\registration\RegistrationManagerInterface
   */
  protected RegistrationManagerInterface $registrationManager;

  /**
   * Constructs a new SendReminders object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\registration\Notify\RegistrationMailerInterface $registration_mailer
   *   The registration mailer.
   * @param \Drupal\registration\RegistrationManagerInterface $registration_manager
   *   The registration manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, LoggerInterface $logger, RegistrationMailerInterface $registration_mailer, RegistrationManagerInterface $registration_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
    $this->registrationMailer = $registration_mailer;
    $this->registrationManager = $registration_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): SendReminders {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('registration.logger'),
      $container->get('registration.notifier'),
      $container->get('registration.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $storage = $this->entityTypeManager->getStorage($data['entity_type_id']);
    $entity = $storage->load($data['entity_id']);
    if ($entity) {
      /** @var \Drupal\registration\HostEntityInterface $host_entity */
      $host_entity = $this->entityTypeManager
        ->getHandler($entity->getEntityTypeId(), 'registration_host_entity')
        ->createHostEntity($entity, $data['langcode']);
      $registration_type = $host_entity->getRegistrationType();
      $states = $registration_type->getActiveStates();
      if (empty($states)) {
        $this->logger->error('There are no active registration states configured. For a reminder email to be sent, an active registration state must be specified for the @type registration type.', [
          '@type' => $registration_type->label(),
        ]);
      }
      else {
        // Active states confirmed, send email to the active registrants.
        $data['subject'] = $this->t('Reminder for %label', [
          '%label' => $host_entity->label(),
        ]);
        $data['states'] = array_keys($states);
        $data['mail_tag'] = 'reminder';
        $success_count = $this->registrationMailer->notify($host_entity, $data);
        if (!$success_count) {
          $this->logger->warning('Reminder email for %label had no recipients.', [
            '%label' => $host_entity->label(),
          ]);
        }
      }

      // Turn off the reminder now that is has been processed.
      $settings = $host_entity->getSettings();
      $settings->set('send_reminder', FALSE);
      $settings->save();
    }
  }

}
