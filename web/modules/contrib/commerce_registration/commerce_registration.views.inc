<?php

/**
 * @file
 * Views integration for Commerce Registration.
 */

/**
 * Implements hook_views_data().
 */
function commerce_registration_views_data() {
  // Make views aware of custom area plugins.
  $data['views']['manage_commerce_registrations_caption'] = [
    'title' => t('Manage commerce registrations caption'),
    'help' => t('Displays a caption for the product Manage Registrations listing.'),
    'area' => [
      'id' => 'manage_commerce_registrations_caption',
    ],
  ];
  $data['views']['manage_commerce_registrations_empty'] = [
    'title' => t('Manage commerce registrations empty'),
    'help' => t('Displays an empty notice for the product Manage Registrations listing.'),
    'area' => [
      'id' => 'manage_commerce_registrations_empty',
    ],
  ];
  return $data;
}

/**
 * Implements hook_views_data_alter().
 */
function commerce_registration_views_data_alter(array &$data) {
  $data['commerce_product_field_data']['commerce_registration_product_id'] = [
    'title' => t('ID selection'),
    'help' => t('The product as chosen by a select or autocomplete field.'),
    'filter' => [
      'id' => 'commerce_registration_product_id',
      'field_name' => 'product_id',
    ],
    'entity field' => 'product_id',
  ];
  $data['registration_settings_field_data']['product_registration_settings_operations'] = [
    'title' => t('Product settings operations'),
    'help' => t('Provides links to perform settings operations for a product.'),
    'field' => [
      'id' => 'product_registration_settings_operations',
    ],
  ];
  $data['registration']['commerce_registration_product_variation'] = [
    'title' => t('Product variation host entity'),
    'help' => t('The registration enabled product variation host entity for the registration.'),
    'filter' => [
      'id' => 'commerce_registration_product_variation',
    ],
  ];
}
