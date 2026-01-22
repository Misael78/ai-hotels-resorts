<?php

namespace Drupal\Tests\registration_confirmation\Kernel;

use Drupal\Tests\registration\Kernel\RegistrationKernelTestBase;
use Drupal\user\UserInterface;

/**
 * Provides a base class for Registration Confirmation kernel tests.
 */
abstract class RegistrationConfirmationKernelTestBase extends RegistrationKernelTestBase {

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
    'registration_confirmation',
  ];

  /**
   * The admin user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig('filter');
    $this->installSchema('dblog', 'watchdog');

    $admin_user = $this->createUser();
    $this->setCurrentUser($admin_user);
    $this->adminUser = $admin_user;
  }

}
