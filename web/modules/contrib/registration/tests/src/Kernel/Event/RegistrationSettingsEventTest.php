<?php

namespace Drupal\Tests\registration\Kernel\Event;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\CronInterface;
use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\Tests\registration\Traits\RegistrationCreationTrait;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

/**
 * Tests registration settings events.
 *
 * @coversDefaultClass \Drupal\registration\Event\RegistrationSettingsEvent
 *
 * @group registration
 */
class RegistrationSettingsEventTest extends EventTestBase {

  use NodeCreationTrait;
  use RegistrationCreationTrait;

  /**
   * The cron interface.
   *
   * @var \Drupal\Core\CronInterface
   */
  protected CronInterface $cron;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'registration_test_open_close',
    'registration_scheduled_action',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->cron = $this->container->get('cron');

    // Schedule open and close actions.
    $scheduled_action = $this->entityTypeManager
      ->getStorage('registration_scheduled_action')
      ->create([
        'langcode' => 'en',
        'status' => TRUE,
        'id' => 'dispatch_event_on_open',
        'label' => 'Dispatch event on open',
        'datetime' => [
          'length' => 0,
          'type' => 'minutes',
          'position' => 'after',
        ],
        'plugin' => 'dispatch_event_on_open_action',
        'target_langcode' => 'und',
        'configuration' => [
          'plugin_date' => '',
        ],
      ]);
    $scheduled_action->save();

