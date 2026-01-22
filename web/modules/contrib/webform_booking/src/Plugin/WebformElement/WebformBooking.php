<?php

namespace Drupal\webform_booking\Plugin\WebformElement;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Plugin\WebformElementBase;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Provides a 'webform_booking' element.
 *
 * @WebformElement(
 *   id = "webform_booking",
 *   label = @Translation("Booking"),
 *   description = @Translation("Provides a webform element for scheduling appointments."),
 *   category = @Translation("Booking"),
 * )
 *
 * @FormElement("webform_webform_booking")
 *
 * @see \Drupal\webform\Plugin\WebformElementBase
 * @see \Drupal\webform\Plugin\WebformElementInterface
 * @see \Drupal\webform\Annotation\WebformElement
 */
class WebformBooking extends WebformElementBase {

  /**
   * {@inheritdoc}
   */
  protected function defineDefaultProperties() {
    $properties = parent::defineDefaultProperties() + [
      'title' => '',
      'start_date' => '',
      'end_date' => '',
      'exclusion_dates' => '',
      'days_advance' => '0',
      'excluded_weekdays' => [],
      'time_interval' => '',
      'slot_duration' => '',
      'seats_slot' => '1',
      'no_slots' => 'No slots available.',
    ];
    unset($properties['markup']);
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    unset($form['markup'], $form['default']);
    $form['form']['#access'] = FALSE;

    $form['appointment_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Webform Booking Settings'),
    ];

    $form['appointment_settings']['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#default_value' => $this->getDefaultProperty('title'),
    ];

