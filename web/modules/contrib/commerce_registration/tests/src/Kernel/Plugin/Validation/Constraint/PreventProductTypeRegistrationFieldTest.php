<?php

namespace Drupal\Tests\commerce_registration\Kernel\Plugin\Validation\Constraint;

use Drupal\Tests\commerce_registration\Kernel\CommerceRegistrationKernelTestBase;

/**
 * Tests the 'prevent product type registration field' constraint.
 *
 * @coversDefaultClass \Drupal\commerce_registration\Plugin\Validation\Constraint\PreventProductTypeRegistrationFieldValidator
 *
 * @group commerce_registration
 */
class PreventProductTypeRegistrationFieldTest extends CommerceRegistrationKernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'field',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('field_storage_config');
    $this->installEntitySchema('field_config');
  }

  /**
   * @covers ::validate
   */
  public function testPreventProductTypeRegistrationField() {
    $field_storage_values = [
      'field_name' => 'field_registration',
      'entity_type' => 'commerce_product',
      'type' => 'registration',
      'translatable' => TRUE,
    ];
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('A registration field cannot be added to a product type. Add to a product variation type instead.');
    $this->entityTypeManager
      ->getStorage('field_storage_config')
      ->create($field_storage_values)
      ->save();
  }

}
