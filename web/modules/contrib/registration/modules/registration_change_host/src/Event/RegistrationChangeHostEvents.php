<?php

namespace Drupal\registration_change_host\Event;

/**
 * Events fired by the Registration Change Host module.
 */
final class RegistrationChangeHostEvents {

  /**
   * Name of the event fired to collect possible hosts.
   *
   * These are the hosts a registration could be changed to.
   *
   * @Event
   *
   * @see \Drupal\registration_change_host\Event\RegistrationChangeHostPossibleHostsEvent
   */
  const REGISTRATION_CHANGE_HOST_POSSIBLE_HOSTS = 'registration_change_host.possible_hosts';

}
