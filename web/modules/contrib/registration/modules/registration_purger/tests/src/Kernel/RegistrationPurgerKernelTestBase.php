<?php

namespace Drupal\Tests\registration_purger\Kernel;

use Drupal\Tests\registration\Kernel\RegistrationKernelTestBase;
use Drupal\user\UserInterface;

/**
 * Provides a base class for Registration Scheduled Action kernel tests.
 */
abstract class RegistrationPurgerKernelTestBase extends RegistrationKernelTestBase {

  /**
   * Modules to enable.
   *
   * Note that when a child class declares its own $modules list, that list
   * doesn't override this one, it just extends it.
   *
   * @var array
   */
  protected static $modules = [
    'registration_purger',
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

    $admin_user = $this->createUser();
    $this->setCurrentUser($admin_user);
    $this->adminUser = $admin_user;
  }

}
