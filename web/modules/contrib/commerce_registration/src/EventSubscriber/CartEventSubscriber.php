<?php

namespace Drupal\commerce_registration\EventSubscriber;

use Drupal\commerce_cart\Event\CartEvents;
use Drupal\commerce_cart\Event\CartOrderItemRemoveEvent;
use Drupal\commerce_cart\Event\CartOrderItemUpdateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides a cart event subscriber.
 */
class CartEventSubscriber implements EventSubscriberInterface {

  /**
   * Process cart item removal.
   *
   * @param \Drupal\commerce_cart\Event\CartOrderItemRemoveEvent $event
   *   The remove from cart event.
   */
  public function onCartItemRemove(CartOrderItemRemoveEvent $event) {
    // Delete referenced registrations. These will only exist if the person
    // completing checkout has already submitted the registration information
    // checkout pane (which saves registrations) and then returned to the cart
    // page to remove an item.
    $item = $event->getOrderItem();
    if (!$item->get('registration')->isEmpty()) {
      $registrations = $item->get('registration')->referencedEntities();
      foreach ($registrations as $registration) {
        $registration->delete();
      }
    }
  }

  /**
   * Process cart item update.
   *
   * @param \Drupal\commerce_cart\Event\CartOrderItemUpdateEvent $event
   *   The update cart event.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function onCartItemUpdate(CartOrderItemUpdateEvent $event) {
    // Delete referenced registrations beyond the item quantity when the
    // quantity has been reduced.
    $item = $event->getOrderItem();
    $original_item = $event->getOriginalOrderItem();

    if ($item->getQuantity() < $original_item->getQuantity()) {
      $registrations = $item->get('registration')->referencedEntities();
      $count = count($registrations);
      if ($count > $item->getQuantity()) {
        $end = $count - 1;
        $index = 1;
        foreach ($registrations as $registration) {
          if ($index > $item->getQuantity()) {
            // Remove from the end to avoid rekey issues.
            $item->get('registration')->removeItem($end);
            $registration->delete();
            $end--;
          }
          $index++;
        }
        $item->save();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      CartEvents::CART_ORDER_ITEM_REMOVE => 'onCartItemRemove',
      CartEvents::CART_ORDER_ITEM_UPDATE => 'onCartItemUpdate',
    ];
  }

}
