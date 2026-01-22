<?php

namespace Drupal\registration\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\registration\Entity\RegistrationSettings;

/**
 * Defines the event for operations on registration settings.
 *
 * @see \Drupal\registration\Event\RegistrationEvents
 */
class RegistrationSettingsEvent extends Event {

  /**
   * The settings.
   *
   * @var \Drupal\registration\Entity\RegistrationSettings
   */
  protected RegistrationSettings $settings;

  /**
   * Constructs a new RegistrationSettingsEvent.
   *
   * @param \Drupal\registration\Entity\RegistrationSettings $settings
   *   The settings.
   */
  public function __construct(RegistrationSettings $settings) {
    $this->settings = $settings;
  }

  /**
   * Gets the settings.
   *
   * @return \Drupal\registration\Entity\RegistrationSettings
   *   The settings.
   */
  public function getSettings(): RegistrationSettings {
    return $this->settings;
  }

}
