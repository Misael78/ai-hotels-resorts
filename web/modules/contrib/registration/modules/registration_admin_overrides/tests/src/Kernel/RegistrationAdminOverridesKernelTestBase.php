<?php

namespace Drupal\Tests\registration_admin_overrides\Kernel;

use Drupal\Tests\registration\Kernel\RegistrationKernelTestBase;

/**
 * Provides a base class for registration administrative override kernel tests.
 */
abstract class RegistrationAdminOverridesKernelTestBase extends RegistrationKernelTestBase {

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

    // Enable all overrides, subject to user permissions.
    $this->regType->setThirdPartySetting('registration_admin_overrides', 'status', TRUE);
    $this->regType->setThirdPartySetting('registration_admin_overrides', 'maximum_spaces', TRUE);
    $this->regType->setThirdPartySetting('registration_admin_overrides', 'capacity', TRUE);
    $this->regType->setThirdPartySetting('registration_admin_overrides', 'open', TRUE);
    $this->regType->setThirdPartySetting('registration_admin_overrides', 'close', TRUE);
    $this->regType->save();
  }

}
