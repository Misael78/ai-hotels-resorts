<?php

namespace Drupal\registration_waitlist\Plugin\Field;

use Drupal\registration\Plugin\Field\RegistrationItemFieldItemList as BaseRegistrationItemFieldItemList;
use Drupal\registration\RegistrationHelper;

/**
 * Extends the item list class for registration fields.
 */
class RegistrationItemFieldItemList extends BaseRegistrationItemFieldItemList {

  /**
   * Gets fallback default registration settings.
   *
   * These hardcoded values are a fallback when no default values have been
   * assigned to a given registration field yet.
   *
   * @return array
   *   The default settings.
   */
  public function getFallbackSettings(): array {
    $fallback_values = parent::getFallbackSettings();

    $waitlist_fallback_values = [
      'registration_waitlist_enable' => FALSE,
      'registration_waitlist_capacity' => 0,
      'registration_waitlist_message_enable' => FALSE,
      'registration_waitlist_message' => 'Please note: completing this registration form will place you on a waitlist as there are currently no places left.',
    ];

    // Return the values, expanded so the structure matches that used by field
    // item lists.
    return array_merge($fallback_values, RegistrationHelper::expand($waitlist_fallback_values));
  }

}
