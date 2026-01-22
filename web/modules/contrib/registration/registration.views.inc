<?php

/**
 * @file
 * Views integration for Entity Registration.
 */

use Drupal\Core\Entity\FieldableEntityInterface;

/**
 * Implements hook_views_data().
 */
function registration_views_data() {
  // Make views aware of custom area plugins.
  $data['views']['manage_registrations_caption'] = [
    'title' => t('Manage registrations caption'),
    'help' => t('Displays a caption for the Manage Registrations listing.'),
    'area' => [
      'id' => 'manage_registrations_caption',
    ],
  ];
  $data['views']['manage_registrations_empty'] = [
    'title' => t('Manage registrations empty'),
    'help' => t('Displays an empty notice for the Manage Registrations listing.'),
    'area' => [
      'id' => 'manage_registrations_empty',
    ],
  ];
  return $data;
}

/**
 * Implements hook_views_data_alter().
 */
function registration_views_data_alter(array &$data) {
  // Most registration fields are handled automatically by setting a
  // views_data class in the entity annotation. Derive additional data
  // for relationships. These are added one per fieldable entity type,
  // since there is a different base table for each.
  foreach (\Drupal::entityTypeManager()->getDefinitions() as $entity_type) {
    // Skip registration entity types to avoid circular references.
    $registration_entity_types = [
      'registration',
      'registration_settings',
    ];
    if (in_array($entity_type->id(), $registration_entity_types)) {
      continue;
    }

    // Ensure the entity type is fieldable.
    if ($entity_type->entityClassImplements(FieldableEntityInterface::class)) {
      $id = $entity_type->id();
      $base_table = $entity_type->getBaseTable();

      // Add a relationship from a host entity type to registrations.
      $data[$base_table][$base_table . '_registration'] = [
        'title' => t('@type registration', [
          '@type' => $entity_type->getLabel(),
        ]),
        'help' => t('Relate @type to their registrations.', [
          '@type' => $entity_type->getPluralLabel(),
        ]),
        'relationship' => [
          'id' => "registration",
          'label' => t('@id registration', [
            '@id' => $id,
          ]),
          'base' => 'registration',
        ],
      ];
      // Add a relationship from a host entity type to registration settings.
      $data[$base_table][$base_table . '_registration_settings'] = [
        'title' => t('@type registration settings', [
          '@type' => $entity_type->getLabel(),
        ]),
        'help' => t('Relate @type to their registration settings.', [
          '@type' => $entity_type->getPluralLabel(),
        ]),
        'relationship' => [
          'id' => "registration_settings",
          'label' => t('@id registration settings', [
            '@id' => $id,
          ]),
          'base' => 'registration_settings_field_data',
        ],
      ];
    }
  }

  /** @var \Drupal\registration\RegistrationManagerInterface $registration_manager */
  $registration_manager = \Drupal::service('registration.manager');
  // Add a relationship from the registration to each enabled host entity type.
  foreach ($registration_manager->getRegistrationEnabledEntityTypes() as $entity_type) {
    $args = ['@type' => $entity_type->getLabel()];
    $data['registration']['host_entity_' . $entity_type->id()] = [
      'relationship' => [
        'title' => t('Host entity: @type', $args),
        'label' => t('Host entity: @type', $args),
        'group' => t('Registration'),
        'help' => t('The @type entity that is associated through the host entity of the registration.', $args),
        'id' => 'standard',
        'base' => $entity_type->getDataTable() ?: $entity_type->getBaseTable(),
        'entity type' => $entity_type->id(),
        'base field' => $entity_type->getKey('id'),
        'relationship field' => 'entity_id',
        'extra' => [
          ['left_field' => 'entity_type_id', 'value' => $entity_type->id()],
        ],
      ],
    ];
  }

  // Add custom field plugins for host entity data.
  foreach ($registration_manager->getRegistrationEnabledEntityTypes() as $entity_type) {
    $table = $entity_type->getDataTable() ?: $entity_type->getBaseTable();
    $data[$table]['registration_count'] = [
      'title' => t('Registration count'),
      'help' => t('The total number of registrations.'),
      'field' => [
        'id' => 'host_entity_registration_count',
      ],
      'filter' => [
        'id' => 'host_entity_registration_count',
      ],
    ];
    $data[$table]['spaces_remaining'] = [
      'title' => t('Spaces remaining'),
      'help' => t('The number of spaces remaining for registration.'),
      'field' => [
        'id' => 'host_entity_spaces_remaining',
      ],
      'filter' => [
        'id' => 'host_entity_spaces_remaining',
      ],
    ];
    $data[$table]['spaces_reserved'] = [
      'title' => t('Spaces reserved'),
      'help' => t('The number of spaces reserved by registrations.'),
      'field' => [
        'id' => 'host_entity_spaces_reserved',
      ],
      'filter' => [
        'id' => 'host_entity_spaces_reserved',
      ],
    ];
    $data[$table]['user_is_registered'] = [
      'title' => t('User is registered'),
      'help' => t('Whether the user is registered or not.'),
      'field' => [
        'id' => 'host_entity_user_is_registered',
      ],
      'filter' => [
        'id' => 'host_entity_user_is_registered',
      ],
    ];
  }

  // Make views aware of additional custom field plugins.
  $data['registration']['host_entity'] = [
    'title' => t('Host entity'),
    'group' => t('Registration'),
    'field' => [
      'title' => t('Host entity'),
      'help' => t('Displays the host entity for a registration.'),
      'id' => 'registration_host_entity',
    ],
  ];
  $data['registration_settings_field_data']['settings_operations'] = [
    'title' => t('Settings operations'),
    'help' => t('Provides links to perform settings operations.'),
    'field' => [
      'id' => 'registration_settings_operations',
    ],
  ];
}
