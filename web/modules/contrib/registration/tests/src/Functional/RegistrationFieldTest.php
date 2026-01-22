<?php

namespace Drupal\Tests\registration\Functional;

use Drupal\field\Entity\FieldStorageConfig;

/**
 * Tests registration fields.
 *
 * @group registration
 */
class RegistrationFieldTest extends RegistrationBrowserTestBase {

  /**
   * Modules to enable.
   *
   * Note that when a child class declares its own $modules list, that list
   * doesn't override this one, it just extends it.
   *
   * @var array
   */
  protected static $modules = [
    'field_ui',
    'node',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $registration_type = $this->entityTypeManager
      ->getStorage('registration_type')
      ->create([
        'id' => 'seminar',
        'label' => 'Seminar',
        'workflow' => 'registration',
        'defaultState' => 'pending',
        'heldExpireTime' => 1,
        'heldExpireState' => 'canceled',
      ]);
    $registration_type->save();

    $this->container
      ->get('config.factory')
      ->getEditable('registration.settings')
      ->set('limit_field_values', TRUE)
      ->save();
  }

  /**
   * Tests administrator permission for a registration field.
   */
  public function testRegistrationFieldAdminPermission() {
    $this->drupalCreateContentType(['type' => 'test_node_type']);
    $user = $this->drupalCreateUser([
      'administer node fields',
      'bypass node access',
      'administer registration',
    ]);
    $this->drupalLogin($user);
    $edit = [
      'new_storage_type' => 'registration',
    ];
    $this->drupalGet('admin/structure/types/manage/test_node_type/fields/add-field');
    $this->submitForm($edit, 'Continue');
    $edit = [
      'label' => 'Registration',
      'field_name' => 'registration',
    ];
    $this->submitForm($edit, 'Continue');
    $this->assertSession()->buttonExists('Save settings');
    $this->assertSession()->pageTextContains('This field cardinality is set to 1 and cannot be configured.');
    // Administrators have access to all types.
    $this->assertSession()->optionExists('default_value_input[field_registration][0][registration_type]', 'conference');
    $this->assertSession()->optionExists('default_value_input[field_registration][0][registration_type]', 'seminar');
  }

  /**
   * Tests "assign type" permission for a registration field.
   */
  public function testRegistrationFieldAssignTypePermission() {
    $this->drupalCreateContentType(['type' => 'test_node_type']);
    $user = $this->drupalCreateUser([
      'administer node fields',
      'bypass node access',
      'assign conference registration field',
    ]);
    $this->drupalLogin($user);
    $edit = [
      'new_storage_type' => 'registration',
    ];
    $this->drupalGet('admin/structure/types/manage/test_node_type/fields/add-field');
    $this->submitForm($edit, 'Continue');
    $edit = [
      'label' => 'Registration',
      'field_name' => 'registration',
    ];
    $this->submitForm($edit, 'Continue');
    $this->assertSession()->buttonExists('Save settings');
    $this->assertSession()->pageTextContains('This field cardinality is set to 1 and cannot be configured.');
    $this->assertSession()->optionExists('default_value_input[field_registration][0][registration_type]', 'conference');
    $this->assertSession()->optionNotExists('default_value_input[field_registration][0][registration_type]', 'seminar');
  }

