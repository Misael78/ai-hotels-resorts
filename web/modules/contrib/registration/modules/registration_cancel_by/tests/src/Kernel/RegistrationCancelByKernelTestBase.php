<?php

namespace Drupal\Tests\registration_cancel_by\Kernel;

use Drupal\Tests\registration\Kernel\RegistrationKernelTestBase;

/**
 * Provides a base class for Registration Cancel By kernel tests.
 */
abstract class RegistrationCancelByKernelTestBase extends RegistrationKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'registration_cancel_by',
    'registration_workflow',
  ];

}
