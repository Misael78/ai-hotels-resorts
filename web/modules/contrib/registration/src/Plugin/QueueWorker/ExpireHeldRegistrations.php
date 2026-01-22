<?php

namespace Drupal\registration\Plugin\QueueWorker;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Expire a held registration.
 *
 * @QueueWorker(
 *  id = "registration.expire_held_registrations",
 *  title = @Translation("Expire held registrations"),
 *  cron = {"time" = 30}
 * )
 */
class ExpireHeldRegistrations extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected TimeInterface $time;

  /**
   * Constructs a new ExpireHeldRegistrations object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, TimeInterface $time) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): ExpireHeldRegistrations {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $registration_storage = $this->entityTypeManager->getStorage('registration');
    $registration = $registration_storage->load($data);
    if ($registration) {
      /** @var \Drupal\registration\Entity\RegistrationInterface $registration **/
      $registration_type = $registration->getType();
      // Change registration state if hold has expired.
      // Expiration time is in hours, hence the multiplication.
      // Note that a hold time of zero means never expire.
      $current_time = $this->time->getCurrentTime();
      $hold_time = $registration_type->getHeldExpirationTime() * 60 * 60;
      if (($hold_time > 0) && (($current_time - $hold_time) > $registration->getChangedTime())) {
        $registration->set('state', $registration_type->getHeldExpirationState());
        $registration->save();
      }
    }
  }

}
