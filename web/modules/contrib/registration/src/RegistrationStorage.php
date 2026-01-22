<?php

namespace Drupal\registration;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * The storage class for registration related entities.
 *
 * Dispatches events for entity CRUD operations.
 */
class RegistrationStorage extends SqlContentEntityStorage {

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    $instance = parent::createInstance($container, $entity_type);
    $instance->eventDispatcher = $container->get('event_dispatcher');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function postLoad(array &$entities) {
    parent::postLoad($entities);

    $event_class = $this->entityType->getHandlerClass('event');
    if ($event_class) {
      $event_name = $this->getEventName('load');
      foreach ($entities as $entity) {
        $this->eventDispatcher->dispatch(new $event_class($entity), $event_name);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function invokeHook($hook, EntityInterface $entity) {
    parent::invokeHook($hook, $entity);

    $event_class = $this->entityType->getHandlerClass('event');
    if ($event_class) {
      $this->eventDispatcher->dispatch(new $event_class($entity), $this->getEventName($hook));
    }
  }

  /**
   * Gets the event name for a given hook.
   *
   * For example, the 'update' hook for registration_settings entities
   * maps to the 'registration.registration_settings.update' event name.
   *
   * @param string $hook
   *   One of 'load', 'create', 'presave', 'insert', 'update', 'predelete',
   *   'delete'.
   *
   * @return string
   *   The event name.
   */
  protected function getEventName(string $hook): string {
    return $this->entityType->getProvider() . '.' . $this->entityType->id() . '.' . $hook;
  }

}
