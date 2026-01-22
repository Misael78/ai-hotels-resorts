<?php

namespace Drupal\registration_waitlist\Event;

/**
 * Events fired by the Registration Wait List module.
 */
final class RegistrationWaitListEvents {

  /**
   * Name of the event when a wait listed registration is about to autofill.
   *
   * Subscribers can change the registration but should not save it, as the
   * registration will be saved by the autofill service right after this event
   * is dispatched. Any changes made to the registration in the subscriber will
   * be saved, except for changes to the status field.
   *
   * @Event
   *
   * @see \Drupal\registration\Event\RegistrationEvent
   */
  const REGISTRATION_WAITLIST_PREAUTOFILL = 'registration_waitlist.registration.preautofill';

  /**
   * Name of the event after a wait listed registration has filled an open slot.
   *
   * This event is fired after the registration has been saved, and thus the
   * registration is no longer in wait list status.
   *
   * @Event
   *
   * @see \Drupal\registration\Event\RegistrationEvent
   */
  const REGISTRATION_WAITLIST_AUTOFILL = 'registration_waitlist.registration.autofill';

  /**
   * Name of the event fired before a registration is about to be wait listed.
   *
   * The subscriber may alter the new status by setting the data field.
   *
   * @Event
   *
   * @see \Drupal\registration\Event\RegistrationDataAlterEvent
   */
  const REGISTRATION_WAITLIST_PRESAVE = 'registration_waitlist.registration.presave';

  /**
   * Name of the event fired after a registration has been wait listed.
   *
   * @Event
   *
   * @see \Drupal\registration\Event\RegistrationEvent
   */
  const REGISTRATION_WAITLIST_WAITLISTED = 'registration_waitlist.registration.waitlisted';

}
