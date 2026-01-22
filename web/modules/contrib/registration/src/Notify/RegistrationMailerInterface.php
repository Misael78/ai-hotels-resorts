<?php

namespace Drupal\registration\Notify;

use Drupal\registration\HostEntityInterface;

/**
 * Defines the interface for the registration notification service.
 */
interface RegistrationMailerInterface {

  /**
   * Gets the list of recipients to send notifications to.
   *
   * @param \Drupal\registration\HostEntityInterface $host_entity
   *   The host entity.
   * @param array $data
   *   (Optional)
   *   Contextual data with information about the usage of the list.
   *   This will be passed to events triggered by this method.
   *   For example, values submitted from the broadcast email form
   *   will be included here when that form is requesting the list.
   *
   *   To filter on certain states, pass an array of states, for example:
   *   $data['states'] = [
   *     'complete',
   *     'held',
   *   ]];
   *
   *   To indicate the list will be used in a test, pass the following:
   *   $data['test'] = TRUE;
   *   This will result in a single email address for the logged in user,
   *   referencing a synthetic (unsaved) registration generated for test use.
   *   To replace this test data with your own, use an event subscriber to
   *   modify the list.
   *
   *   Other data needed by your event subscribers can be set into this
   *   array, and it will be passed to your event handlers.
   *
   * @return array
   *   In this default implementation, the recipient list is an associative
   *   array indexed by email address:
   *   [email_address => $registration_entity]
   *
   *   If a user has registered for an event more than once, the registration
   *   entity will be replaced with an array of registration entities instead.
   *   The registration_entity can be NULL; this may occur if an event handler
   *   adds an email address to the list, but does not have a registration to
   *   include. In this case the specified recipient will still be notified,
   *   but tokens related to registrations will be removed from the message
   *   instead of being replaced.
   *
   *   Decorate this service provider to notify via text message or other means.
   */
  public function getRecipientList(HostEntityInterface $host_entity, array $data = []): array;

  /**
   * Sends a notification to registrants associated with a given host entity.
   *
   * In this default implementation, registrants are notified via email.
   * Decorate this service provider to notify via text message or other means.
   *
   * @param \Drupal\registration\HostEntityInterface $host_entity
   *   The host entity.
   * @param array $data
   *   (optional) Data as documented above.
   *
   * @return int
   *   The number of emails sent.
   */
  public function notify(HostEntityInterface $host_entity, array $data = []): int;

}
