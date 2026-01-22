<?php

namespace Drupal\easy_carousel\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'color_widget' widget.
 *
 * @FieldWidget(
 *   id = "color_widget",
 *   label = @Translation("Color input"),
 *   field_types = {
 *     "string"
 *   },
 * )
 */
class ColorWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $value = isset($items[$delta]->value) ? $items[$delta]->value : '#FFFFFF';
    $element['value'] = [
      '#type' => 'color',
      '#title' => $this->fieldDefinition->getLabel(),
      '#default_value' => $value,
      '#size' => 10,
      '#maxlength' => 10,
    ];
    return $element;
  }
}
