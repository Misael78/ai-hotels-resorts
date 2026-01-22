<?php

namespace Drupal\Tests\registration\Kernel\Plugin\Validation\Constraint;

use Drupal\Tests\registration\Kernel\RegistrationKernelTestBase;

/**
 * Tests the Registration Field constraint.
 *
 * @coversDefaultClass \Drupal\registration\Plugin\Validation\Constraint\RegistrationFieldConstraint
 *
 * @group registration
 */
class RegistrationFieldConstraintTest extends RegistrationKernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'field',
    'taxonomy',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('field_storage_config');
    $this->installEntitySchema('field_config');

    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('taxonomy_vocabulary');
  }

  /**
   * @covers ::validate
   */
  public function testRegistrationFieldConstraint1() {
    // Fail validation - exception thrown.
    $field_storage_values = [
      'field_name' => 'field_registration',
      'entity_type' => 'registration',
      'type' => 'registration',
      'translatable' => TRUE,
    ];
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('A registration field cannot be added to a registration type');
    $this->entityTypeManager
      ->getStorage('field_storage_config')
      ->create($field_storage_values)
      ->save();
  }

  /**
   * @covers ::validate
   */
  public function testRegistrationFieldConstraint2() {
    // Fail validation - exception thrown.
    $field_storage_values = [
      'field_name' => 'field_registration',
      'entity_type' => 'registration_settings',
      'type' => 'registration',
      'translatable' => TRUE,
    ];
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('A registration field cannot be added to registration settings');
    $this->entityTypeManager
      ->getStorage('field_storage_config')
      ->create($field_storage_values)
      ->save();
  }

  /**
   * @covers ::validate
   */
  public function testRegistrationFieldConstraint3() {
    // Pass validation - no exception.
    $field_storage_values = [
      'field_name' => 'field_registration',
      'entity_type' => 'node',
      'type' => 'registration',
      'translatable' => TRUE,
    ];
    $this->entityTypeManager
      ->getStorage('field_storage_config')
      ->create($field_storage_values)
      ->save();
    // Fail validation - exception thrown.
    $field_values = [
      'field_name' => 'field_registration',
      'entity_type' => 'node',
      'bundle' => 'event',
      'label' => 'Registration',
      'translatable' => FALSE,
    ];
    // The node entity already has a base registration field added by the
    // registration_test module.
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('An entity can only have one registration field');
    $this->entityTypeManager
      ->getStorage('field_config')
      ->create($field_values)
      ->save();
  }

  /**
   * @covers ::validate
   */
  public function testRegistrationFieldConstraint4() {
    // Pass validation - no exception.
    $field_storage_values = [
      'field_name' => 'field_registration',
      'entity_type' => 'user',
      'type' => 'registration',
      'translatable' => TRUE,
    ];
    $this->entityTypeManager
      ->getStorage('field_storage_config')
      ->create($field_storage_values)
      ->save();
    // Pass validation - no exception.
    $field_values = [
      'field_name' => 'field_registration',
      'entity_type' => 'user',
      'bundle' => 'user',
      'label' => 'Registration',
      'translatable' => FALSE,
    ];
    $this->entityTypeManager
      ->getStorage('field_config')
      ->create($field_values)
      ->save();
  }

  /**
   * @covers ::validate
   */
  public function testRegistrationFieldConstraint5() {
    // Setup taxonomy vocabularies.
    $this->entityTypeManager
      ->getStorage('taxonomy_vocabulary')
      ->create([
        'vid' => 'fruit',
        'name' => 'Fruit',
      ])
      ->save();
    $this->entityTypeManager
      ->getStorage('taxonomy_vocabulary')
      ->create([
        'vid' => 'sports',
        'name' => 'Sports',
      ])
      ->save();

    // Pass validation - no exception.
    $field_storage_values = [
      'field_name' => 'field_registration',
      'entity_type' => 'taxonomy_term',
      'type' => 'registration',
      'translatable' => TRUE,
    ];
    $this->entityTypeManager
      ->getStorage('field_storage_config')
      ->create($field_storage_values)
      ->save();
    // Pass validation - no exception.
    $field_values = [
      'field_name' => 'field_registration',
      'entity_type' => 'taxonomy_term',
      'bundle' => 'fruit',
      'label' => 'Registration',
      'translatable' => FALSE,
    ];
    $this->entityTypeManager
      ->getStorage('field_config')
      ->create($field_values)
      ->save();
    // Pass validation - no exception (different bundle).
    $field_values = [
      'field_name' => 'field_registration',
      'entity_type' => 'taxonomy_term',
      'bundle' => 'sports',
      'label' => 'Registration',
      'translatable' => FALSE,
    ];
    $this->entityTypeManager
      ->getStorage('field_config')
      ->create($field_values)
      ->save();
    // Pass validation - no exception.
    $field_storage_values = [
      'field_name' => 'field_registration_2',
      'entity_type' => 'taxonomy_term',
      'type' => 'registration',
      'translatable' => TRUE,
    ];
    $this->entityTypeManager
      ->getStorage('field_storage_config')
      ->create($field_storage_values)
      ->save();
    // Fail validation - exception thrown.
    $field_values = [
      'field_name' => 'field_registration_2',
      'entity_type' => 'taxonomy_term',
      'bundle' => 'fruit',
      'label' => 'Registration',
      'translatable' => FALSE,
    ];
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('An entity can only have one registration field');
    $this->entityTypeManager
      ->getStorage('field_config')
      ->create($field_values)
      ->save();
  }

}
