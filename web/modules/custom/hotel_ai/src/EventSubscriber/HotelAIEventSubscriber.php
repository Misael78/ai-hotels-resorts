<?php

namespace Drupal\hotel_ai\EventSubscriber;

use Drupal\Core\Entity\EntityInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Entity\EntityStorageException;
use Symfony\Component\EventDispatcher\Event;
use Drupal\hotel_ai\Service\HotelAIGenerator;

/**
 * Automatically generates AI data on Room save.
 */
class HotelAIEventSubscriber implements EventSubscriberInterface {

  protected $generator;

  public function __construct(HotelAIGenerator $generator) {
    $this->generator = $generator;
  }

  public static function getSubscribedEvents() {
    return [
      'entity.insert' => 'onEntitySave',
      'entity.update' => 'onEntitySave',
    ];
  }

  public function onEntitySave(Event $event) {
    $entity = $event->getEntity();

    if ($entity instanceof NodeInterface && $entity->bundle() === 'room') {
      try {
        $this->generator->generateRoomEnhancements($entity);
      }
      catch (\Exception $e) {
        \Drupal::logger('hotel_ai')->error($e->getMessage());
      }
    }
  }
}
