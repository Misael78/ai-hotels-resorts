<?php

namespace Drupal\Tests\registration_scheduled_action\Kernel;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\Tests\registration\Traits\RegistrationCreationTrait;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

/**
 * Tests uninstall.
 *
 * @group registration
 */
class RegistrationScheduledActionUninstallTest extends RegistrationScheduledActionKernelTestBase {

  use NodeCreationTrait;
  use RegistrationCreationTrait;

  /**
   * Tests that key value entries are deleted by the uninstall hook.
   */
  public function testUninstall() {
    $storage_format = DateTimeItemInterface::DATETIME_STORAGE_FORMAT;
    $storage_timezone = new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE);
    $date = new DrupalDateTime('now', $storage_timezone);
    $scheduled_action = $this->entityTypeManager->getStorage('registration_scheduled_action')->load('test_future');
    /** @var \Drupal\registration_scheduled_action\Entity\ScheduledActionInterface $scheduled_action */
    $plugin = $scheduled_action->getPlugin();
    $collection = $plugin->getKeyValueStoreCollectionName();
    $key_value_store = $this->keyValueFactory->get($collection);
    $node = $this->createAndSaveNode();
    $registration = $this->createAndSaveRegistration($node);
    $host_entity = $registration->getHostEntity();
    $settings = $host_entity->getSettings();
    $php_date = $date->getPhpDateTime();
    $close = $php_date->modify('+1 hour')->format($storage_format);
    $settings->set('close', $close);
    $settings->save();
    $key = $scheduled_action->getKeyValueStoreKeyName($settings->id());
    $this->assertFalse($key_value_store->has($key));
    $this->cron->run();
    // Confirm there is a key value entry.
    $this->assertTrue($key_value_store->has($key));
    // Add a second key value entry that should not be removed after uninstall.
    $key_value_store2 = $this->keyValueFactory->get('some_other_collection');
    $key_value_store2->setWithExpire('example_key', 'example_value', 3600);
    // Uninstall the module.
    $this->container->get('module_installer')->uninstall(['registration_scheduled_action']);
    // Confirm the key value entry has been deleted by the uninstall hook.
    $this->assertFalse($key_value_store->has($key));
    // Confirm the unrelated key value entry is still there.
    $this->assertTrue($key_value_store2->has('example_key'));
  }

}
