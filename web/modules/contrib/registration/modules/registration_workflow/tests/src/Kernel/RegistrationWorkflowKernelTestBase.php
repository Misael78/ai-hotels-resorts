<?php

namespace Drupal\Tests\registration_workflow\Kernel;

use Drupal\Tests\registration\Kernel\RegistrationKernelTestBase;
use Drupal\user\UserInterface;

/**
 * Provides a base class for Registration Workflow kernel tests.
 */
abstract class RegistrationWorkflowKernelTestBase extends RegistrationKernelTestBase {

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
    'registration_workflow',
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

    $this->installConfig('registration_workflow');

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
