<?php

namespace Drupal\Tests\registration\Kernel\Cron;

use Drupal\Tests\registration\Traits\NodeCreationTrait;

/**
 * Tests the cron job that sends reminders.
 *
 * @coversDefaultClass \Drupal\registration\Cron\SetAndForget
 *
 * @group registration
 */
class SetAndForgetTest extends CronTestBase {

  use NodeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->container
      ->get('config.factory')
      ->getEditable('registration.settings')
      ->set('set_and_forget', TRUE)
      ->save();
  }

  /**
   * @covers ::run
   */
  public function testSetAndForget() {
    $handler = $this->entityTypeManager->getHandler('node', 'registration_host_entity');
    /** @var \Drupal\registration\RegistrationSettingsStorage $storage */
    $storage = $this->entityTypeManager->getStorage('registration_settings');

    // Disable registration for a host entity that is closed.
    $node = $this->createAndSaveNode();
    $host_entity = $handler->createHostEntity($node);

    $settings = $storage->loadSettingsForHostEntity($host_entity);
    $settings->set('close', '2020-01-01T00:00:00');
    $settings->save();
    $this->assertTrue((bool) $settings->getSetting('status'));

    $this->cron->run();

    /** @var \Drupal\registration\Entity\RegistrationSettings $settings */
    $settings = $this->reloadEntity($settings);
    $this->assertFalse((bool) $settings->getSetting('status'));

    // Disable registration for a host entity that is not open yet.
    $node = $this->createAndSaveNode();
    $host_entity = $handler->createHostEntity($node);

    $settings = $storage->loadSettingsForHostEntity($host_entity);
    $settings->set('open', '2220-01-01T00:00:00');
    $settings->save();
    $this->assertTrue((bool) $settings->getSetting('status'));

    $this->cron->run();

    /** @var \Drupal\registration\Entity\RegistrationSettings $settings */
    $settings = $this->reloadEntity($settings);
    $this->assertFalse((bool) $settings->getSetting('status'));

    // Do not disable registration for a host entity that is still open.
    $node = $this->createAndSaveNode();
    $host_entity = $handler->createHostEntity($node);

    $settings = $storage->loadSettingsForHostEntity($host_entity);
    $settings->set('open', '2020-01-01T00:00:00');
    $settings->set('close', '2220-01-01T00:00:00');
    $settings->save();
    $this->assertTrue((bool) $settings->getSetting('status'));

    $this->cron->run();

    /** @var \Drupal\registration\Entity\RegistrationSettings $settings */
    $settings = $this->reloadEntity($settings);
    $this->assertTrue((bool) $settings->getSetting('status'));

    // Enable registration for a host entity that is open and disabled.
    $node = $this->createAndSaveNode();
    $host_entity = $handler->createHostEntity($node);

    $settings = $storage->loadSettingsForHostEntity($host_entity);
    $settings->set('open', '2020-01-01T00:00:00');
    $settings->set('close', '2220-01-01T00:00:00');
    $settings->set('status', FALSE);
    $settings->save();
    $this->assertFalse((bool) $settings->getSetting('status'));

    $this->cron->run();

    /** @var \Drupal\registration\Entity\RegistrationSettings $settings */
    $settings = $this->reloadEntity($settings);
    $this->assertTrue((bool) $settings->getSetting('status'));
  }

}
