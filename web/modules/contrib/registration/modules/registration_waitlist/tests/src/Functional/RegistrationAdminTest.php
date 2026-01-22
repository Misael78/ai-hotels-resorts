<?php

namespace Drupal\Tests\registration_waitlist\Functional;

/**
 * Tests the registration administrative UI.
 *
 * @group registration
 */
class RegistrationAdminTest extends RegistrationWaitListBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    \Drupal::service('entity_display.repository')
      ->getFormDisplay('registration', 'conference', 'default')
      ->setComponent('state', [
        'type' => 'registration_state_default',
        'region' => 'content',
      ])
      ->setComponent('count', [
        'type' => 'registration_spaces_default',
        'settings' => [
          'hide_single_space' => TRUE,
        ],
        'region' => 'content',
      ])
      ->save();
  }

  /**
   * Tests editing a registration.
   */
  public function testEdit() {
    $user = $this->drupalCreateUser();
    $user->set('field_registration', 'conference');
    $user->save();

    $account = $this->drupalCreateUser([
      'update any conference registration',
      'edit conference registration state',
    ]);
    $this->drupalLogin($account);

    $handler = $this->entityTypeManager->getHandler('user', 'registration_host_entity');
    $host_entity = $handler->createHostEntity($user);

    /** @var \Drupal\registration\RegistrationSettingsStorage $settings_storage */
    $settings_storage = $this->entityTypeManager->getStorage('registration_settings');
    $settings = $settings_storage->loadSettingsForHostEntity($host_entity);
    $settings->set('status', TRUE);
    $settings->set('capacity', 2);
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
      'user_uid' => $account->id(),
      'langcode' => 'en',
    ]);
    $registration->save();
    $this->assertFalse($registration->isComplete());

    $workflow = $this->entityTypeManager->getStorage('workflow')->load('registration');
    $configuration = $workflow->getTypePlugin()->getConfiguration();
    $state = $workflow->getTypePlugin()->getState('complete');
    $configuration['states'][$state->id()]['show_on_form'] = TRUE;
    $state = $workflow->getTypePlugin()->getState('canceled');
    $configuration['states'][$state->id()]['show_on_form'] = TRUE;
    $workflow->getTypePlugin()->setConfiguration($configuration);
    $workflow->save();

    // Complete a registration using the edit form.
    $this->drupalGet('/registration/' . $registration->id() . '/edit');
    $this->assertSession()->pageTextContains('Edit Registration #' . $registration->id());
    $edit = [
      'count[0][value]' => 2,
      'state[0]' => 'complete',
    ];
    $this->submitForm($edit, 'Save Registration');
    $this->assertSession()->statusMessageExists('status', 'Registration has been saved.');
    $registration_storage->resetCache([$registration->id()]);
    $registration = $registration_storage->load($registration->id());
    $this->assertTrue($registration->isComplete());
    $this->drupalLogout();

    // Registration is blocked because the host entity is at capacity.
    $account = $this->drupalCreateUser([
      'create conference registration self',
      'update any conference registration',
      'edit conference registration state',
    ]);
    $this->drupalLogin($account);
    $this->drupalGet('user/' . $user->id() . '/register');
    $this->assertSession()->buttonNotExists('Save Registration');
    $this->assertSession()->pageTextContains('insufficient spaces remaining');

    // Set up the wait list and retry.
    $settings->set('registration_waitlist_enable', TRUE);
    $settings->set('registration_waitlist_message_enable', TRUE);
    $settings->save();
    $this->drupalGet('user/' . $user->id() . '/register');
    $this->assertSession()->buttonExists('Save Registration');
    $this->assertSession()->pageTextContains('The number of spaces you wish to reserve on the wait list. You may register up to 2 spaces.');
    $this->assertSession()->pageTextNotContains('insufficient spaces remaining');
    $this->submitForm([], 'Save Registration');
    $this->assertSession()->statusMessageExists('warning', 'Registration placed on the wait list.');
    $this->assertSession()->statusMessageNotExists('status', 'Registration has been saved.');

    // Attempt to complete the new registration but should not succeed.
    $registration = $registration_storage->load(2);
    $this->assertFalse($registration->isComplete());
    $this->drupalGet('/registration/' . $registration->id() . '/edit');
    $this->assertSession()->pageTextContains('Edit Registration #' . $registration->id());
    $this->assertSession()->pageTextContains('The number of spaces you wish to reserve.');
    $edit = [
      'state[0]' => 'complete',
    ];
    $this->submitForm($edit, 'Save Registration');
    $this->assertSession()->statusMessageExists('warning', 'Registration placed on the wait list.');
    $this->assertSession()->statusMessageNotExists('status', 'Registration has been saved.');
    $registration_storage->resetCache([$registration->id()]);
    $registration = $registration_storage->load($registration->id());
    $this->assertFalse($registration->isComplete());
    $this->drupalLogout();

    // Install administrative overrides and retry the edit and should succeed.
    $this->container->get('module_installer')->install(['registration_admin_overrides']);
    $registration_type = $this->entityTypeManager->getStorage('registration_type')->load('conference');
    $registration_type->setThirdPartySetting('registration_admin_overrides', 'capacity', TRUE);
    $registration_type->save();
    $account = $this->drupalCreateUser([
      'administer registration',
      'registration override capacity',
      'edit conference registration state',
    ]);
    $this->drupalLogin($account);
    $this->drupalGet('/registration/' . $registration->id() . '/edit');
    $this->assertSession()->pageTextContains('Edit Registration #' . $registration->id());
    $edit = [
      'state[0]' => 'complete',
    ];
    $this->submitForm($edit, 'Save Registration');
    $this->assertSession()->statusMessageExists('status', 'Registration has been saved.');
    $this->assertSession()->statusMessageNotExists('warning', 'Registration placed on the wait list.');
    $registration_storage->resetCache([$registration->id()]);
    $registration = $registration_storage->load($registration->id());
    $this->assertTrue($registration->isComplete());
    $this->drupalLogout();
  }

}
