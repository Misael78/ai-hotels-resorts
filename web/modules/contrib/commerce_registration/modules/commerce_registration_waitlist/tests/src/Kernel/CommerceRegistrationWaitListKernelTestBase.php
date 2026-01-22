<?php

namespace Drupal\Tests\commerce_registration_waitlist\Kernel;

use Drupal\Tests\commerce_registration\Kernel\CommerceRegistrationKernelTestBase;

/**
 * Provides a base class for Registration Wait List kernel tests.
 */
abstract class CommerceRegistrationWaitListKernelTestBase extends CommerceRegistrationKernelTestBase {

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
    'commerce_registration_waitlist',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $storage = $this->entityTypeManager->getStorage('workflow');
    if ($workflow = $storage->load('registration')) {
      $workflow_type = $workflow->getTypePlugin();
      $configuration = $workflow_type->getConfiguration();
      $configuration['states']['waitlist'] = [
        'label' => 'Wait list',
        'active' => FALSE,
        'canceled' => FALSE,
        'held' => FALSE,
        'show_on_form' => TRUE,
        'description' => 'Special state for registrations after capacity is reached.',
        'weight' => 10,
      ];
      $configuration['transitions']['complete']['from'][] = 'waitlist';
      $configuration['transitions']['cancel']['from'][] = 'waitlist';
      $workflow_type->setConfiguration($configuration);
      $workflow->save();
    }
  }

}
