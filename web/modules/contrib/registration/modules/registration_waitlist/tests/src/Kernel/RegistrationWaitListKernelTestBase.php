<?php

namespace Drupal\Tests\registration_waitlist\Kernel;

use Drupal\Tests\registration\Kernel\RegistrationKernelTestBase;

/**
 * Provides a base class for Registration Wait List kernel tests.
 */
abstract class RegistrationWaitListKernelTestBase extends RegistrationKernelTestBase {

  /**
   * Modules to enable.
   *
   * Note that when a child class declares its own $modules list, that list
   * doesn't override this one, it just extends it.
   *
   * @var array
   */
  protected static $modules = [
    'dblog',
    'registration_waitlist',
    'registration_waitlist_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig('filter');
    $this->installSchema('dblog', 'watchdog');

    $module_handler = $this->container->get('module_handler');
    $module_handler->loadInclude('registration_waitlist', 'install');
    registration_waitlist_install();
  }

}
