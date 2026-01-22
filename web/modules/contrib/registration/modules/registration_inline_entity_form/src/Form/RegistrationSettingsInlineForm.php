<?php

namespace Drupal\registration_inline_entity_form\Form;

use Drupal\inline_entity_form\Form\EntityInlineForm;

/**
 * Defines the inline form for registration settings.
 */
class RegistrationSettingsInlineForm extends EntityInlineForm {

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeLabels(): array {
    return [
      'singular' => $this->t('setting'),
      'plural' => $this->t('settings'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getTableFields($bundles): array {
    $fields = parent::getTableFields($bundles);
    $fields['label']['label'] = $this->t('Title');
    $fields['capacity'] = [
      'type' => 'field',
      'label' => $this->t('Capacity'),
      'weight' => 10,
    ];
    $fields['status'] = [
      'type' => 'field',
      'label' => $this->t('Status'),
      'weight' => 100,
      'display_options' => [
        'settings' => [
          'format' => 'custom',
          'format_custom_true' => $this->t('Enabled'),
          'format_custom_false' => $this->t('Disabled'),
        ],
      ],
    ];

    return $fields;
  }

}
