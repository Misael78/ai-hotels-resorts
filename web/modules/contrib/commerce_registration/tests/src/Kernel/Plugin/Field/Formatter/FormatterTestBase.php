<?php

namespace Drupal\Tests\commerce_registration\Kernel\Plugin\Field\Formatter;

use Drupal\Tests\commerce_registration\Kernel\CommerceRegistrationKernelTestBase;

/**
 * Provides a base test for kernel formatter tests.
 */
abstract class FormatterTestBase extends CommerceRegistrationKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $admin_user = $this->createUser();
    $this->setCurrentUser($admin_user);

    $this->installConfig(['system']);

    $formats = $this->entityTypeManager
      ->getStorage('date_format')
      ->loadMultiple(['long', 'medium', 'short']);
    $formats['long']->setPattern('l, j. F Y - G:i')->save();
    $formats['medium']->setPattern('j. F Y - G:i')->save();
    $formats['short']->setPattern('Y M j - g:ia')->save();
  }

  /**
   * Render a field.
   *
   * @param array $build
   *   The render array for the field.
   *
   * @return string
   *   The rendered output, with <div> tags removed to make assertions easier.
   */
  protected function renderField(array $build): string {

    $output = trim($this->container->get('renderer')->renderInIsolation($build));

    return preg_replace("/<div>(.*?)<\/div>/", "$1", $output);
  }

}
