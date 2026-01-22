<?php

namespace Drupal\Tests\registration\Functional;

/**
 * Tests the registration administrative UI.
 *
 * @group registration
 */
class RegistrationAdminTest extends RegistrationBrowserTestBase {

  /**
   * Tests viewing a registration.
   */
  public function testView() {
    $user = $this->drupalCreateUser();
    $user->set('field_registration', 'conference');
    $user->save();

    $handler = $this->entityTypeManager->getHandler('user', 'registration_host_entity');
    $host_entity = $handler->createHostEntity($user);

    /** @var \Drupal\registration\RegistrationSettingsStorage $settings_storage */
    $settings_storage = $this->entityTypeManager->getStorage('registration_settings');
    $settings = $settings_storage->loadSettingsForHostEntity($host_entity);
    $settings->set('status', TRUE);
    $settings->save();

    /** @var \Drupal\registration\RegistrationStorage $registration_storage */
    $registration_storage = $this->entityTypeManager->getStorage('registration');
    $registration = $registration_storage->create([
      'workflow' => 'registration',
      'state' => 'pending',
      'type' => 'conference',
      'entity_type_id' => 'user',
      'entity_id' => $user->id(),
      'user_uid' => 1,
    ]);
    $registration->save();

    $this->drupalGet('/registration/' . $registration->id());
    $this->assertSession()->pageTextContains('Registration #' . $registration->id());
    $this->assertSession()->pageTextContains('Pending');
  }

