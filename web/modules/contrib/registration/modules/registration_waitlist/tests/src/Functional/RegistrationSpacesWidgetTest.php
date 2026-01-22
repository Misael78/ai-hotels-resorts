<?php

namespace Drupal\Tests\registration_waitlist\Functional;

/**
 * Tests the registration spaces widget.
 *
 * @group registration
 */
class RegistrationSpacesWidgetTest extends RegistrationWaitListBrowserTestBase {

  /**
   * Tests the registration spaces widget in various scenarios.
   */
  public function testRegistrationSpacesWidget() {
    $this->drupalLogout();

    $this->adminUser->set('field_registration', 'conference');
    $this->adminUser->save();

    $handler = $this->entityTypeManager->getHandler('user', 'registration_host_entity');
    $host_entity = $handler->createHostEntity($this->adminUser);

    /** @var \Drupal\registration\RegistrationSettingsStorage $storage */
    $storage = $this->entityTypeManager->getStorage('registration_settings');
    $settings = $storage->loadSettingsForHostEntity($host_entity);
    $settings->set('status', TRUE);
    $settings->save();

    // Hide the spaces field unless the user can register for more than one
    // space.
    \Drupal::service('entity_display.repository')
      ->getFormDisplay('registration', 'conference', 'default')
      ->setComponent('count', [
        'type' => 'registration_spaces_default',
        'settings' => [
          'hide_single_space' => TRUE,
        ],
        'region' => 'content',
      ])
      ->save();
    $user = $this->drupalCreateUser([
      'access user profiles',
      'create conference registration self',
    ]);
    $this->drupalLogin($user);
    $this->drupalGet('user/' . $this->adminUser->id() . '/register');
    $this->assertSession()->buttonExists('Save Registration');
    $this->assertSession()->fieldNotExists('count[0][value]');
    $this->assertSession()->pageTextNotContains('The number of spaces you wish to reserve. You may register 1 space.');

    // Show the spaces field in all cases.
    \Drupal::service('entity_display.repository')
      ->getFormDisplay('registration', 'conference', 'default')
      ->setComponent('count', [
        'type' => 'registration_spaces_default',
        'settings' => [
          'hide_single_space' => FALSE,
        ],
        'region' => 'content',
      ])
      ->save();

    // Test scenarios when registering and the wait list is not active yet.
    $settings->set('capacity', 0);
    $settings->set('maximum_spaces', 1);
    $settings->save();
    $this->drupalGet('user/' . $this->adminUser->id() . '/register');
    $this->assertSession()->buttonExists('Save Registration');
    $this->assertSession()->fieldEnabled('count[0][value]');
    $this->assertSession()->pageTextContainsOnce('The number of spaces you wish to reserve. You may register 1 space.');
    $settings->set('maximum_spaces', 5);
    $settings->save();
    $this->drupalGet('user/' . $this->adminUser->id() . '/register');
    $this->assertSession()->buttonExists('Save Registration');
    $this->assertSession()->fieldEnabled('count[0][value]');
    $this->assertSession()->pageTextContainsOnce('The number of spaces you wish to reserve. You may register up to 5 spaces.');
    $settings->set('capacity', 1);
    $settings->save();
    $this->drupalGet('user/' . $this->adminUser->id() . '/register');
    $this->assertSession()->buttonExists('Save Registration');
    $this->assertSession()->fieldEnabled('count[0][value]');
    $this->assertSession()->pageTextContainsOnce('The number of spaces you wish to reserve. There is 1 space remaining.');
    $settings->set('capacity', 3);
    $settings->save();
    $this->drupalGet('user/' . $this->adminUser->id() . '/register');
    $this->assertSession()->buttonExists('Save Registration');
    $this->assertSession()->fieldEnabled('count[0][value]');
    $this->assertSession()->pageTextContainsOnce('The number of spaces you wish to reserve. There are 3 spaces remaining.');
    $settings->set('maximum_spaces', 1);
    $settings->save();
    $this->drupalGet('user/' . $this->adminUser->id() . '/register');
    $this->assertSession()->buttonExists('Save Registration');
    $this->assertSession()->fieldEnabled('count[0][value]');
    $this->assertSession()->pageTextContainsOnce('The number of spaces you wish to reserve. There are 3 spaces remaining. You may register 1 space.');
    $settings->set('capacity', 7);
    $settings->set('maximum_spaces', 5);
    $settings->save();
    $this->drupalGet('user/' . $this->adminUser->id() . '/register');
    $this->assertSession()->buttonExists('Save Registration');
    $this->assertSession()->fieldEnabled('count[0][value]');
    $this->assertSession()->pageTextContainsOnce('The number of spaces you wish to reserve. There are 7 spaces remaining. You may register up to 5 spaces.');

    // Register for one space.
    $this->submitForm([], 'Save Registration');
    $this->assertSession()->statusMessageExists('status', 'Registration has been saved.');
    $this->drupalLogout();

    // No more room.
    $settings->set('capacity', 1);
    $settings->save();
    $user = $this->drupalCreateUser([
      'access user profiles',
      'create conference registration self',
    ]);
    $this->drupalLogin($user);
    $this->drupalGet('user/' . $this->adminUser->id() . '/register');
    $this->assertSession()->buttonNotExists('Save Registration');
    $this->assertSession()->pageTextContains('insufficient spaces remaining');

    // Set up the wait list.
    $settings->set('registration_waitlist_enable', TRUE);
    $settings->set('registration_waitlist_message_enable', TRUE);
    $settings->set('registration_waitlist_capacity', 0);
    $settings->set('maximum_spaces', 1);
    $settings->save();

    // Test scenarios when registering and the wait list is active.
    $this->drupalGet('user/' . $this->adminUser->id() . '/register');
    $this->assertSession()->buttonExists('Save Registration');
    $this->assertSession()->pageTextContains('completing this registration form will place you on a waitlist');
    $this->assertSession()->pageTextNotContains('insufficient spaces remaining');
    $this->assertSession()->pageTextContainsOnce('The number of spaces you wish to reserve on the wait list. You may register 1 space.');
    $settings->set('maximum_spaces', 5);
    $settings->save();
    $this->drupalGet('user/' . $this->adminUser->id() . '/register');
    $this->assertSession()->buttonExists('Save Registration');
    $this->assertSession()->pageTextContainsOnce('The number of spaces you wish to reserve on the wait list. You may register up to 5 spaces.');
    $settings->set('capacity', 2);
    $settings->save();
    $this->drupalGet('user/' . $this->adminUser->id() . '/register');
    $this->assertSession()->buttonExists('Save Registration');
    $this->assertSession()->pageTextContainsOnce('The number of spaces you wish to reserve. There is 1 space remaining. You may register up to 5 spaces, although registering for more than 1 space will place the registration on the wait list.');
    $settings->set('maximum_spaces', 0);
    $settings->save();
    $this->drupalGet('user/' . $this->adminUser->id() . '/register');
    $this->assertSession()->buttonExists('Save Registration');
    $this->assertSession()->pageTextContainsOnce('The number of spaces you wish to reserve. There is 1 space remaining. Registering for more than 1 space will place the registration on the wait list.');
    $settings->set('maximum_spaces', 5);
    $settings->set('capacity', 3);
    $settings->save();
    $this->drupalGet('user/' . $this->adminUser->id() . '/register');
    $this->assertSession()->buttonExists('Save Registration');
    $this->assertSession()->pageTextContainsOnce('The number of spaces you wish to reserve. There are 2 spaces remaining. You may register up to 5 spaces, although registering for more than 2 spaces will place the registration on the wait list.');
    $settings->set('maximum_spaces', 0);
    $settings->set('capacity', 3);
    $settings->save();
    $this->drupalGet('user/' . $this->adminUser->id() . '/register');
    $this->assertSession()->buttonExists('Save Registration');
    $this->assertSession()->pageTextContainsOnce('The number of spaces you wish to reserve. There are 2 spaces remaining. Registering for more than 2 spaces will place the registration on the wait list.');
    $settings->set('maximum_spaces', 1);
    $settings->set('capacity', 1);
    $settings->set('registration_waitlist_capacity', 10);
    $settings->save();
    $this->drupalGet('user/' . $this->adminUser->id() . '/register');
    $this->assertSession()->buttonExists('Save Registration');
    $this->assertSession()->pageTextContainsOnce('The number of spaces you wish to reserve. There are 10 spaces remaining on the wait list. You may register 1 space.');
    $settings->set('registration_waitlist_capacity', 1);
    $settings->save();
    $this->drupalGet('user/' . $this->adminUser->id() . '/register');
    $this->assertSession()->buttonExists('Save Registration');
    $this->assertSession()->pageTextContainsOnce('The number of spaces you wish to reserve. There is 1 space remaining on the wait list.');
    $this->assertSession()->pageTextNotContains('You may register 1 space.');
    $settings->set('registration_waitlist_capacity', 10);
    $settings->set('maximum_spaces', 2);
    $settings->save();
    $this->drupalGet('user/' . $this->adminUser->id() . '/register');
    $this->assertSession()->buttonExists('Save Registration');
    $this->assertSession()->pageTextContainsOnce('The number of spaces you wish to reserve. There are 10 spaces remaining on the wait list. You may register up to 2 spaces.');
    $settings->set('registration_waitlist_capacity', 2);
    $settings->save();
    $this->drupalGet('user/' . $this->adminUser->id() . '/register');
    $this->assertSession()->buttonExists('Save Registration');
    $this->assertSession()->pageTextContainsOnce('The number of spaces you wish to reserve. There are 2 spaces remaining on the wait list.');
    $this->assertSession()->pageTextNotContains('You may register up to 2 spaces.');
    $settings->set('maximum_spaces', 0);
    $settings->set('registration_waitlist_capacity', 0);
    $settings->save();
    $this->drupalGet('user/' . $this->adminUser->id() . '/register');
    $this->assertSession()->buttonExists('Save Registration');
    $this->assertSession()->pageTextContainsOnce('The number of spaces you wish to reserve on the wait list.');
    $this->assertSession()->pageTextNotContains('You may register');
    $settings->set('maximum_spaces', 5);
    $settings->set('capacity', 3);
    $settings->set('registration_waitlist_capacity', 10);
    $settings->save();
    $this->drupalGet('user/' . $this->adminUser->id() . '/register');
    $this->assertSession()->buttonExists('Save Registration');
    $this->assertSession()->pageTextContainsOnce('The number of spaces you wish to reserve. There are 2 spaces remaining, plus 10 remaining on the wait list. You may register up to 5 spaces, although registering for more than 2 spaces will place the registration on the wait list.');
    $settings->set('capacity', 2);
    $settings->set('registration_waitlist_capacity', 10);
    $settings->save();
    $this->drupalGet('user/' . $this->adminUser->id() . '/register');
    $this->assertSession()->buttonExists('Save Registration');
    $this->assertSession()->pageTextContainsOnce('The number of spaces you wish to reserve. There is 1 space remaining, plus 10 remaining on the wait list. You may register up to 5 spaces, although registering for more than 1 space will place the registration on the wait list.');
    $settings->set('capacity', 3);
    $settings->set('registration_waitlist_capacity', 3);
    $settings->save();
    $this->drupalGet('user/' . $this->adminUser->id() . '/register');
    $this->assertSession()->buttonExists('Save Registration');
    $this->assertSession()->pageTextContainsOnce('The number of spaces you wish to reserve. There are 2 spaces remaining, plus 3 remaining on the wait list. Registering for more than 2 spaces will place the registration on the wait list.');
    $settings->set('capacity', 2);
    $settings->set('registration_waitlist_capacity', 4);
    $settings->save();
    $this->drupalGet('user/' . $this->adminUser->id() . '/register');
    $this->assertSession()->buttonExists('Save Registration');
    $this->assertSession()->pageTextContainsOnce('The number of spaces you wish to reserve. There is 1 space remaining, plus 4 remaining on the wait list. Registering for more than 1 space will place the registration on the wait list.');

    // Register for one space on the wait list.
    $settings->set('capacity', 1);
    $settings->save();
    $this->drupalGet('user/' . $this->adminUser->id() . '/register');
    $this->submitForm([], 'Save Registration');
    $this->assertSession()->statusMessageExists('warning', 'Registration placed on the wait list.');
    $this->assertSession()->statusMessageNotExists('status', 'Registration has been saved.');
    $this->drupalLogout();
  }

}
