<?php

namespace Drupal\registration\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\NumberWidget;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'registration_spaces_default' widget.
 *
 * @FieldWidget(
 *   id = "registration_spaces_default",
 *   label = @Translation("Registration spaces"),
 *   field_types = {
 *     "integer"
 *   }
 * )
 */
class RegistrationSpacesWidget extends NumberWidget {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'hide_single_space' => TRUE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $element['hide_single_space'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide if limited to one space'),
      '#description' => $this->t('Hide the field unless the user can register for more than one space.'),
      '#default_value' => (bool) $this->getSetting('hide_single_space'),
    ];
    return $element + parent::settingsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = parent::settingsSummary();
    if ($this->getSetting('hide_single_space')) {
      $summary[] = $this->t('Hide when the user can only register for one space: Yes');
    }
    else {
      $summary[] = $this->t('Hide when the user can only register for one space: No');
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    /** @var \Drupal\registration\Entity\RegistrationInterface $registration */
    $registration = $items->getEntity();

    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $host_entity = $registration->getHostEntity();
    if (is_null($host_entity)) {
      return $element;
    }
    $settings = $host_entity->getSettings();

    $capacity = $settings->getSetting('capacity');
    $limit = $settings->getSetting('maximum_spaces');
    $remaining = $host_entity->getSpacesRemaining($registration);
    $spaces = $registration->getSpacesReserved();
    $max = 99999;

    // Calculate the maximum spaces and an appropriate field description.
    if ($capacity && $limit) {
      $max = min($limit, $remaining);
      if ($max == $remaining) {
        $description = $this->formatPlural($remaining, 'The number of spaces you wish to reserve. There is 1 space remaining.', 'The number of spaces you wish to reserve. There are @count spaces remaining.');
      }
      elseif ($max == 1) {
        $description = $this->formatPlural($remaining, 'The number of spaces you wish to reserve. There is 1 space remaining.', 'The number of spaces you wish to reserve. There are @count spaces remaining. You may register 1 space.');
      }
      else {
        $description = $this->formatPlural($remaining, 'The number of spaces you wish to reserve. There is 1 space remaining. You may register up to @max spaces.', 'The number of spaces you wish to reserve. There are @count spaces remaining. You may register up to @max spaces.', [
          '@max' => $max,
        ]);
      }
    }
    elseif ($capacity) {
      $max = $remaining;
      $description = $this->formatPlural($remaining, 'The number of spaces you wish to reserve. There is 1 space remaining.', 'The number of spaces you wish to reserve. There are @count spaces remaining.');
    }
    elseif ($limit) {
      $max = $limit;
      $description = $this->formatPlural($max, 'The number of spaces you wish to reserve. You may register 1 space.', 'The number of spaces you wish to reserve. You may register up to @count spaces.');
    }
    else {
      $description = $this->t('The number of spaces you wish to reserve.');
    }

    // Allow an existing registration to keep its reserved spaces, even if the
    // capacity or maximum spaces was reduced after the registration occurred.
    if (!$registration->isNewToHost() && ($spaces > $max)) {
      $max = $spaces;
      $description = $this->t('The number of spaces you wish to reserve.');
    }

    $element['value']['#description'] = $description;
    $element['value']['#default_value'] = $registration->getSpacesReserved();

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
