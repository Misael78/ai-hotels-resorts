<?php

namespace Drupal\Tests\registration\Kernel;

use Drupal\registration\RegistrationFieldManager as BaseRegistrationFieldManager;

/**
 * Overrides field definitions for testing.
 */
class RegistrationFieldManager extends BaseRegistrationFieldManager {

  /**
   * {@inheritdoc}
   */
  public function getFieldDefinitionsForLanguage(string $entity_type_id, string $bundle, ?string $langcode): array {
    $definitions = parent::getFieldDefinitionsForLanguage($entity_type_id, $bundle, $langcode);
    if (($entity_type_id == 'node') && ($bundle == 'event')) {
      switch ($langcode) {
        // English.
        case 'en':
          // Use standard values from the registration_test module.
          $default_settings = [
            'status' => [
              'value' => TRUE,
            ],
            'capacity' => [
              0 => [
                'value' => 5,
              ],
            ],
            'maximum_spaces' => [
              0 => [
                'value' => 2,
              ],
            ],
            'from_address' => [
              0 => [
                'value' => 'test@example.com',
              ],
            ],
          ];
          $this->fieldDefinitions[$entity_type_id][$bundle][$langcode]['event_registration']->setDefaultValue([
            'registration_settings' => serialize($default_settings),
          ]);
          break;

        // Spanish.
        case 'es':
          // Clear the defaults so that fallback settings are used.
          $this->fieldDefinitions[$entity_type_id][$bundle][$langcode]['event_registration']->setDefaultValue([]);
          break;
      }
    }
    return $definitions;
  }

}
