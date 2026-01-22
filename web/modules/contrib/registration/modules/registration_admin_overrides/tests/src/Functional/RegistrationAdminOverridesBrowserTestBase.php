<?php

namespace Drupal\Tests\registration_admin_overrides\Functional;

use Drupal\Tests\registration\Functional\RegistrationBrowserTestBase;

/**
 * Defines the base class for registration administrative override web tests.
 */
abstract class RegistrationAdminOverridesBrowserTestBase extends RegistrationBrowserTestBase {

  /**
   * Modules to enable.
   *
   * Note that when a child class declares its own $modules list, that list
   * doesn't override this one, it just extends it.
   *
   * @var array
   */
  protected static $modules = [
    'registration_admin_overrides',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $registration_type = $this->entityTypeManager->getStorage('registration_type')->load('conference');

    // Enable all overrides, subject to user permissions.
    $registration_type->setThirdPartySetting('registration_admin_overrides', 'status', TRUE);
    $registration_type->setThirdPartySetting('registration_admin_overrides', 'maximum_spaces', TRUE);
    $registration_type->setThirdPartySetting('registration_admin_overrides', 'capacity', TRUE);
    $registration_type->setThirdPartySetting('registration_admin_overrides', 'open', TRUE);
    $registration_type->setThirdPartySetting('registration_admin_overrides', 'close', TRUE);
    $registration_type->save();
  }

}
