<?php

/**
 * @file
 * Views integration for Registration Wait List.
 */

/**
 * Implements hook_views_data_alter().
 */
function registration_waitlist_views_data_alter(array &$data) {
  /** @var \Drupal\registration\RegistrationManagerInterface $registration_manager */
  $registration_manager = \Drupal::service('registration.manager');

  // Add custom field plugins for host entity data.
  foreach ($registration_manager->getRegistrationEnabledEntityTypes() as $entity_type) {
    $table = $entity_type->getDataTable() ?: $entity_type->getBaseTable();
    $data[$table]['waitlist_spaces_remaining'] = [
      'title' => t('Wait list spaces remaining'),
      'help' => t('The number of spaces remaining on the wait list.'),
      'field' => [
        'id' => 'host_entity_waitlist_spaces_remaining',
      ],
    ];
    $data[$table]['waitlist_spaces_reserved'] = [
      'title' => t('Wait listed spaces reserved'),
      'help' => t('The number of spaces reserved by wait listed registrations.'),
      'field' => [
        'id' => 'host_entity_waitlist_spaces_reserved',
      ],
    ];
  }
}
