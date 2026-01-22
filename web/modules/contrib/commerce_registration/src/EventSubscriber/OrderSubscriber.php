<?php

namespace Drupal\commerce_registration\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\commerce_order\Event\OrderEvent;
use Drupal\commerce_order\Event\OrderEvents;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides an order event subscriber.
 */
class OrderSubscriber implements EventSubscriberInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a new OrderSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Marks held registrations as pending for submitted orders.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The event.
   */
  public function onOrderPlace(WorkflowTransitionEvent $event) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $event->getEntity();
    $registrations = $this->entityTypeManager->getStorage('registration')->loadByProperties([
      'order_id' => $order->id(),
    ]);
    /** @var \Drupal\registration\Entity\RegistrationInterface $registration */
    foreach ($registrations as $registration) {
      if ($registration->isHeld()) {
        if ($workflow = $registration->getWorkflow()->getTypePlugin()) {
          if ($workflow->hasState('pending')) {
            $registration->set('state', 'pending');
            $registration->save();
          }
        }
      }
    }
  }

  /**
   * Marks active registrations as complete for fully paid orders.
   *
   * @param \Drupal\commerce_order\Event\OrderEvent $event
   *   The event.
   */
  public function onOrderPaid(OrderEvent $event) {
    $order = $event->getOrder();
    $registrations = $this->entityTypeManager->getStorage('registration')->loadByProperties([
      'order_id' => $order->id(),
    ]);
    /** @var \Drupal\registration\Entity\RegistrationInterface $registration */
    foreach ($registrations as $registration) {
      // Only mark active or held registrations, in case any were canceled.
      if ($registration->isActive() || $registration->isHeld()) {
        if ($workflow = $registration->getWorkflow()->getTypePlugin()) {
          if ($workflow->hasState('complete')) {
            $registration->set('state', 'complete');
            $registration->save();
          }
        }
      }
    }
  }

  /**
   * Marks pending registrations as canceled when a cart is canceled.
   *
   * @param \Drupal\commerce_order\Event\OrderEvent $event
   *   The event.
   */
  public function onOrderUpdate(OrderEvent $event) {
    $is_cart = FALSE;
    $order = $event->getOrder();
    if (!$order->get('cart')->isEmpty()) {
      $is_cart = $order->get('cart')->getValue()[0]['value'];
    }
    if ($is_cart && ($order->getState()->getId() == 'canceled')) {
      $registrations = $this->entityTypeManager->getStorage('registration')->loadByProperties([
        'order_id' => $order->id(),
      ]);
      /** @var \Drupal\registration\Entity\RegistrationInterface $registration */
      foreach ($registrations as $registration) {
        if ($registration->isActive() || $registration->isHeld()) {
          if (!$registration->isComplete()) {
            if ($workflow = $registration->getWorkflow()->getTypePlugin()) {
              if ($workflow->hasState('canceled')) {
                $registration->set('state', 'canceled');
                $registration->save();
              }
            }
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
      'commerce_order.place.post_transition' => ['onOrderPlace', 100],
      OrderEvents::ORDER_PAID => ['onOrderPaid', 100],
      OrderEvents::ORDER_UPDATE => ['onOrderUpdate', 100],
    ];
  }

}
