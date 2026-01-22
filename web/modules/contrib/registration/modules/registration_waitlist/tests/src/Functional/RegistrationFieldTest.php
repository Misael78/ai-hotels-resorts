<?php

namespace Drupal\Tests\registration_waitlist\Functional;

use Drupal\Tests\registration\Functional\RegistrationBrowserTestBase;

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
    'registration_waitlist',
  ];

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
      'create conference registration self',
    ]);
    $this->drupalLogin($user);

    // Create a new registration field on the content type.
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

    // Enable registrations by default, with a wait list.
    $edit = [
      'set_default_value' => TRUE,
      'default_value_input[field_registration][0][registration_type]' => 'conference',
      'default_value_input[registration_settings][status][value]' => TRUE,
      'default_value_input[registration_settings][capacity][0][value]' => 1,
      'default_value_input[registration_settings][multiple_registrations][value]' => TRUE,
      'default_value_input[registration_settings][from_address][0][value]' => 'webmaster@example.org',
      'default_value_input[registration_settings][registration_waitlist_enable][value]' => TRUE,
      'default_value_input[registration_settings][registration_waitlist_capacity][0][value]' => 0,
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

    // New registration that fills capacity.
    $edit = [];
    $this->drupalGet('node/1/register');
    $this->submitForm($edit, 'Save Registration');
    $this->assertSession()->statusMessageExists('status');

    // New registration added to the wait list.
    $this->drupalGet('node/1/register');
    $this->submitForm($edit, 'Save Registration');
    $this->assertSession()->statusMessageExists('warning');
  }

}
