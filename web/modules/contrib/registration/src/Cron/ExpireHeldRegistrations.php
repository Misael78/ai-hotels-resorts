<?php

namespace Drupal\registration\Cron;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;

/**
 * Queue registrations for possible hold expiration.
 *
 * @see \Drupal\registration\Plugin\QueueWorker\ExpireHeldRegistrations
 */
class ExpireHeldRegistrations {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The queue.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected QueueInterface $queue;

  /**
   * Constructs a new ExpireHeldRegistrations object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, QueueFactory $queue_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->queue = $queue_factory->get('registration.expire_held_registrations');
  }

  /**
   * Run this task.
   */
  public function run() {
    // Clear existing queue items to avoid reprocessing.
    $this->queue->deleteQueue();

    // Re-fill the queue with any held registrations.
    $registration_type_storage = $this->entityTypeManager->getStorage('registration_type');
    $registration_types = $registration_type_storage->loadMultiple();

    // Processed per type, since the states could act differently
    // for different types if they are using different workflows.
    foreach ($registration_types as $registration_type) {
      /** @var \Drupal\registration\Entity\RegistrationTypeInterface $registration_type **/
      $held_states = $registration_type->getHeldStates();
      if (!empty($held_states) && $registration_type->getHeldExpirationTime()) {
        $registration_storage = $this->entityTypeManager->getStorage('registration');
        $registrations = $registration_storage->loadByProperties([
          'type' => $registration_type->id(),
          'workflow' => $registration_type->getWorkflowId(),
          'state' => array_keys($held_states),
        ]);
        foreach ($registrations as $registration) {
          $item = $registration->id();
          $this->queue->createItem($item);
        }
      }
    }
  }

}