    $scheduled_action = $this->entityTypeManager
      ->getStorage('registration_scheduled_action')
      ->create([
        'langcode' => 'en',
        'status' => TRUE,
        'id' => 'dispatch_event_on_close',
        'label' => 'Dispatch event on close',
        'datetime' => [
          'length' => 0,
          'type' => 'minutes',
          'position' => 'after',
        ],
        'plugin' => 'dispatch_event_on_close_action',
        'target_langcode' => 'und',
        'configuration' => [
          'plugin_date' => '',
        ],
      ]);
    $scheduled_action->save();
  }

  /**
   * @covers ::getSettings
   *
   * Event subscribers in the test module do the following:
   *
   * on open - enable registration, increment maximum allowed spaces
   * on close - disable registration, decrement maximum allowed spaces
   *
   * @see \Drupal\registration_test_open_close\EventSubscriber\RegistrationSettingsEventSubscriber
   */
  public function testRegistrationSettingsEvent() {
    $node = $this->createAndSaveNode();

    /** @var \Drupal\registration\HostEntityInterface $host_entity */
    $host_entity = $this->entityTypeManager
      ->getHandler($node->getEntityTypeId(), 'registration_host_entity')
      ->createHostEntity($node);

    // Start with maximum spaces 1 and registration disabled.
    $settings = $host_entity->getSettings();
    $settings->set('maximum_spaces', 1);
    $settings->set('status', 0);
    $settings->save();

    // Run cron. There is no open date so nothing will happen since the
    // settings open subscriber will not be invoked.
    $this->cron->run();

    $settings = $this->reloadEntity($settings);
    $this->assertEquals(1, (int) $settings->getSetting('maximum_spaces'));
    $this->assertFalse((bool) $settings->getSetting('status'));

    // Add an open date and run cron. Registration will be opened and
    // maximum spaces will increment.
    $storage_format = DateTimeItemInterface::DATETIME_STORAGE_FORMAT;
    $storage_timezone = new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE);
    $now = new DrupalDateTime('now', $storage_timezone);
    $now_date = $now->format($storage_format);
    $settings->set('open', $now_date);
    $settings->save();

    $this->cron->run();

    $settings = $this->reloadEntity($settings);
    $this->assertEquals(2, (int) $settings->getSetting('maximum_spaces'));
    $this->assertTrue((bool) $settings->getSetting('status'));

    // Run cron again. The "open" event only fires once, so the maximum
    // spaces field should not increment again.
    $this->cron->run();

    $settings = $this->reloadEntity($settings);
    $this->assertEquals(2, (int) $settings->getSetting('maximum_spaces'));
    $this->assertTrue((bool) $settings->getSetting('status'));

    // Add a close date and run cron. Registration will be disabled and
    // maximum spaces will decrement.
    $storage_format = DateTimeItemInterface::DATETIME_STORAGE_FORMAT;
    $storage_timezone = new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE);
    $now = new DrupalDateTime('now', $storage_timezone);
    $now_date = $now->format($storage_format);
    $settings->set('close', $now_date);
    $settings->save();

    $this->cron->run();

    $settings = $this->reloadEntity($settings);
    $this->assertEquals(1, (int) $settings->getSetting('maximum_spaces'));
    $this->assertFalse((bool) $settings->getSetting('status'));

    // Run cron again. The "close" event only fires once, so the maximum
    // spaces field should not decrement again.
    $this->cron->run();

    $settings = $this->reloadEntity($settings);
    $this->assertEquals(1, (int) $settings->getSetting('maximum_spaces'));
    $this->assertFalse((bool) $settings->getSetting('status'));

    // Confirm that events are only fired for the appropriate settings
    // dates and not all.
    $node1 = $this->createAndSaveNode();
    $node2 = $this->createAndSaveNode();
    $node3 = $this->createAndSaveNode();

    /** @var \Drupal\registration\HostEntityInterface $host_entity */
    $host_entity1 = $this->entityTypeManager
      ->getHandler($node->getEntityTypeId(), 'registration_host_entity')
      ->createHostEntity($node1);
    $host_entity2 = $this->entityTypeManager
      ->getHandler($node->getEntityTypeId(), 'registration_host_entity')
      ->createHostEntity($node2);
    $host_entity3 = $this->entityTypeManager
      ->getHandler($node->getEntityTypeId(), 'registration_host_entity')
      ->createHostEntity($node3);

    // An event should be fired for setting 1 but not 2 or 3.
    $settings1 = $host_entity1->getSettings();
    $settings1->set('maximum_spaces', 1);
    $settings1->set('status', 0);
    $now = new DrupalDateTime('now', $storage_timezone);
    $now_date = $now->format($storage_format);
    $settings1->set('open', $now_date);
    $settings1->save();

    $settings2 = $host_entity2->getSettings();
    $settings2->set('maximum_spaces', 1);
    $settings2->set('status', 0);
    // Opens in 15 minutes.
    $php_date_time = $now->getPhpDateTime();
    $interval = new \DateInterval('PT15M');
    $php_date_time->add($interval);
    $date = DrupalDateTime::createFromDateTime($php_date_time);
    $settings2->set('open', $date->format($storage_format));
    $settings2->save();

    $settings3 = $host_entity3->getSettings();
    $settings3->set('maximum_spaces', 1);
    $settings3->set('status', 0);
    // Closed one hour ago.
    // If the interval was 59 minutes ago an event would fire.
    $php_date_time = $now->getPhpDateTime();
    $interval = new \DateInterval('PT1H');
    $php_date_time->sub($interval);
    $date = DrupalDateTime::createFromDateTime($php_date_time);
    $settings3->set('close', $date->format($storage_format));
    $settings3->save();

    $this->cron->run();

    $settings1 = $this->reloadEntity($settings1);
    $this->assertEquals(2, (int) $settings1->getSetting('maximum_spaces'));
    $this->assertTrue((bool) $settings1->getSetting('status'));

    $settings2 = $this->reloadEntity($settings2);
    $this->assertEquals(1, (int) $settings2->getSetting('maximum_spaces'));
    $this->assertFalse((bool) $settings2->getSetting('status'));

    $settings3 = $this->reloadEntity($settings3);
    $this->assertEquals(1, (int) $settings3->getSetting('maximum_spaces'));
    $this->assertFalse((bool) $settings3->getSetting('status'));
  }

}