  /**
   * Tests editing a registration.
   */
  public function testEdit() {
    $user = $this->drupalCreateUser();
    $user->set('field_registration', 'conference');
    $user->save();

    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository */
    $display_repository = \Drupal::service('entity_display.repository');
    $entity_form_display = $display_repository->getFormDisplay('registration', 'conference', 'default');
    $entity_form_display->setComponent('count', [
      'type' => 'registration_spaces_default',
      'settings' => [
        'hide_single_space' => TRUE,
      ],
    ])->save();

    $handler = $this->entityTypeManager->getHandler('user', 'registration_host_entity');
    $host_entity = $handler->createHostEntity($user);

    /** @var \Drupal\registration\RegistrationSettingsStorage $settings_storage */
    $settings_storage = $this->entityTypeManager->getStorage('registration_settings');
    $settings = $settings_storage->loadSettingsForHostEntity($host_entity);
    $settings->set('status', TRUE);
    $settings->set('capacity', 0);
    $settings->set('maximum_spaces', 2);
    $settings->save();

    /** @var \Drupal\registration\RegistrationStorage $registration_storage */
    $registration_storage = $this->entityTypeManager->getStorage('registration');
    $registration = $registration_storage->create([
      'workflow' => 'registration',
      'state' => 'pending',
      'type' => 'conference',
      'entity_type_id' => 'user',
      'entity_id' => $user->id(),
      'user_uid' => 1,
      'langcode' => 'en',
    ]);
    $registration->save();

    $admin_user = $this->drupalCreateUser([
      'access user profiles',
      'administer registration',
      'create conference registration other users',
    ]);
    $this->drupalLogin($admin_user);
    $this->drupalGet('/registration/' . $registration->id() . '/edit');
    $this->assertSession()->pageTextContains('Edit Registration #' . $registration->id());
    $this->assertSession()->fieldEnabled('count[0][value]');
    $this->assertSession()->pageTextContainsOnce('The number of spaces you wish to reserve. You may register up to 2 spaces.');
    $this->assertSession()->buttonExists('Save Registration');
    $this->getSession()->getPage()->pressButton('Save Registration');
    $this->assertSession()->pageTextContains('Registration has been saved.');

    // The Status field is hidden by default when only one state is available,
    // which is the case for the installed registration workflow.
    $entity_form_display->setComponent('state', [
      'type' => 'registration_state_default',
    ])->save();
    $this->drupalGet('/registration/' . $registration->id() . '/edit');
    $this->assertSession()->fieldNotExists('state[0]');
    $this->assertSession()->pageTextNotContains('The registration status.');

    // When the "hide_single_state" setting is disabled for the Status field on
    // the registration form display, the field is shown even when only one
    // state is available.
    $entity_form_display->setComponent('state', [
      'type' => 'registration_state_default',
      'settings' => [
        'hide_single_state' => FALSE,
      ],
    ])->save();
    $this->drupalGet('/registration/' . $registration->id() . '/edit');
    $this->assertSession()->fieldExists('state[0]');
    $this->assertSession()->pageTextContains('The registration status.');

    // Remove permission to edit the Status field.
    $admin_user = $this->drupalCreateUser([
      'access user profiles',
      'update any conference registration',
      'create conference registration other users',
    ]);
    $this->drupalLogin($admin_user);
    $this->drupalGet('/registration/' . $registration->id() . '/edit');
    $this->assertSession()->fieldNotExists('state[0]');
    $this->assertSession()->pageTextNotContains('The registration status.');

    // When only one space can be registered, the Spaces field is hidden.
    $admin_user = $this->drupalCreateUser([
      'access user profiles',
      'administer registration',
      'create conference registration other users',
    ]);
    $this->drupalLogin($admin_user);
    $settings->set('maximum_spaces', 1);
    $settings->save();
    $this->drupalGet('/registration/' . $registration->id() . '/edit');
    $this->assertSession()->pageTextContains('Edit Registration #' . $registration->id());
    $this->assertSession()->buttonExists('Save Registration');
    $this->assertSession()->fieldNotExists('count[0][value]');
    $this->assertSession()->pageTextNotContains('The number of spaces you wish to reserve.');

    // Disable hiding the Spaces field when only one space can be registered.
    $entity_form_display->setComponent('count', [
      'type' => 'registration_spaces_default',
      'settings' => [
        'hide_single_space' => FALSE,
      ],
    ])->save();
    $this->drupalGet('/registration/' . $registration->id() . '/edit');
    $this->assertSession()->fieldExists('count[0][value]');
    $this->assertSession()->pageTextContains('The number of spaces you wish to reserve.');

    // Editing a registration for another account.
    $admin_user = $this->drupalCreateUser([
      'administer registration',
    ]);
    $this->drupalLogin($admin_user);
    $this->drupalGet('/registration/' . $registration->id() . '/edit');
    $this->assertSession()->pageTextContains('Edit Registration #' . $registration->id());
    $this->assertSession()->buttonExists('Save Registration');
    $this->assertSession()->pageTextContainsOnce('Other account');

    // Editing a registration for anonymous.
    $registration->set('user_uid', NULL);
    $registration->set('anon_mail', 'someone@example.org');
    $registration->save();
    $this->drupalGet('/registration/' . $registration->id() . '/edit');
    $this->assertSession()->pageTextContains('Edit Registration #' . $registration->id());
    $this->assertSession()->buttonExists('Save Registration');
    $this->assertSession()->pageTextContainsOnce('Other person');

    // Editing own registration.
    $user = $this->drupalCreateUser([
      'update own conference registration',
    ]);
    $registration->set('user_uid', $user->id());
    $registration->set('anon_mail', NULL);
    $registration->save();
    $this->drupalLogin($user);
    $this->drupalGet('/registration/' . $registration->id() . '/edit');
    $this->assertSession()->pageTextContains('Edit Registration #' . $registration->id());
    $this->assertSession()->buttonExists('Save Registration');
    $this->assertSession()->pageTextContainsOnce('Yourself');
  }

  /**
   * Tests deleting a registration.
   */
  public function testDelete() {
    $user = $this->drupalCreateUser();
    $user->set('field_registration', 'conference');
    $user->save();

    $handler = $this->entityTypeManager->getHandler('user', 'registration_host_entity');
    $host_entity = $handler->createHostEntity($user);

    /** @var \Drupal\registration\RegistrationSettingsStorage $settings_storage */
    $settings_storage = $this->entityTypeManager->getStorage('registration_settings');
    $settings = $settings_storage->loadSettingsForHostEntity($host_entity);
    $settings->set('status', TRUE);
    $settings->save();

    /** @var \Drupal\registration\RegistrationStorage $registration_storage */
    $registration_storage = $this->entityTypeManager->getStorage('registration');
    $registration = $registration_storage->create([
      'workflow' => 'registration',
      'state' => 'pending',
      'type' => 'conference',
      'entity_type_id' => 'user',
      'entity_id' => $user->id(),
      'user_uid' => 1,
      'langcode' => 'en',
    ]);
    $registration->save();

    $this->drupalGet('/registration/' . $registration->id() . '/delete');
    $this->assertSession()->pageTextContains('Are you sure you want to delete the registration Registration #' . $registration->id());
    $this->assertSession()->pageTextContains('This action cannot be undone.');
    $this->getSession()->getPage()->pressButton('Delete');
    $this->assertSession()->pageTextContains('has been deleted.');
  }

}
