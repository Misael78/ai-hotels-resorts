<?php

namespace Drupal\Tests\registration_cancel_by\Functional;

use Drupal\Tests\registration\Functional\RegistrationBrowserTestBase;

/**
 * Defines the base class for Registration Cancel By web tests.
 */
abstract class RegistrationCancelByBrowserTestBase extends RegistrationBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'registration_cancel_by',
    'registration_workflow',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalLogout();

    $this->adminUser->set('field_registration', 'conference');
    $this->adminUser->save();

    $handler = $this->entityTypeManager->getHandler('user', 'registration_host_entity');
    $host_entity = $handler->createHostEntity($this->adminUser);

    /** @var \Drupal\registration\RegistrationSettingsStorage $storage */
    $storage = $this->entityTypeManager->getStorage('registration_settings');
    $settings = $storage->loadSettingsForHostEntity($host_entity);
    $settings->set('status', TRUE);
    $settings->save();
  }

}
