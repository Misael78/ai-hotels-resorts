<?php

namespace Drupal\registration_scheduled_action\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElement;

/**
 * Provides a form input element for a datetime.
 *
 * @FormElement("registration_scheduled_action_datetime")
 */
class DateTime extends FormElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo(): array {
    $class = get_class($this);
    return [
      '#input' => TRUE,
      '#multiple' => FALSE,
      '#process' => [
        [$class, 'processDateTime'],
      ],
    ];
  }

  /**
   * Process callback.
   */
  public static function processDateTime(&$element, FormStateInterface $form_state, &$complete_form) {
    $default_value = $element['#default_value'];
    $element['#tree'] = TRUE;
    $element['values'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'form--inline',
        ],
      ],
      '#suffix' => '<div class="clearfix"></div>',
    ];
    $element['values']['length'] = [
      '#title' => $element['#title'],
      '#type' => 'number',
      '#min' => $element['#min'],
      '#max' => $element['#max'],
      '#size' => $element['#size'],
      '#required' => $element['#required'],
      '#default_value' => $default_value['length'] ?? 7,
    ];
    $element['values']['type'] = [
      '#title' => '&nbsp;',
      '#type' => 'select',
      '#required' => FALSE,
      '#options' => [
        'minutes' => t('Minutes'),
        'hours' => t('Hours'),
        'days' => t('Days'),
        'months' => t('Months'),
      ],
      '#default_value' => $default_value['type'] ?? 'days',
    ];
    $element['values']['position'] = [
      '#title' => '&nbsp;',
      '#type' => 'select',
      '#required' => FALSE,
      '#options' => [
        'before' => t('Before'),
        'after' => t('After'),
      ],
      '#default_value' => $default_value['position'] ?? 'before',
    ];
    return $element;
  }

}
