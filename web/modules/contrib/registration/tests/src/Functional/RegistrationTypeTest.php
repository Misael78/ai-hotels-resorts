<?php

namespace Drupal\Tests\registration\Functional;

/**
 * Tests the registration type UI.
 *
 * @group registration
 */
class RegistrationTypeTest extends RegistrationBrowserTestBase {

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
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextContains('The registration type Seminar has been successfully saved.');

    $registration_type = $this->entityTypeManager
      ->getStorage('registration_type')
      ->load($edit['id']);
    $this->assertNotEmpty($registration_type);
    $this->assertEquals($edit['label'], $registration_type->label());
    $this->assertEquals($edit['workflow'], $registration_type->getWorkflowId());
    $this->assertEquals($edit['workflow_data[default_state]'], $registration_type->getDefaultState());
    $this->assertEquals($edit['workflow_data[held][held_expire]'], $registration_type->getHeldExpirationTime());
    $this->assertEquals($edit['workflow_data[held][held_expire_state]'], $registration_type->getHeldExpirationState());
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
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextContains('The registration type Conference! has been successfully saved.');

    $registration_type = $this->entityTypeManager
      ->getStorage('registration_type')
      ->load('conference');
    $this->assertNotEmpty($registration_type);
    $this->assertEquals($edit['label'], $registration_type->label());
    $this->assertEquals($edit['workflow_data[held][held_expire]'], $registration_type->getHeldExpirationTime());
    $this->assertEquals($edit['workflow_data[held][held_expire_state]'], $registration_type->getHeldExpirationState());
  }

  /**
   * Tests deleting a registration type.
   */
  public function testDelete() {
    $user = $this->drupalCreateUser();
    $user->set('field_registration', 'conference');
    $user->save();

    $registration_type = $this->entityTypeManager
      ->getStorage('registration_type')
      ->load('conference');
    $this->assertNotEmpty($registration_type);

    $registration = $this->entityTypeManager
      ->getStorage('registration')
      ->create([
        'workflow' => 'registration',
        'state' => 'pending',
        'type' => $registration_type->id(),
        'entity_type_id' => 'user',
        'entity_id' => $user->id(),
      ]);
    $this->assertNotEmpty($registration);
    $registration->save();

    // Confirm that the type can't be deleted while there's a registration of
    // the type.
    $this->drupalGet($registration_type->toUrl('delete-form'));
    $this->assertSession()->pageTextContains($this->t('@type is used by 1 registration on your site. You cannot delete this registration type until you have deleted that registration.', ['@type' => $registration_type->label()]));
    $this->assertSession()->pageTextNotContains($this->t('This action cannot be undone.'));

    // Confirm that the type can't be deleted while there's a registration
    // field using the type.
    $registration->delete();
    $this->drupalGet($registration_type->toUrl('delete-form'));
    $this->assertSession()->pageTextContains($this->t('@type is used by 1 @entity_type entity on your site. You cannot delete this registration type until you have edited that entity to remove the reference to this registration type.', [
      '@type' => $registration_type->label(),
      '@entity_type' => 'User',
    ]));
    $this->assertSession()->pageTextNotContains($this->t('This action cannot be undone.'));

    // Remove the last remaining reference.
    $user->set('field_registration', NULL);
    $user->save();

    // Confirm that deletion works, now that all references are gone.
    $this->drupalGet($registration_type->toUrl('delete-form'));
    $this->assertSession()->pageTextContains($this->t('Are you sure you want to delete the registration type @type?', ['@type' => $registration_type->label()]));
    $this->assertSession()->pageTextContains($this->t('This action cannot be undone.'));
    $this->submitForm([], 'Delete');
    $registration_type = $this->entityTypeManager
      ->getStorage('registration_type')
      ->load($registration_type->id());
    $exists = (bool) $registration_type;
    $this->assertEmpty($exists);
  }

}
