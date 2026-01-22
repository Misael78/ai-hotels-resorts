<?php

namespace Drupal\registration\Plugin\Field;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Form\FormStateInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\registration\HostEntityInterface;
use Drupal\registration\RegistrationHelper;

/**
 * Defines an item list class for registration fields.
 *
 * Overrides the default values form for a registration field
 * to append a subform for the default registration settings.
 */
class RegistrationItemFieldItemList extends FieldItemList implements RegistrationItemFieldItemListInterface {

  /**
   * {@inheritdoc}
   */
  public function createHostEntity(?string $langcode = NULL): HostEntityInterface {
    $handler = \Drupal::entityTypeManager()->getHandler($this->getEntity()->getEntityTypeId(), 'registration_host_entity');
    return $handler->createHostEntity($this->getEntity(), $langcode);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultValuesForm(array &$form, FormStateInterface $form_state) {
    // Append the registration settings default values form to the standard
    // default values form.
    $element = parent::defaultValuesForm($form, $form_state);
    $element += $this->settingsDefaultValuesForm($form_state, 'registration_settings');
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultValuesFormSubmit(array $element, array &$form, FormStateInterface $form_state) {
    // Append the registration settings default values to the standard value.
    $value = parent::defaultValuesFormSubmit($element, $form, $form_state);

    $settings = $form_state->getValue([
      'default_value_input',
      'registration_settings',
    ]);
    $value[0]['registration_settings'] = serialize($this->massageFormValues($settings));

    return $value;
  }

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
    $fallback_values = [
      'status' => FALSE,
      'capacity' => 0,
      'send_reminder' => FALSE,
      'maximum_spaces' => 1,
      'multiple_registrations' => FALSE,
      'from_address' => \Drupal::config('system.site')->get('mail'),
      'confirmation' => 'Registration has been saved.',
    ];

    // Return the values, expanded so the structure matches that used by field
    // item lists.
    return RegistrationHelper::expand($fallback_values);
  }

  /**
   * Gets submitted form values into a usable structure.
   *
   * @param array $settings
   *   The input settings array.
   *
   * @return array
   *   The massaged settings array.
   */
  protected function massageFormValues(array $settings): array {
    $settings = array_map(function (mixed $item) {
      if (is_array($item)) {
        // Loop through the property values and massage as needed. The
        // properties that need massaging have a "value" key.
        foreach ($item as $key => $value) {
          if (is_array($value) && array_key_exists('value', $value)) {
            // Filter out nulls and blanks.
            if (is_null($value['value']) || ($value['value'] === '')) {
              unset($item[$key]);
            }

            // Convert dates to storage format and timezone (UTC).
            elseif ($value['value'] instanceof DrupalDateTime) {
              $storage_timezone = new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE);
              $value['value']->setTimezone($storage_timezone);
              $item[$key]['value'] = $value['value']
                ->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
            }
          }
        }
      }
      return $item;
    }, $settings);

    // Remove any empty remaining structure.
    return array_filter($settings);
  }

  /**
   * Gets the default values registration settings form.
   *
   * Uses the form display for the registration settings entity. This allows
   * site builders to extend the settings with additional fields, or hide
   * base fields, if needed. Those changes are picked up automatically here.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $entity_type_id
   *   The entity type ID for registration settings.
   *
   * @return array
   *   The default settings form.
   */
  protected function settingsDefaultValuesForm(FormStateInterface $form_state, string $entity_type_id): array {
    // Initialize the element with a fieldset to contain the settings fields.
    $element = [];
    $element[$entity_type_id] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Registration settings'),
      '#parents' => [
        'default_value_input',
        $entity_type_id,
      ],
    ];

    // Get stored default values and fill missing keys with global defaults.
    $values = $this[0]->getValue();
    if (!array_key_exists($entity_type_id, $values)) {
      $values[$entity_type_id] = [];
    }
    else {
      $values[$entity_type_id] = unserialize($values[$entity_type_id]);
    }
    $values[$entity_type_id] += $this->getFallbackSettings();
    $values = $values[$entity_type_id];

    // Create an entity with the default values and retrieve its form display.
    $entity = \Drupal::entityTypeManager()
      ->getStorage($entity_type_id)
      ->create($values);

    /** @var \Drupal\Core\Entity\FieldableEntityInterface $entity */
    $form_object = \Drupal::entityTypeManager()
      ->getFormObject($entity->getEntityTypeId(), 'default');
    $form_object->setEntity($entity);
    $form_display = EntityFormDisplay::collectRenderDisplay($entity, 'default');

    // Add each entity form field to the element.
    foreach ($form_display->getComponents() as $name => $options) {
      if ($widget = $form_display->getRenderer($name)) {
        $element[$entity_type_id][$name] = $widget->form($entity->get($name), $element[$entity_type_id], $form_state);
        $element[$entity_type_id][$name]['#weight'] = $options['weight'];
      }
    }

    return $element;
  }

}
