<?php

namespace Drupal\registration_waitlist\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\registration\Plugin\Field\FieldWidget\RegistrationSpacesWidget as BaseRegistrationSpacesWidget;

/**
 * Extends the implementation of the 'registration_spaces_default' widget.
 */
class RegistrationSpacesWidget extends BaseRegistrationSpacesWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    /** @var \Drupal\registration\Entity\RegistrationInterface $registration */
    $registration = $items->getEntity();

    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    /** @var \Drupal\registration_waitlist\HostEntityInterface $host_entity */
    $host_entity = $registration->getHostEntity();
    $settings = $host_entity->getSettings();

    $capacity = $settings->getSetting('capacity');
    $limit = $settings->getSetting('maximum_spaces');
    $remaining = $host_entity->getSpacesRemaining($registration);
    $waitlist_capacity = $settings->getSetting('registration_waitlist_capacity');
    $waitlist_remaining = $host_entity->getWaitListSpacesRemaining($registration);
    $spaces = $registration->getSpacesReserved();
    $max = $element['value']['#max'] ?? 0;

    // Calculate the maximum spaces and an appropriate field description when
    // the wait list is active.
    $description = '';
    if ($host_entity->shouldAddToWaitList($spaces, $registration)) {
      $capacity = $waitlist_capacity;
      $remaining = $waitlist_remaining;

      if ($capacity && $limit) {
        $max = min($limit, $remaining);
        if ($max == $remaining) {
          $description = $this->formatPlural($remaining, 'The number of spaces you wish to reserve. There is 1 space remaining on the wait list.', 'The number of spaces you wish to reserve. There are @count spaces remaining on the wait list.');
        }
        elseif ($max == 1) {
          $description = $this->formatPlural($remaining, 'The number of spaces you wish to reserve. There is 1 space remaining on the wait list.', 'The number of spaces you wish to reserve. There are @count spaces remaining on the wait list. You may register 1 space.');
        }
        else {
          $description = $this->formatPlural($remaining, 'The number of spaces you wish to reserve. There is 1 space remaining on the wait list.', 'The number of spaces you wish to reserve. There are @count spaces remaining on the wait list. You may register up to @max spaces.', [
            '@max' => $max,
          ]);
        }
      }
      elseif ($capacity) {
        $max = $remaining;
        $description = $this->formatPlural($remaining, 'The number of spaces you wish to reserve. There is 1 space remaining on the wait list.', 'The number of spaces you wish to reserve. There are @count spaces remaining on the wait list.');
      }
      elseif ($limit) {
        $max = $limit;
        $description = $this->formatPlural($max, 'The number of spaces you wish to reserve on the wait list. You may register 1 space.', 'The number of spaces you wish to reserve on the wait list. You may register up to @count spaces.');
      }
      else {
        $max = 99999;
        $description = $this->t('The number of spaces you wish to reserve on the wait list.');
      }
    }

    // Calculate the maximum spaces and an appropriate field description when
    // the wait list is not active yet, but could become active for some of the
    // spaces reserved depending on the number of spaces requested.
    elseif ($host_entity->isWaitListEnabled() && $host_entity->hasRoomOffWaitList($spaces, $registration) && $host_entity->hasRoomOnWaitList($spaces, $registration)) {

      // There are limits on standard capacity, wait list capacity, and the
      // maximum number of spaces per registration.
      if ($capacity && $limit && $waitlist_capacity) {
        $total_remaining = $remaining + $waitlist_remaining;
        $max = min($limit, $total_remaining);
        if ($max == $total_remaining) {
          $description = $this->formatPlural($remaining, 'The number of spaces you wish to reserve. There is 1 space remaining, plus @waitlist_remaining remaining on the wait list. Registering for more than 1 space will place the registration on the wait list.', 'The number of spaces you wish to reserve. There are @count spaces remaining, plus @waitlist_remaining remaining on the wait list. Registering for more than @count spaces will place the registration on the wait list.', [
            '@waitlist_remaining' => $waitlist_remaining,
          ]);
        }
        elseif ($max > $remaining) {
          $description = $this->formatPlural($remaining, 'The number of spaces you wish to reserve. There is 1 space remaining, plus @waitlist_remaining remaining on the wait list. You may register up to @max spaces, although registering for more than 1 space will place the registration on the wait list.', 'The number of spaces you wish to reserve. There are @count spaces remaining, plus @waitlist_remaining remaining on the wait list. You may register up to @max spaces, although registering for more than @count spaces will place the registration on the wait list.', [
            '@waitlist_remaining' => $waitlist_remaining,
            '@max' => $max,
          ]);
        }
      }

      // There are limits on standard capacity and the maximum number of spaces
      // per registration.
      elseif ($capacity && $limit) {
        $max = $limit;
        if ($max > $remaining) {
          $description = $this->formatPlural($remaining, 'The number of spaces you wish to reserve. There is 1 space remaining. You may register up to @max spaces, although registering for more than 1 space will place the registration on the wait list.', 'The number of spaces you wish to reserve. There are @count spaces remaining. You may register up to @max spaces, although registering for more than @count spaces will place the registration on the wait list.', [
            '@max' => $max,
          ]);
        }
      }

      // There are limits on standard capacity and wait list capacity.
      elseif ($capacity && $waitlist_capacity) {
        $max = $remaining + $waitlist_remaining;
        $description = $this->formatPlural($remaining, 'The number of spaces you wish to reserve. There is 1 space remaining, plus @waitlist_remaining remaining on the wait list. Registering for more than 1 space will place the registration on the wait list.', 'The number of spaces you wish to reserve. There are @count spaces remaining, plus @waitlist_remaining remaining on the wait list. Registering for more than @count spaces will place the registration on the wait list.', [
          '@waitlist_remaining' => $waitlist_remaining,
        ]);
      }

      // There is a limit on standard capacity.
      elseif ($capacity) {
        $max = 99999;
        $description = $this->formatPlural($remaining, 'The number of spaces you wish to reserve. There is 1 space remaining. Registering for more than 1 space will place the registration on the wait list.', 'The number of spaces you wish to reserve. There are @count spaces remaining. Registering for more than @count spaces will place the registration on the wait list.');
      }
    }

    // Use the field description calculated by the standard widget for an
    // existing registration.
    if (!$registration->isNew()) {
      $description = '';
    }

    // Allow an existing registration to keep its reserved spaces, even if the
    // capacity or maximum spaces was reduced after the registration occurred.
    if (!$registration->isNewToHost() && ($spaces > $max)) {
      $max = $spaces;
      $description = $this->t('The number of spaces you wish to reserve.');
    }

    if ($description) {
      $element['value']['#description'] = $description;
    }

    // Hide the element unless the user can register for more than one space,
    // or the "hide single space" option is disabled and the user can register
    // for exactly one space.
    $element['value']['#access'] = ($max > 1) || (($max == 1) && !$this->getSetting('hide_single_space'));

    // Set a maximum in the widget, as long as the max is valid. In some rare
    // cases the maximum could be negative or zero if the form is being rendered
    // after the capacity limit was changed or new registrations were saved.
    if ($max > 0) {
      $element['value']['#max'] = $max;
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition): bool {
    return $field_definition->getName() === 'count' && $field_definition->getTargetEntityTypeId() === 'registration';
  }

}
