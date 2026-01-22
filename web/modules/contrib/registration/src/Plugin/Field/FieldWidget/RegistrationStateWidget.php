<?php

namespace Drupal\registration\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsSelectWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\registration\Entity\RegistrationInterface;

/**
 * Plugin implementation of the 'registration_state_default' widget.
 *
 * @FieldWidget(
 *   id = "registration_state_default",
 *   label = @Translation("Registration state"),
 *   field_types = {
 *     "string"
 *   }
 * )
 */
class RegistrationStateWidget extends OptionsSelectWidget {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'hide_single_state' => TRUE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $element = parent::settingsForm($form, $form_state);
    $element['hide_single_state'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide single state'),
      '#description' => $this->t('Hide the field unless more than one state is available.'),
      '#default_value' => (bool) $this->getSetting('hide_single_state'),
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = parent::settingsSummary();
    if ($this->getSetting('hide_single_state')) {
      $summary[] = $this->t('Hide when only one registration state is available: Yes');
    }
    else {
      $summary[] = $this->t('Hide when only one registration state is available: No');
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    /** @var \Drupal\registration\Entity\RegistrationInterface $entity */
    $entity = $items->getEntity();

    $options = $this->getStateOptions($entity);

    $element = $element + [
      '#type' => 'select',
      '#options' => $options,
      '#default_value' => $entity->getState()->id(),
    ];

    // Hide the field in certain cases.
    if (empty($options)) {
      // No states configured yet.
      $element['#access'] = FALSE;
    }
    elseif ($this->getSetting('hide_single_state') && (count($options) == 1)) {
      // Only one state would be available and the hide setting is enabled.
      $element['#access'] = FALSE;
    }
    else {
      $registration_type = $entity->getType();
      $type = $registration_type->id();
      // Hide the field unless the user has permission to edit the state.
      $element['#access'] = $entity->access("edit state");
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state) {
    $field_name = $this->fieldDefinition->getName();

    // Extract the values from $form_state->getValues().
    $path = array_merge($form['#parents'], [$field_name]);
    $key_exists = NULL;
    $values = NestedArray::getValue($form_state->getValues(), $path, $key_exists);

    if ($key_exists) {
      // Assign the values and remove the empty ones.
      $items->setValue($values);
      $items->filterEmptyItems();

      // Put delta mapping in $form_state, so that flagErrors() can use it.
      $field_state = static::getWidgetState($form['#parents'], $field_name, $form_state);
      foreach ($items as $delta => $item) {
        $field_state['original_deltas'][$delta] = $item->_original_delta ?? $delta;
        unset($item->_original_delta, $item->_weight);
      }
      static::setWidgetState($form['#parents'], $field_name, $form_state, $field_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition): bool {
    return $field_definition->getName() === 'state' && $field_definition->getTargetEntityTypeId() === 'registration';
  }

  /**
   * Gets the available registration state options.
   *
   * @param \Drupal\registration\Entity\RegistrationInterface $registration
   *   The registration entity.
   *
   * @return array
   *   The states as an options array of labels keyed by ID.
   */
  protected function getStateOptions(RegistrationInterface $registration): array {
    $options = [];
    $current_state = $registration->getState();
    $states = $registration->getType()->getStatesToShowOnForm($current_state, !$registration->isNewToHost());
    foreach ($states as $id => $state) {
      $options[$id] = $state->label();
    }
    return $options;
  }

}
