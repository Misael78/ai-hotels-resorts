<?php

namespace Drupal\Tests\registration\Functional;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Tests the email host entity registrants page.
 *
 * @group registration
 */
class EmailRegistrantsTest extends RegistrationBrowserTestBase {

  /**
   * The configuration factory.
   */
  protected ConfigFactoryInterface $config;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->config = $this->container->get('config.factory');
  }

  /**
   * Tests send.
   */
  public function testSend() {
    $user = $this->drupalCreateUser();
    $user->set('field_registration', 'conference');
    $user->save();

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

    // The status filter is enabled by default, with active states checked.
    $this->drupalGet('user/' . $user->id() . '/registrations/broadcast');
    $this->assertSession()->fieldExists('states[pending]');
    $this->assertSession()->fieldExists('states[held]');
    $this->assertSession()->fieldExists('states[complete]');
    $this->assertSession()->fieldExists('states[canceled]');
    $this->assertSession()->checkboxChecked('states[pending]');
    $this->assertSession()->checkboxNotChecked('states[held]');
    $this->assertSession()->checkboxChecked('states[complete]');
    $this->assertSession()->checkboxNotChecked('states[canceled]');
    $edit = [
      'subject' => 'This is a test subject',
      'message[value]' => 'This is a test message.',
    ];
    $this->submitForm($edit, 'Send');
    $this->assertSession()->pageTextContains('Registration broadcast sent to 1 recipient.');

    // Filter to a status with no registrants.
    $this->drupalGet('user/' . $user->id() . '/registrations/broadcast');
    $edit = [
      'states[pending]' => FALSE,
      'states[held]' => 'held',
      'states[complete]' => FALSE,
      'subject' => 'This is a test subject',
      'message[value]' => 'This is a test message.',
    ];
    $this->submitForm($edit, 'Send');
    $this->assertSession()->pageTextContains('Registration broadcast sent to 0 recipients.');

    // Filter to two statuses, ome matching the registrant.
    $this->drupalGet('user/' . $user->id() . '/registrations/broadcast');
    $edit = [
      'states[pending]' => 'pending',
      'states[held]' => 'held',
      'states[complete]' => FALSE,
      'subject' => 'This is a test subject',
      'message[value]' => 'This is a test message.',
    ];
    $this->submitForm($edit, 'Send');
    $this->assertSession()->pageTextContains('Registration broadcast sent to 1 recipient.');

    // Disable the status filter.
    $this->config
      ->getEditable('registration.settings')
      ->set('broadcast_filter', FALSE)
      ->save();

    $this->drupalGet('user/' . $user->id() . '/registrations/broadcast');
    // The status field should disappear.
    $this->assertSession()->fieldNotExists('states[pending]');
    $this->assertSession()->fieldNotExists('states[held]');
    $this->assertSession()->fieldNotExists('states[complete]');
    $this->assertSession()->fieldNotExists('states[canceled]');
    $edit = [
      'subject' => 'This is a test subject',
      'message[value]' => 'This is a test message.',
    ];
    $this->submitForm($edit, 'Send');
    $this->assertSession()->pageTextContains('Registration broadcast sent to 1 recipient.');
  }

  /**
   * Tests preview.
   */
  public function testPreview() {
    $user = $this->drupalCreateUser();
    $user->set('field_registration', 'conference');
    $user->save();

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

    // The status filter is enabled by default, with active states checked.
    $this->drupalGet('user/' . $user->id() . '/registrations/broadcast');
    $this->assertSession()->checkboxChecked('states[pending]');
    $this->assertSession()->checkboxNotChecked('states[held]');
    $this->assertSession()->checkboxChecked('states[complete]');
    $this->assertSession()->checkboxNotChecked('states[canceled]');
    $edit = [
      'subject' => 'This is a test subject',
      'message[value]' => 'This is a test message.',
    ];
    $this->submitForm($edit, 'Preview');
    $this->assertSession()->pageTextContains('Email will be sent to registrants in these states: Pending, Complete');
    $this->assertSession()->pageTextContains('This is a test subject');
    $this->assertSession()->pageTextContains('This is a test message.');
    $this->assertSession()->hiddenFieldExists('states');
    $this->assertSession()->hiddenFieldExists('subject');
    $this->assertSession()->hiddenFieldValueEquals('subject', 'This is a test subject');
    $this->assertSession()->hiddenFieldExists('message');
    $this->getSession()->getPage()->pressButton('Edit message');

    $this->assertSession()->addressEquals('user/' . $user->id() . '/registrations/broadcast');
    $this->getSession()->getPage()->pressButton('Send');
    $this->assertSession()->pageTextContains('Registration broadcast sent to 1 recipient.');

    // Send directly from Preview.
    $this->drupalGet('user/' . $user->id() . '/registrations/broadcast');
    $edit = [
      'subject' => 'This is a test subject',
      'message[value]' => 'This is a test message.',
    ];
    $this->submitForm($edit, 'Preview');
    $this->getSession()->getPage()->pressButton('Send');
    $this->assertSession()->pageTextContains('Registration broadcast sent to 1 recipient.');

    // Disable the status filter and repeat.
    $this->config
      ->getEditable('registration.settings')
      ->set('broadcast_filter', FALSE)
      ->save();

    $this->drupalGet('user/' . $user->id() . '/registrations/broadcast');
    $this->assertSession()->fieldNotExists('states[pending]');
    $this->assertSession()->fieldNotExists('states[held]');
    $this->assertSession()->fieldNotExists('states[complete]');
    $this->assertSession()->fieldNotExists('states[canceled]');
    $edit = [
      'subject' => 'This is a test subject',
      'message[value]' => 'This is a test message.',
    ];
    $this->submitForm($edit, 'Preview');
    $this->assertSession()->pageTextNotContains('Email will be sent to registrants in these states');
    $this->assertSession()->pageTextContains('This is a test subject');
    $this->assertSession()->pageTextContains('This is a test message.');
    $this->assertSession()->hiddenFieldNotExists('states');
    $this->assertSession()->hiddenFieldExists('subject');
    $this->assertSession()->hiddenFieldValueEquals('subject', 'This is a test subject');
    $this->assertSession()->hiddenFieldExists('message');
    $this->getSession()->getPage()->pressButton('Edit message');

    $this->assertSession()->addressEquals('user/' . $user->id() . '/registrations/broadcast');
    $this->getSession()->getPage()->pressButton('Send');
    $this->assertSession()->pageTextContains('Registration broadcast sent to 1 recipient.');

    // Send directly from Preview.
    $this->drupalGet('user/' . $user->id() . '/registrations/broadcast');
    $edit = [
      'subject' => 'This is a test subject',
      'message[value]' => 'This is a test message.',
    ];
    $this->submitForm($edit, 'Preview');
    $this->getSession()->getPage()->pressButton('Send');
    $this->assertSession()->pageTextContains('Registration broadcast sent to 1 recipient.');
  }

}
