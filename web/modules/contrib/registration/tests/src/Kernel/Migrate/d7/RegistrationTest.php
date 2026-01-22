<?php

namespace Drupal\Tests\registration\Kernel\Migrate\d7;

use Drupal\Tests\migrate_drupal\Kernel\d7\MigrateDrupal7TestBase;
use Drupal\registration\Entity\Registration;

/**
 * Tests migration of registration data from Registration-D7.
 *
 * @group registration
 */
class RegistrationTest extends MigrateDrupal7TestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'comment',
    'content_translation',
    'datetime',
    'filter',
    'language',
    'link',
    'menu_link_content',
    'menu_ui',
    'node',
    'registration',
    'text',
    'workflows',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->loadFixture(__DIR__ . '/../../../../fixtures/d7_registration.php');

    $this->installEntitySchema('node');
    $this->installEntitySchema('comment');
    $this->installEntitySchema('menu_link_content');
    $this->installEntitySchema('workflow');
    $this->installConfig(static::$modules);
    $this->installSchema('node', ['node_access']);
    $this->installSchema('system', ['sequences']);
    $this->installEntitySchema('registration');
    $this->installEntitySchema('registration_settings');

    $this->startCollectingMessages();
    $this->executeMigrations([
      'language',
      'd7_node_type',
      'd7_registration_type',
      'd7_user_role',
      'd7_user',
      'd7_comment_type',
      'd7_field',
      'd7_field_instance',
      'd7_language_content_settings',
      'd7_node_complete',
      'd7_registration_settings',
      'd7_registration',
    ]);
  }

  /**
   * Tests the results of a registration migration.
   */
  public function testRegistration() {
    $registration = Registration::load(777);
    $this->assertEquals('node', $registration->getHostEntityTypeId());
    $this->assertEquals(998, $registration->getHostEntityId());
    $this->assertEquals('tradeshow', $registration->getType()->id());
    $this->assertEquals('Trade show', $registration->getType()->label());
    $this->assertEquals('pending', $registration->getState()->id());
    $this->assertEquals('test@example.org', $registration->getEmail());
    $this->assertEquals(1, $registration->getSpacesReserved());
    $this->assertEquals(1715360510, $registration->getCreatedTime());
    $this->assertEquals(1715382038, $registration->getChangedTime());

    $host_entity = $registration->getHostEntity();
    $this->assertEquals('node', $host_entity->getEntityTypeId());
    $this->assertEquals(998, $host_entity->id());
    $this->assertTrue($host_entity->isEnabledForRegistration());

    $settings = $host_entity->getSettings();
    $this->assertTrue((bool) $settings->getSetting('status'));
    $this->assertEquals(30, $settings->getSetting('capacity'));
    $this->assertEquals(1, $settings->getSetting('maximum_spaces'));
    $this->assertEquals('mail@example.org', $settings->getSetting('from_address'));
    $this->assertFalse((bool) $settings->getSetting('multiple_registrations'));
    $this->assertFalse((bool) $settings->getSetting('send_reminder'));
    $this->assertEquals('2024-01-01T09:00:00', $settings->getSetting('open'));
    $this->assertEquals('2084-12-31T09:00:00', $settings->getSetting('close'));
    $this->assertNull($settings->getSetting('reminder_date'));
    $this->assertNull($settings->getSetting('reminder_template'));
    $this->assertEquals('Your registration has been saved.', $settings->getSetting('confirmation'));
    $this->assertEmpty($settings->getSetting('confirmation_redirect'));
  }

}
