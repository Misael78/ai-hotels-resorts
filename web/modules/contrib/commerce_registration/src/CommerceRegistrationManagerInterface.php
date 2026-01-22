<?php

namespace Drupal\commerce_registration;

/**
 * Defines the interface for the commerce registration manager service.
 */
interface CommerceRegistrationManagerInterface {

  /**
   * Gets the product ID from a views argument.
   *
   * The argument is assumed to be in the form "1+2+3", representing product
   * variation IDs in a list.
   *
   * @param string $argument
   *   The argument.
   *
   * @return int|null
   *   The product ID, if available.
   */
  public function getProductIdFromArgument(string $argument): ?int;

}
