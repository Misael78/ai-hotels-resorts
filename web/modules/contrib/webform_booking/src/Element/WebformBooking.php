<?php

namespace Drupal\webform_booking\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElement;

/**
 * Provides a custom form element for booking.
 *
 * @FormElement("webform_booking")
 */
class WebformBooking extends FormElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#input' => TRUE,
      '#size' => 60,
      '#process' => [[$class, 'processWebformBooking']],
      '#element_validate' => [[$class, 'validateWebformBooking']],
      '#theme_wrappers' => ['form_element'],
    ];
  }

  /**
   * Process webform booking element.
   */
  public static function processWebformBooking(&$element, FormStateInterface $form_state, &$complete_form) {
    // Custom processing for the element.
    return $element;
  }

  /**
   * Validation for the element.
   */
  public static function validateWebformBooking(&$element, FormStateInterface $form_state, &$complete_form) {
    $value = $element['#value'];

    // Try to create a DateTime object with the specified format.
    $dateTime = \DateTime::createFromFormat('Y-m-d H:i', $value);

    // Check if the value matches the format and if it is a valid date and time.
    if ($dateTime === FALSE || $dateTime->format('Y-m-d H:i') !== $value) {
      // If not, set an error.
      $form_state->setError($element, t('The provided value must be in the format YYYY-MM-DD HH:MM, for example, 2024-03-12 14:30.'));
    }
  }

}
