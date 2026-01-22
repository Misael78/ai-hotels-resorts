<?php

namespace Drupal\registration\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the Registration field type.
 *
 * @FieldType(
 *   id = "registration",
 *   label = @Translation("Registration"),
 *   description = @Translation("Field to enable registration for an event or other entity."),
 *   default_widget = "registration_type",
 *   default_formatter = "registration_type",
 *   cardinality = 1,
 *   list_class = "\Drupal\registration\Plugin\Field\RegistrationItemFieldItemList",
 * )
 */
class RegistrationItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition): array {
    return [
      'columns' => [
        'registration_type' => [
          'description' => 'The type of registration that should be enabled.',
          'type' => 'varchar',
          'length' => 32,
          'not null' => TRUE,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition): array {
    $properties['registration_type'] = DataDefinition::create('string')
      ->setLabel(t('Registration type'))
      ->setDescription(t('The type of registration to create for an entity.'))
      ->setRequired(FALSE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty(): bool {
    $value = $this->get('registration_type')->getValue();
    return ($value === NULL) || ($value === '');
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    return [
      'allowed_types' => [],
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    $options = [];
    $types = \Drupal::entityTypeManager()->getStorage('registration_type')->loadMultiple();
    foreach ($types as $type) {
      $options[$type->id()] = $type->label();
    }

    if (count($options) == 1) {
      $element = [];
      $element['allowed_types'] = [
        '#type' => 'hidden',
        '#value' => array_keys($options),
      ];
    }
    else {
      $element = [];
      $element['allowed_types'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Allowed registration types'),
        '#options' => $options,
        '#default_value' => $this->getSetting('allowed_types') ?? [],
        '#required' => TRUE,
        '#access' => (count($options) > 1),
      ];
    }

    return $element;
  }

}
