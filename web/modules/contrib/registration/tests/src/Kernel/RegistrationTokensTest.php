<?php

namespace Drupal\Tests\registration\Kernel;

use Drupal\Core\Utility\Token;
use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\Tests\registration\Traits\RegistrationCreationTrait;

/**
 * Tests registration tokens.
 *
 * @group registration
 */
class RegistrationTokensTest extends RegistrationKernelTestBase {

  use NodeCreationTrait;
  use RegistrationCreationTrait;

  /**
   * The token service.
   */
  protected Token $tokenService;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['system']);

    $this->tokenService = $this->container->get('token');
  }

  /**
   * Tests token generation and chaining for registration tokens.
   */
  public function testRegistrationTokens() {
    $node = $this->createAndSaveNode();
    $registration = $this->createRegistration($node);
    $registration->set('anon_mail', 'test@example.org');
    $registration->save();

    // Simple tokens.
    $test_data = [
      '[registration:id]' => '1',
      '[registration:count]' => '1',
      '[registration:label]' => 'Registration #1 for My event',
      '[registration:mail]' => 'test@example.org',
      '[registration:state]' => 'Pending',
      '[registration:type]' => 'conference',
      '[registration:type-name]' => 'Conference',

      // Since the token module is not installed, field tokens will not resolve.
      '[registration:registration_id:value]' => '[registration:registration_id:value]',
      '[registration:count:value]' => '[registration:count:value]',
      '[registration:mail:value]' => '[registration:mail:value]',
    ];

    $token_data = [
      'registration' => $registration,
    ];

    foreach ($test_data as $token => $expected_value) {
      $token_replaced = $this->tokenService->replace($token, $token_data);
      $this->assertEquals($expected_value, $token_replaced);
    }

    // Chained host entity tokens.
    $test_data = [
      '[registration:entity]' => 'My event',
      '[registration:entity:nid]' => '1',
      '[registration:entity:title]' => 'My event',
      '[registration:entity:type]' => 'event',
      '[registration:entity:type-name]' => 'Event',
    ];

    foreach ($test_data as $token => $expected_value) {
      $token_replaced = $this->tokenService->replace($token, $token_data);
      $this->assertEquals($expected_value, $token_replaced);
    }
  }

  /**
   * Tests token generation and chaining for registration settings tokens.
   */
  public function testRegistrationSettingsTokens() {
    $node = $this->createAndSaveNode();
    $registration = $this->createRegistration($node);
    $registration->set('anon_mail', 'test@example.org');
    $registration->save();

    // Setup open and close dates.
    $settings = $registration->getHostEntity()->getSettings();
    $settings->set('open', '2020-01-01T00:00:00');
    $settings->set('close', '2220-01-01T00:00:00');
    $settings->set('send_reminder', TRUE);
    $settings->set('reminder_date', '2219-12-30T12:00:00');
    $settings->set('reminder_template', [
      'value' => 'This is an example reminder template.',
      'format' => 'plain_text',
    ]);
    $settings->save();

    // Simple tokens.
    if (version_compare(\Drupal::VERSION, '11.1', '>=')) {
      $test_data = [
        '[registration_settings:id]' => '1',
        '[registration_settings:open]' => 'Wed, 1 Jan 2020 - 00:00',
        '[registration_settings:close]' => 'Sat, 1 Jan 2220 - 00:00',
        '[registration_settings:send_reminder]' => 'Yes',
        '[registration_settings:reminder_date]' => 'Thu, 30 Dec 2219 - 12:00',
        '[registration_settings:reminder_template]' => 'This is an example reminder template.',
        '[registration_settings:status]' => 'Enabled',
        '[registration_settings:capacity]' => '5',
        '[registration_settings:maximum_spaces]' => '2',
        '[registration_settings:multiple_registrations]' => 'No',
        '[registration_settings:from_address]' => 'test@example.com',
        '[registration_settings:confirmation]' => 'The registration was saved.',

        // Since the token module is not installed, field tokens do not resolve.
        '[registration_settings:status:value]' => '[registration_settings:status:value]',
      ];
    }
    else {
      $test_data = [
        '[registration_settings:id]' => '1',
        '[registration_settings:open]' => 'Wed, 01/01/2020 - 00:00',
        '[registration_settings:close]' => 'Sat, 01/01/2220 - 00:00',
        '[registration_settings:send_reminder]' => 'Yes',
        '[registration_settings:reminder_date]' => 'Thu, 12/30/2219 - 12:00',
        '[registration_settings:reminder_template]' => 'This is an example reminder template.',
        '[registration_settings:status]' => 'Enabled',
        '[registration_settings:capacity]' => '5',
        '[registration_settings:maximum_spaces]' => '2',
        '[registration_settings:multiple_registrations]' => 'No',
        '[registration_settings:from_address]' => 'test@example.com',
        '[registration_settings:confirmation]' => 'The registration was saved.',

        // Since the token module is not installed, field tokens do not resolve.
        '[registration_settings:status:value]' => '[registration_settings:status:value]',
      ];
    }

    $token_data = [
      'registration_settings' => $registration->getHostEntity()->getSettings(),
    ];

    foreach ($test_data as $token => $expected_value) {
      $token_replaced = $this->tokenService->replace($token, $token_data);
      $this->assertEquals($expected_value, $token_replaced);
    }
  }

  /**
   * Tests token generation and chaining for host entity tokens.
   */
  public function testHostEntityTokens() {
    $node = $this->createAndSaveNode();
    $registration = $this->createRegistration($node);
    $registration->set('anon_mail', 'test@example.org');
    $registration->set('count', 2);
    $registration->save();

    // Simple tokens.
    $test_data = [
      '[registration:host-entity:id]' => '1',
      '[registration:host-entity:type]' => 'node',
      '[registration:host-entity:is-after-close]' => 'No',
      '[registration:host-entity:is-before-open]' => 'No',
      '[registration:host-entity:is-available]' => 'Yes',
      '[registration:host-entity:is-configured]' => 'Yes',
      '[registration:host-entity:registration-count]' => '1',
      '[registration:host-entity:spaces-reserved]' => '2',
      '[registration:host-entity:spaces-remaining]' => '3',
    ];

    $token_data = [
      'registration' => $registration,
    ];

    foreach ($test_data as $token => $expected_value) {
      $token_replaced = $this->tokenService->replace($token, $token_data);
      $this->assertEquals($expected_value, $token_replaced);
    }

    // Chained node tokens.
    $test_data = [
      '[node:registration-host-entity:id]' => '1',
      '[node:registration-host-entity:type]' => 'node',
      '[node:registration-host-entity:is-after-close]' => 'No',
      '[node:registration-host-entity:is-before-open]' => 'No',
      '[node:registration-host-entity:is-available]' => 'Yes',
      '[node:registration-host-entity:is-configured]' => 'Yes',
      '[node:registration-host-entity:registration-count]' => '1',
      '[node:registration-host-entity:spaces-reserved]' => '2',
      '[node:registration-host-entity:spaces-remaining]' => '3',
    ];

    $token_data = [
      'node' => $node,
    ];

    foreach ($test_data as $token => $expected_value) {
      $token_replaced = $this->tokenService->replace($token, $token_data);
      $this->assertEquals($expected_value, $token_replaced);
    }

    // Chained registration settings tokens.
    $test_data = [
      '[registration:host-entity:settings:id]' => '1',
      '[registration:host-entity:settings:status]' => 'Enabled',
      '[registration:host-entity:settings:capacity]' => '5',
      '[registration:host-entity:settings:maximum_spaces]' => '2',
      '[registration:host-entity:settings:multiple_registrations]' => 'No',
      '[registration:host-entity:settings:from_address]' => 'test@example.com',
      '[registration:host-entity:settings:confirmation]' => 'The registration was saved.',
    ];

    $token_data = [
      'registration' => $registration,
    ];

    foreach ($test_data as $token => $expected_value) {
      $token_replaced = $this->tokenService->replace($token, $token_data);
      $this->assertEquals($expected_value, $token_replaced);
    }

    // Chained host entity tokens for a host without registrations yet.
    $node = $this->createAndSaveNode();

    $test_data = [
      '[node:registration-host-entity:id]' => '2',
      '[node:registration-host-entity:type]' => 'node',
      '[node:registration-host-entity:is-after-close]' => 'No',
      '[node:registration-host-entity:is-before-open]' => 'No',
      '[node:registration-host-entity:is-available]' => 'Yes',
      '[node:registration-host-entity:is-configured]' => 'Yes',
      '[node:registration-host-entity:registration-count]' => '0',
      '[node:registration-host-entity:spaces-reserved]' => '0',
      '[node:registration-host-entity:spaces-remaining]' => '5',
      '[node:registration-host-entity:settings:id]' => '2',
      '[node:registration-host-entity:settings:status]' => 'Enabled',
      '[node:registration-host-entity:settings:capacity]' => '5',
      '[node:registration-host-entity:settings:maximum_spaces]' => '2',
      '[node:registration-host-entity:settings:multiple_registrations]' => 'No',
      '[node:registration-host-entity:settings:from_address]' => 'test@example.com',
      '[node:registration-host-entity:settings:confirmation]' => 'The registration was saved.',
    ];

    $token_data = [
      'node' => $node,
    ];

    foreach ($test_data as $token => $expected_value) {
      $token_replaced = $this->tokenService->replace($token, $token_data);
      $this->assertEquals($expected_value, $token_replaced);
    }
  }

}
