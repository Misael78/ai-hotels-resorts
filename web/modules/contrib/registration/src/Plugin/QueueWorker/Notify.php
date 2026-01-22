<?php

namespace Drupal\registration\Plugin\QueueWorker;

use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Notify registrants.
 *
 * Process emails in the queue and send.
 *
 * @QueueWorker(
 *  id = "registration.notify",
 *  title = @Translation("Notify registrants"),
 *  cron = {"time" = 30}
 * )
 */
class Notify extends QueueWorkerBase implements ContainerFactoryPluginInterface {

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
   * Constructs a new SendReminders object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerInterface $logger, MailManagerInterface $mail_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->logger = $logger;
    $this->mailManager = $mail_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): Notify {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('registration.logger'),
      $container->get('plugin.manager.mail')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $send = TRUE;
    $result = $this->mailManager->mail(
      'registration',
      'broadcast',
      $data['target'],
      $data['langcode'],
      $data['params'],
      NULL,
      $send);
    if ($result['result'] === FALSE) {
      $this->logger->error('Failed to send registration broadcast email for %label to %email.', [
        '%label' => $data['label'],
        '%email' => $data['target'],
      ]);
    }
  }

}
