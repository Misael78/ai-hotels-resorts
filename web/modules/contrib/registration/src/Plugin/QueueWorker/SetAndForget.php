<?php

namespace Drupal\registration\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Automatically maintains the "status" field on the per-entity Settings form.
 *
 * @QueueWorker(
 *  id = "registration.set_and_forget",
 *  title = @Translation("Set and forget"),
 *  cron = {"time" = 30}
 * )
 */
class SetAndForget extends QueueWorkerBase implements ContainerFactoryPluginInterface {

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
   * Constructs a new SetAndForget object.
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
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, LoggerInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): SetAndForget {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('registration.logger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    /** @var \Drupal\registration\Entity\RegistrationSettings $settings */
    $settings_storage = $this->entityTypeManager->getStorage('registration_settings');
    $settings = $settings_storage->load($data['settings_id']);
    if ($settings?->getSetting('status') != $data['new_status']) {
      $settings->set('status', $data['new_status']);
      $settings->save();
      if (!empty($data['new_status'])) {
        $this->logger->notice('Automatically set status to "enabled" for @type @id.', [
          '@type' => $settings->getHostEntityTypeId(),
          '@id' => $settings->getHostEntityId(),
        ]);
      }
      else {
        $this->logger->notice('Automatically set status to "disabled" for @type @id.', [
          '@type' => $settings->getHostEntityTypeId(),
          '@id' => $settings->getHostEntityId(),
        ]);
      }
    }
  }

}
