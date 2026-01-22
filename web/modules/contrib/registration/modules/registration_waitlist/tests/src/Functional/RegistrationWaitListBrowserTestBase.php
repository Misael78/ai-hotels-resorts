<?php

namespace Drupal\Tests\registration_waitlist\Functional;

use Drupal\Tests\registration\Functional\RegistrationBrowserTestBase;

/**
 * Defines the base class for registration wait list web tests.
 */
abstract class RegistrationWaitListBrowserTestBase extends RegistrationBrowserTestBase {

  /**
   * Modules to enable.
   *
   * Note that when a child class declares its own $modules list, that list
   * doesn't override this one, it just extends it.
   *
   * @var array
   */
  protected static $modules = [
    'registration_waitlist',
    'registration_waitlist_test',
  ];

}
