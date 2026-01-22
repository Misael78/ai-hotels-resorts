<?php

namespace Drupal\Tests\registration\Kernel\Entity;

use Drupal\Tests\registration\Kernel\RegistrationKernelTestBase;
use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\Tests\registration\Traits\RegistrationCreationTrait;

/**
 * Tests the Registration Settings entity.
 *
 * @coversDefaultClass \Drupal\registration\Entity\RegistrationSettings
 *
 * @group registration
 */
class RegistrationSettingsTest extends RegistrationKernelTestBase {

  use NodeCreationTrait;
  use RegistrationCreationTrait;

  /**
   * @covers ::getHostEntity
   * @covers ::getHostEntityId
   * @covers ::getHostEntityTypeId
   * @covers ::getLangcode
   * @covers ::getSetting
   */
  public function testRegistrationSettings() {
    $node = $this->createAndSaveNode();
    $registration = $this->createAndSaveRegistration($node);
    $settings = $registration->getHostEntity()->getSettings();

    $this->assertEquals($node->id(), $settings->getHostEntity()->id());
    $this->assertEquals($node->id(), $settings->getHostEntityId());
    $this->assertEquals($node->getEntityTypeId(), $settings->getHostEntityTypeId());
    $this->assertEquals('en', $settings->getLangcode());
    // Default settings from the registration_test module.
    $this->assertTrue((bool) $settings->getSetting('status'));
    $this->assertEquals(5, $settings->getSetting('capacity'));
    $this->assertEquals(2, $settings->getSetting('maximum_spaces'));
    $this->assertEquals('test@example.com', $settings->getSetting('from_address'));
    // Settings for fields without an explicit default set.
    $this->assertNull($settings->getSetting('multiple_registrations'));
    $this->assertNull($settings->getSetting('send_reminder'));
    $this->assertNull($settings->getSetting('open'));
    $this->assertNull($settings->getSetting('close'));
    $this->assertNull($settings->getSetting('reminder_date'));
    $this->assertNull($settings->getSetting('reminder_template'));
    $this->assertNull($settings->getSetting('confirmation'));
    $this->assertNull($settings->getSetting('confirmation_redirect'));
  }

}
