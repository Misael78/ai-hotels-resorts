<?php

namespace Drupal\Tests\registration_scheduled_action\Kernel;

use Drupal\Core\CronInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Tests\registration\Kernel\RegistrationKernelTestBase;

/**
 * Provides a base class for Registration Scheduled Action kernel tests.
 */
abstract class RegistrationScheduledActionKernelTestBase extends RegistrationKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'dblog',
    'registration_scheduled_action',
  ];

  /**
   * The cron interface.
   *
   * @var \Drupal\Core\CronInterface
   */
  protected CronInterface $cron;

  /**
   * The key value factory.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface
   */
  protected KeyValueExpirableFactoryInterface $keyValueFactory;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig('filter');
    $this->installSchema('dblog', 'watchdog');
    $this->installSchema('user', 'users_data');

    $this->cron = $this->container->get('cron');
    $this->keyValueFactory = $this->container->get('keyvalue.expirable');

    $storage = $this->entityTypeManager->getStorage('registration_scheduled_action');

    // Send an email to host entity registrants 1 hour before the registration
    // close date.
    $action = $storage->create([
      'id' => 'test_future',
      'label' => 'Test future event',
      'datetime' => [
        'length' => 1,
        'type' => 'hours',
        'position' => 'before',
      ],
      'plugin' => 'registration_email_registrants_action',
      'target_langcode' => 'und',
      'configuration' => [
        'subject' => 'Test message',
        'message' => [
          'value' => 'This is a test message',
          'format' => 'plain_text',
        ],
      ],
    ]);
    $action->save();

    // Send an email to host entity registrants 2 days after the registration
    // close date.
    $action = $storage->create([
      'id' => 'test_past',
      'label' => 'Test past event',
      'datetime' => [
        'length' => 2,
        'type' => 'days',
        'position' => 'after',
      ],
      'plugin' => 'registration_email_registrants_action',
      'target_langcode' => 'und',
      'configuration' => [
        'subject' => 'Test message',
        'message' => [
          'value' => 'This is a test message',
          'format' => 'plain_text',
        ],
      ],
    ]);
    $action->save();
  }

}
