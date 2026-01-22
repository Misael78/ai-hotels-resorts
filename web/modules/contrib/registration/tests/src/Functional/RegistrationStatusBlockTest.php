<?php

namespace Drupal\Tests\registration\Functional;

/**
 * Tests the registration status block.
 *
 * @group registration
 */
class RegistrationStatusBlockTest extends RegistrationBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'registration_test_block',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $settings = [
      'context_mapping' => [
        'entity' => '@registration_test_block.user_route_context:user',
      ],
      'enabled' => [
        'value' => 'Registration for this user is enabled.',
        'format' => 'plain_text',
      ],
      'disabled' => [
        'value' => 'Registration for this user is disabled.',
        'format' => 'plain_text',
      ],
      'disabled_before_open' => [
        'value' => 'Registration for this user is not open yet.',
        'format' => 'plain_text',
      ],
      'disabled_after_close' => [
        'value' => 'Registration for this user is closed.',
        'format' => 'plain_text',
      ],
      'disabled_capacity' => [
        'value' => 'Registration for this user is full.',
        'format' => 'plain_text',
      ],
      'label' => 'Registration Status',
      'remaining_spaces_single' => 'There is 1 space remaining.',
      'remaining_spaces_plural' => 'There are @count spaces remaining.',
    ];
    $this->drupalPlaceBlock('registration_status:user', $settings);
  }

  /**
   * Tests viewing the status block.
   */
  public function testStatusBlock() {
    $user = $this->drupalCreateUser();
    $user->set('field_registration', 'conference');
    $user->save();

    $handler = $this->entityTypeManager->getHandler('user', 'registration_host_entity');
    $host_entity = $handler->createHostEntity($user);

    /** @var \Drupal\registration\RegistrationSettingsStorage $settings_storage */
    $settings_storage = $this->entityTypeManager->getStorage('registration_settings');
    $settings = $settings_storage->loadSettingsForHostEntity($host_entity);

    $settings->set('status', FALSE);
    $settings->save();
    $this->drupalGet('/user/' . $user->id());
    $this->assertSession()->pageTextContains('Registration for this user is disabled.');

    $settings->set('status', TRUE);
    $settings->save();
    $this->drupalGet('/user/' . $user->id());
    $this->assertSession()->pageTextContains('Registration for this user is enabled.');

    $settings->set('open', '2220-01-01T00:00:00');
    $settings->save();
    $this->drupalGet('/user/' . $user->id());
    $this->assertSession()->pageTextContains('Registration for this user is not open yet.');

    $settings->set('open', NULL);
    $settings->set('close', '2020-01-01T00:00:00');
    $settings->save();
    $this->drupalGet('/user/' . $user->id());
    $this->assertSession()->pageTextContains('Registration for this user is closed.');

    $settings->set('open', NULL);
    $settings->set('close', NULL);
    $settings->set('capacity', 1);
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
    $this->drupalGet('/user/' . $user->id());
    $this->assertSession()->pageTextContains('Registration for this user is full.');
  }

}
