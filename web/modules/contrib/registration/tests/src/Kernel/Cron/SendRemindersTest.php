<?php

namespace Drupal\Tests\registration\Kernel\Cron;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

/**
 * Tests the cron job that sends reminders.
 *
 * @coversDefaultClass \Drupal\registration\Cron\SendReminders
 *
 * @group registration
 */
class SendRemindersTest extends CronTestBase {

  use NodeCreationTrait;

  /**
   * @covers ::run
   */
  public function testSendReminders() {
    $handler = $this->entityTypeManager->getHandler('node', 'registration_host_entity');
    /** @var \Drupal\registration\RegistrationSettingsStorage $storage */
    $storage = $this->entityTypeManager->getStorage('registration_settings');

    $storage_format = DateTimeItemInterface::DATETIME_STORAGE_FORMAT;
    $storage_timezone = new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE);
    $now = new DrupalDateTime('now', $storage_timezone);
    $now_date = $now->format($storage_format);

    $node = $this->createAndSaveNode();
    $host_entity = $handler->createHostEntity($node);

    // Send reminder.
    $settings = $storage->loadSettingsForHostEntity($host_entity);
    $settings->set('send_reminder', TRUE);
    $settings->set('reminder_date', $now_date);
    $settings->set('reminder_template', [
      'value' => 'This is a reminder message',
      'format' => 'plain_text',
    ]);
    $settings->save();
    $this->assertTrue((bool) $settings->getSetting('send_reminder'));

    $this->cron->run();

    /** @var \Drupal\registration\Entity\RegistrationSettings $settings */
    $settings = $this->reloadEntity($settings);
    $this->assertFalse((bool) $settings->getSetting('send_reminder'));

    // Do not send a reminder if the reminder date is in the future.
    $now = $now->add(new \DateInterval('P1D'));
    $now_date = $now->format($storage_format);
    $settings = $storage->loadSettingsForHostEntity($host_entity);
    $settings->set('send_reminder', TRUE);
    $settings->set('reminder_date', $now_date);
    $settings->set('reminder_template', [
      'value' => 'This is a reminder message',
      'format' => 'plain_text',
    ]);
    $settings->save();
    $this->assertTrue((bool) $settings->getSetting('send_reminder'));

    $this->cron->run();

    /** @var \Drupal\registration\Entity\RegistrationSettings $settings */
    $settings = $this->reloadEntity($settings);
    $this->assertTrue((bool) $settings->getSetting('send_reminder'));
  }

}
