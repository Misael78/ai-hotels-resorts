<?php

namespace Drupal\commerce_registration_waitlist\Event;

/**
 * Events fired by the Commerce Registration Wait List module.
 */
final class CommerceRegistrationWaitListEvents {

  /**
   * Name of the event fired when a wait listed item has been added to the cart.
   *
   * Subscribers can alter the order, display messages and perform other
   * necessary housekeeping.
   *
   * @Event
   *
   * @see \Drupal\commerce_registration_waitlist\Event\CommerceRegistrationWaitListEvent
   */
  const COMMERCE_REGISTRATION_WAITLIST_ADD = 'commerce_registration_waitlist.cart.add_to_waitlist';

  /**
   * Name of the event when a wait listed item has been removed from the cart.
   *
   * Subscribers can alter the order, display messages and perform other
   * necessary housekeeping.
   *
   * @Event
   *
   * @see \Drupal\commerce_registration_waitlist\Event\CommerceRegistrationWaitListEvent
   */
  const COMMERCE_REGISTRATION_WAITLIST_REMOVE = 'commerce_registration_waitlist.cart.remove_from_waitlist';

}