  /**
   * Tests creation of a registration field.
   */
  public function testRegistrationFieldCreate() {
    // Create a test content type.
    $this->drupalCreateContentType(['type' => 'test_node_type']);

    // Login as a user who can administer node fields.
    $user = $this->drupalCreateUser([
      'administer node fields',
      'bypass node access',
      'assign conference registration field',
      'create conference registration self',
    ]);
    $this->drupalLogin($user);

    // Create a registration field on the content type.
    $edit = [
      'new_storage_type' => 'registration',
    ];
    $this->drupalGet('admin/structure/types/manage/test_node_type/fields/add-field');
    $this->submitForm($edit, 'Continue');
    $edit = [
      'label' => 'Registration',
      'field_name' => 'registration',
    ];
    $this->submitForm($edit, 'Continue');
    $this->assertSession()->buttonExists('Save settings');
    $this->assertSession()->pageTextContains('This field cardinality is set to 1 and cannot be configured.');

    // Enable registrations by default.
    $edit = [
      'settings[allowed_types][conference]' => 'conference',
      'set_default_value' => TRUE,
      'default_value_input[field_registration][0][registration_type]' => 'conference',
      'default_value_input[registration_settings][status][value]' => TRUE,
      'default_value_input[registration_settings][capacity][0][value]' => 0,
      'default_value_input[registration_settings][from_address][0][value]' => 'webmaster@example.org',
    ];
    $this->drupalGet('admin/structure/types/manage/test_node_type/add-field/node/field_registration');
    $this->submitForm($edit, 'Save settings');
    $this->assertSession()->statusMessageContains('Saved', 'status');

    // A new node should be enabled for registration.
    $edit = [
      'title[0][value]' => 'Example 1',
    ];
    $this->drupalGet('node/add/test_node_type');
    $this->submitForm($edit, 'Save');
    $this->assertSession()->statusMessageExists('status');
    $this->drupalGet('node/1/register');
    $this->assertSession()->buttonExists('Save Registration');

    // Disable registrations by default.
    $edit = [
      'settings[allowed_types][conference]' => 'conference',
      'set_default_value' => TRUE,
      'default_value_input[field_registration][0][registration_type]' => 'conference',
      'default_value_input[registration_settings][status][value]' => FALSE,
      'default_value_input[registration_settings][capacity][0][value]' => 0,
      'default_value_input[registration_settings][from_address][0][value]' => 'webmaster@example.org',
    ];
    $this->drupalGet('admin/structure/types/manage/test_node_type/fields/node.test_node_type.field_registration');
    $this->submitForm($edit, 'Save settings');
    $this->assertSession()->statusMessageContains('Saved', 'status');

    // A new node should not be enabled for registration.
    $edit = [
      'title[0][value]' => 'Example 2',
    ];
    $this->drupalGet('node/add/test_node_type');
    $this->submitForm($edit, 'Save');
    $this->assertSession()->statusMessageExists('status');
    $this->drupalGet('node/2/register');
    $this->assertSession()->statusCodeEquals(403);

    // A second registration field on the same bundle is not allowed.
    $edit = [
      'new_storage_type' => 'registration',
    ];
    $this->drupalGet('admin/structure/types/manage/test_node_type/fields/add-field');
    $this->submitForm($edit, 'Continue');
    $edit = [
      'label' => 'Registration',
      'field_name' => 'registration2',
    ];
    $this->submitForm($edit, 'Continue');
    $edit = [];
    $this->drupalGet('admin/structure/types/manage/test_node_type/add-field/node/field_registration2');
    $edit = [
      'settings[allowed_types][conference]' => 'conference',
    ];
    $this->submitForm($edit, 'Save settings');
    $this->assertSession()->statusMessageContains('An entity can only have one registration field', 'error');
  }

  /**
   * Tests deletion of a registration field.
   */
  public function testRegistrationFieldDelete() {
    // Create a test content type.
    $this->drupalCreateContentType(['type' => 'test_node_type']);

    // Login as a user who can administer node fields.
    $user = $this->drupalCreateUser([
      'administer node fields',
      'bypass node access',
      'assign conference registration field',
      'create conference registration self',
    ]);
    $this->drupalLogin($user);

    // Create a registration field on the content type.
    $edit = [
      'new_storage_type' => 'registration',
    ];
    $this->drupalGet('admin/structure/types/manage/test_node_type/fields/add-field');
    $this->submitForm($edit, 'Continue');
    $edit = [
      'label' => 'Registration',
      'field_name' => 'registration',
    ];
    $this->submitForm($edit, 'Continue');
    $this->assertSession()->buttonExists('Save settings');
    $this->assertSession()->pageTextContains('This field cardinality is set to 1 and cannot be configured.');

    $edit = [
      'settings[allowed_types][conference]' => 'conference',
      'set_default_value' => TRUE,
      'default_value_input[field_registration][0][registration_type]' => 'conference',
      'default_value_input[registration_settings][status][value]' => TRUE,
      'default_value_input[registration_settings][capacity][0][value]' => 0,
      'default_value_input[registration_settings][from_address][0][value]' => 'webmaster@example.org',
    ];
    $this->drupalGet('admin/structure/types/manage/test_node_type/add-field/node/field_registration');
    $this->submitForm($edit, 'Save settings');
    $this->assertSession()->statusMessageContains('Saved', 'status');

    // Create a new node that is enabled for registration.
    $edit = [
      'title[0][value]' => 'Example 1',
    ];
    $this->drupalGet('node/add/test_node_type');
    $this->submitForm($edit, 'Save');
    $this->assertSession()->statusMessageExists('status');
    $this->drupalGet('node/1/register');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->buttonExists('Save Registration');

    // Delete the registration field.
    FieldStorageConfig::loadByName('node', 'field_registration')->delete();
    $this->drupalGet('node/1');
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet('node/1/register');
    $this->assertSession()->statusCodeEquals(404);
  }

}
