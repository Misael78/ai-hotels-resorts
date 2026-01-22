<?php

namespace Drupal\commerce_registration_waitlist\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\commerce_order\Entity\OrderInterface;

/**
 * Defines the commerce registration wait list event.
 *
 * @see \Drupal\registration\Event\CommerceRegistrationWaitListEvents
 */
class CommerceRegistrationWaitListEvent extends Event {

  /**
   * The order (a Commerce cart).
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected OrderInterface $order;

  /**
   * Whether the event was handled already.
   *
   * @var bool
   */
  protected bool $handled;

  /**
   * Constructs a new CommerceRegistrationWaitListEvent.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   */
  public function __construct(OrderInterface $order) {
    $this->order = $order;
    $this->handled = FALSE;
  }

  /**
   * Gets the order.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface
   *   The order.
   */
  public function getOrder(): OrderInterface {
    return $this->order;
  }

  /**
   * Determines if the event was already handled.
   *
   * @return bool
   *   TRUE if the event was already handled, FALSE otherwise.
   */
  public function wasHandled(): bool {
    return $this->handled;
  }

  /**
   * Sets whether the event was already handled.
   *
   * @param bool $handled
   *   Whether the event was already handled.
   */
  public function setHandled(bool $handled) {
    $this->handled = $handled;
  }

}
