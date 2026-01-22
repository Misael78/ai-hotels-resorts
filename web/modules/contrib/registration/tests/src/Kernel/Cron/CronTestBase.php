<?php

namespace Drupal\Tests\registration\Kernel\Cron;

use Drupal\Core\CronInterface;
use Drupal\Tests\registration\Kernel\RegistrationKernelTestBase;

/**
 * Provides a base test for cron tests.
 */
abstract class CronTestBase extends RegistrationKernelTestBase {

  /**
   * The cron interface.
   *
   * @var \Drupal\Core\CronInterface
   */
  protected CronInterface $cron;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $admin_user = $this->createUser();
    $this->setCurrentUser($admin_user);

    $this->cron = $this->container->get('cron');
  }

}