    // Start Date.
    $form['appointment_settings']['start_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Start Date'),
      '#default_value' => $this->getDefaultProperty('start_date'),
    ];

    // End Date.
    $form['appointment_settings']['end_date'] = [
      '#type' => 'date',
      '#title' => $this->t('End Date'),
      '#default_value' => $this->getDefaultProperty('end_date'),
    ];
    // Days in advance.
    $form['appointment_settings']['days_advance'] = [
      '#type' => 'number',
      '#title' => $this->t('Days in Advance'),
      '#default_value' => $this->getDefaultProperty('days_advance'),
      '#description' => $this->t('Number of days in advance a booking can be made'),
    ];
    // Days visible.
    $form['appointment_settings']['days_visible'] = [
      '#type' => 'number',
      '#title' => $this->t('Days Visible'),
      '#description' => $this->t('Number of days from now a booking can be made'),
    ];
    // Exclusion Dates.
    $example_dates = $this->getExampleDates();
    $form['appointment_settings']['exclusion_dates'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Exclusion Dates'),
      '#description' => $this->t('Enter dates in format YYYY-MM-DD or intervals in format YYYY-MM-DD|YYYY-MM-DD, one per line.<br>Ex.<br>@example_date_single<br>@example_date_multi', [
        '@example_date_single' => $example_dates['single'],
        '@example_date_multi' => $example_dates['multi'],
      ]),
      '#default_value' => $this->getDefaultProperty('exclusion_dates'),
      '#rows' => 3,
      '#element_validate' => [[get_class($this), 'validateExclusionDates']],
    ];

    // Excluded Weekdays.
    $form['appointment_settings']['excluded_weekdays'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Excluded Weekdays'),
      '#options' => [
        'Mon' => $this->t('Mon'),
        'Tue' => $this->t('Tue'),
        'Wed' => $this->t('Wed'),
        'Thu' => $this->t('Thu'),
        'Fri' => $this->t('Fri'),
        'Sat' => $this->t('Sat'),
        'Sun' => $this->t('Sun'),
      ],
      '#default_value' => $this->getDefaultProperty('excluded_weekdays'),
    ];

    // Time intervals.
    $form['appointment_settings']['time_interval'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Time Intervals'),
      '#description' => $this->t('Enter time intervals in 24h format. One interval per line. Ex. 08:00|12:00. You can also add exceptions for specific days (weekdays in English). Ex: 10:00|12:00(Friday)'),
      '#default_value' => $this->getDefaultProperty('time_interval'),
      '#required' => TRUE,
      '#element_validate' => [[get_class($this), 'validateTimeIntervals']],
    ];

    // Slot Duration.
    $form['appointment_settings']['slot_duration'] = [
      '#type' => 'number',
      '#title' => $this->t('Slot Duration'),
      '#description' => $this->t('In Minutes'),
      '#default_value' => $this->getDefaultProperty('slot_duration'),
      '#min' => 1,
      '#required' => TRUE,
    ];

    // Seats per slot.
    $form['appointment_settings']['seats_slot'] = [
      '#type' => 'number',
      '#title' => $this->t('Seats Per Slot'),
      '#description' => $this->t('Number of bookings for the same slot'),
      '#default_value' => $this->getDefaultProperty('seats_slot'),
      '#min' => 1,
      '#required' => TRUE,
    ];

    // No slots available message.
    $form['appointment_settings']['no_slots'] = [
      '#type' => 'textarea',
      '#title' => $this->t('No slots available message'),
      '#description' => $this->t('Insert the text that will be displayed when there are no slots available.'),
      '#default_value' => $this->getDefaultProperty('no_slots'),
      '#rows' => 3,
    ];
    return $form;
  }

  /**
   * Generate example dates for exclusion dates.
   *
   * @return array
   *   An associative array with example single and multi dates.
   */
  protected static function getExampleDates() {
    $example_date_single = date('Y-m-d');
    $example_date_multi = date('Y-m-d', strtotime('first day of next month')) . '|' . date('Y-m-d', strtotime('last day of next month'));
    return [
      'single' => $example_date_single,
      'multi' => $example_date_multi,
    ];
  }

  /**
   * Validate time intervals.
   */
  public static function validateTimeIntervals(array &$element, FormStateInterface $form_state) {
    $time_intervals = $form_state->getValue($element['#parents']);

    // Ensure $time_intervals is a string.
    if (is_array($time_intervals)) {
      $time_intervals = reset($time_intervals);
    }

    $time_interval_pattern = '/^(\d{1,2}:\d{2})\|(\d{1,2}:\d{2})(\((Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday)\))?$/';
    $lines = preg_split('/\r\n|\r|\n/', $time_intervals);

    foreach ($lines as $line) {
      $line = trim($line);
      if (!empty($line) && !preg_match($time_interval_pattern, $line)) {
        // Check if the line matches the incomplete interval pattern.
        $incomplete_interval_pattern = '/^(\d{1,2}:\d{2})\|?$/';
        if (preg_match($incomplete_interval_pattern, $line)) {
          $error_message = t('Incomplete Time Interval. Both start and end times are required in the format HH:MM|HH:MM. Example: 08:00|12:00');
        }
        else {
          $error_message = t('Invalid Time Intervals. Enter time intervals in 24h format HH:MM|HH:MM, one interval per line. You can also add exceptions for specific days (weekdays in English).<br>Ex.<br>08:00|12:00<br>10:00|12:00(Friday)');
        }
        $form_state->setError($element, $error_message);
        break;
      }
    }
  }

  /**
   * Validate exclusion dates.
   */
  public static function validateExclusionDates(array &$element, FormStateInterface $form_state) {
    $exclusion_dates = $form_state->getValue(['properties', 'exclusion_dates']);

    // Ensure $exclusion_dates is a string.
    if (is_array($exclusion_dates)) {
      $exclusion_dates = reset($exclusion_dates);
    }

    $date_pattern = '/^(\d{4}-\d{2}-\d{2})(\|\d{4}-\d{2}-\d{2})?$/';
    $lines = preg_split('/\r\n|\r|\n/', $exclusion_dates);
    foreach ($lines as $line) {
      if (!empty($line) && !preg_match($date_pattern, $line)) {
        $example_dates = self::getExampleDates();
        $error_message = t('Invalid Exclusion Dates. Enter dates in format YYYY-MM-DD or intervals in format YYYY-MM-DD|YYYY-MM-DD, one per line.<br>Ex.<br>@example_date_single<br>@example_date_multi', [
          '@example_date_single' => $example_dates['single'],
          '@example_date_multi' => $example_dates['multi'],
        ]);
        $form_state->setError($element, $error_message);
        break;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function prepare(array &$element, WebformSubmissionInterface $webform_submission = NULL) {
    // Ensure the library is attached only once.
    $element['#attached']['library'][] = 'webform_booking/webform_booking';

    $elementId = $element['#webform_key'];

    if (!isset($element['#attached']['drupalSettings']['webform_booking']['elements'])) {
      $element['#attached']['drupalSettings']['webform_booking']['elements'] = [];
    }

    // Use element ID as a key to ensure unique settings for each element.
    $element['#attached']['drupalSettings']['webform_booking']['elements'][$elementId] = [
      'formId' => $element['#webform'],
      'elementId' => $elementId,
      'startDate' => $element['#start_date'] ?? '',
      'endDate' => $element['#end_date'] ?? '',
      'noSlots' => $element['#no_slots'] ?? '',
    ];

    // Set up the HTML structure for the calendar and slots.
    $element['#description'] = '<div id="appointment-wrapper-' . $elementId . '"><div id="calendar-container-' . $elementId . '"></div><div id="slots-container-' . $elementId . '"></div></div>';

    $element['#type'] = 'textfield';
    $element['#attributes'] = ['id' => 'selected-slot-' . $elementId];
    if (isset($element["#states"]["required"])) {
      unset($element["#states"]["required"]);
      $element['#attached']['drupalSettings']['webform_booking']['elements'][$elementId]['required'] = TRUE;
    }

    $user = \Drupal::currentUser();
    if (!$user->hasPermission('edit any webform submission')) {
      $element['#attributes']['class'][] = 'hide';
    }
    // Add custom validation for empty slots.
    $element['#element_validate'][] = [get_class($this), 'validateEmptySlot'];

    return $element;
  }

  /**
   * Add a highlight class to the description if a required field is empty.
   *
   * @param array $element
   *   Element to validate.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function validateEmptySlot(array &$element, FormStateInterface $form_state) {
    // Check if the element is required.
    if (!empty($element['#required']) && empty($element['#value'])) {
      $elementId = $element['#webform_key'];
      $element['#description'] = '<div id="appointment-wrapper-' . $elementId . '"><div id="calendar-container-' . $elementId . '"></div><div id="slots-container-' . $elementId . '" class="highlight"></div></div>';

      // Set an error for the form element.
      $form_state->setErrorByName($element['#name'], t('Please select a slot'));
    }
  }

}
