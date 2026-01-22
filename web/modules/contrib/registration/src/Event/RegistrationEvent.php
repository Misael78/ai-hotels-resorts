<?php

namespace Drupal\registration\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\registration\Entity\RegistrationInterface;

/**
 * Defines the event for load and CRUD operations on registrations.
 *
 * @see \Drupal\registration\Event\RegistrationEvents
 */
class RegistrationEvent extends Event {

  /**
   * The registration.
   *
   * @var \Drupal\registration\Entity\RegistrationInterface
   */
  protected RegistrationInterface $registration;

  /**
   * Constructs a new RegistrationEvent.
   *
   * @param \Drupal\registration\Entity\RegistrationInterface $registration
   *   The registration.
   */
  public function __construct(RegistrationInterface $registration) {
    $this->registration = $registration;
  }

  /**
   * Gets the registration.
   *
   * @return \Drupal\registration\Entity\RegistrationInterface
   *   The registration.
   */
  public function getRegistration(): RegistrationInterface {
    return $this->registration;
  }

}
