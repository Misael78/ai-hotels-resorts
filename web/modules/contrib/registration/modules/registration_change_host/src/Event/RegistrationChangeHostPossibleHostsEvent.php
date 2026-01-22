<?php

namespace Drupal\registration_change_host\Event;

use Drupal\registration\Entity\RegistrationInterface;
use Drupal\registration\Event\RegistrationEvent;
use Drupal\registration_change_host\PossibleHostSet;
use Drupal\registration_change_host\PossibleHostSetInterface;

/**
 * Defines the event for gathering hosts the registration could be changed to.
 *
 * Subscribers are allowed to add, remove or modify hosts from the set.
 *
 * @see \Drupal\registration_change_host\Event\RegistrationChangeHostEvents
 * @see \Drupal\registration_change_host\RegistrationChangeHostManager
 */
class RegistrationChangeHostPossibleHostsEvent extends RegistrationEvent {

  /**
   * The set of hosts the registration could be changed to.
   */
  protected PossibleHostSetInterface $hosts;

  /**
   * Constructs a new RegistrationEvent.
   *
   * @param \Drupal\registration\Entity\RegistrationInterface $registration
   *   The registration.
   */
  public function __construct(RegistrationInterface $registration) {
    parent::__construct($registration);
    $this->hosts = new PossibleHostSet($registration);
  }

  /**
   * Gets a set of possible hosts the registration could be changed to.
   *
   * @return \Drupal\registration_change_host\PossibleHostSetInterface
   *   The possible hosts.
   */
  public function getPossibleHostsSet() {
    return $this->hosts;
  }

}
