<?php

namespace Drupal\Tests\registration_admin_overrides\Functional;

/**
 * Tests the registration type UI.
 *
 * @group registration
 */
class RegistrationTypeTest extends RegistrationAdminOverridesBrowserTestBase {

  /**
   * Tests adding a registration type.
   */
  public function testAdd() {
    $this->drupalGet('admin/structure/registration-types/add');

    $edit = [
      'id' => 'seminar',
      'label' => 'Seminar',
      'workflow' => 'registration',
      'workflow_data[default_state]' => 'pending',
      'workflow_data[held][held_expire]' => 6,
      'workflow_data[held][held_expire_state]' => 'canceled',
      'registration_admin_overrides[status]' => FALSE,
      'registration_admin_overrides[maximum_spaces]' => FALSE,
      'registration_admin_overrides[capacity]' => FALSE,
      'registration_admin_overrides[open]' => FALSE,
      'registration_admin_overrides[close]' => FALSE,
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextContains('The registration type Seminar has been successfully saved.');

    $reg_type = $this->entityTypeManager
      ->getStorage('registration_type')
      ->load($edit['id']);
    $this->assertNotEmpty($reg_type);
    $this->assertEquals($edit['label'], $reg_type->label());
    $this->assertEquals($edit['workflow'], $reg_type->getWorkflowId());
    $this->assertEquals($edit['workflow_data[default_state]'], $reg_type->getDefaultState());
    $this->assertEquals($edit['workflow_data[held][held_expire]'], $reg_type->getHeldExpirationTime());
    $this->assertEquals($edit['workflow_data[held][held_expire_state]'], $reg_type->getHeldExpirationState());
    $this->assertEquals($edit['registration_admin_overrides[status]'], $reg_type->getThirdPartySetting('registration_admin_overrides', 'status'));
    $this->assertEquals($edit['registration_admin_overrides[maximum_spaces]'], $reg_type->getThirdPartySetting('registration_admin_overrides', 'maximum_spaces'));
    $this->assertEquals($edit['registration_admin_overrides[capacity]'], $reg_type->getThirdPartySetting('registration_admin_overrides', 'capacity'));
    $this->assertEquals($edit['registration_admin_overrides[open]'], $reg_type->getThirdPartySetting('registration_admin_overrides', 'open'));
    $this->assertEquals($edit['registration_admin_overrides[close]'], $reg_type->getThirdPartySetting('registration_admin_overrides', 'close'));
  }

  /**
   * Tests editing a registration type.
   */
  public function testEdit() {
    $this->drupalGet('admin/structure/registration-types/conference/edit');
    $edit = [
      'label' => 'Conference!',
      'workflow_data[held][held_expire]' => 1,
      'workflow_data[held][held_expire_state]' => 'pending',
      'registration_admin_overrides[capacity]' => FALSE,
      'registration_admin_overrides[open]' => FALSE,
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextContains('The registration type Conference! has been successfully saved.');

    $reg_type = $this->entityTypeManager
      ->getStorage('registration_type')
      ->load('conference');
    $this->assertNotEmpty($reg_type);
    $this->assertEquals($edit['label'], $reg_type->label());
    $this->assertEquals($edit['workflow_data[held][held_expire]'], $reg_type->getHeldExpirationTime());
    $this->assertEquals($edit['workflow_data[held][held_expire_state]'], $reg_type->getHeldExpirationState());
    $this->assertEquals($edit['registration_admin_overrides[capacity]'], $reg_type->getThirdPartySetting('registration_admin_overrides', 'capacity'));
    $this->assertEquals($edit['registration_admin_overrides[open]'], $reg_type->getThirdPartySetting('registration_admin_overrides', 'open'));
    $this->assertTrue($reg_type->getThirdPartySetting('registration_admin_overrides', 'status'));
    $this->assertTrue($reg_type->getThirdPartySetting('registration_admin_overrides', 'maximum_spaces'));
    $this->assertFalse($reg_type->getThirdPartySetting('registration_admin_overrides', 'capacity'));
    $this->assertFalse($reg_type->getThirdPartySetting('registration_admin_overrides', 'open'));
    $this->assertTrue($reg_type->getThirdPartySetting('registration_admin_overrides', 'close'));
  }

}
