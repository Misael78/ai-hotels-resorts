<?php

namespace Drupal\registration\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Defines the registration save event.
 *
 * @see \Drupal\registration\Event\RegistrationEvents
 */
class RegistrationSaveEvent extends Event {

  /**
   * The save context.
   *
   * @var array
   */
  protected array $context;

  /**
   * Whether the event was handled already.
   *
   * @var bool
   */
  protected bool $handled;

  /**
   * Constructs a new RegistrationSaveEvent.
   *
   * @param array $context
   *   The save context.
   */
  public function __construct(array $context) {
    $this->context = $context;
    $this->handled = FALSE;
  }

  /**
   * Gets the save context.
   *
   * @return array
   *   The save context.
   */
  public function getContext(): array {
    return $this->context;
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
