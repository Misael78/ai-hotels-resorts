<?php

namespace Drupal\Tests\registration\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\registration\Entity\RegistrationType;
use Drupal\registration\Entity\RegistrationTypeInterface;

/**
 * Provides a base class for Registration kernel tests.
 */
abstract class RegistrationKernelTestBase extends EntityKernelTestBase {

  /**
   * Modules to enable.
   *
   * Note that when a child class declares its own $modules list, that list
   * doesn't override this one, it just extends it.
   *
   * @var array
   */
  protected static $modules = [
    'datetime',
    'node',
    'registration',
    'registration_test',
    'workflows',
  ];

  /**
   * {@inheritdoc}
   */
  protected bool $usesSuperUserAccessPolicy = FALSE;

  /**
   * The registration type.
   *
   * @var \Drupal\registration\Entity\RegistrationTypeInterface
   */
  protected RegistrationTypeInterface $regType;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('node', 'node_access');

    $this->installConfig('registration');

    $this->installEntitySchema('node');
    $this->installEntitySchema('registration');
    $this->installEntitySchema('registration_settings');
    $this->installEntitySchema('workflow');

    $node_type = NodeType::create([
      'type' => 'event',
      'name' => 'Event',
    ]);
    $node_type->save();

    $registration_type = RegistrationType::create([
      'id' => 'conference',
      'label' => 'Conference',
      'workflow' => 'registration',
      'defaultState' => 'pending',
      'heldExpireTime' => 1,
      'heldExpireState' => 'canceled',
    ]);
    $registration_type->save();
    /** @var \Drupal\registration\Entity\RegistrationTypeInterface $registration_type */
    $registration_type = $this->reloadEntity($registration_type);

    // The registration type should have a dependency on its workflow.
    $dependencies = $registration_type->getDependencies();
    $this->assertContains('workflows.workflow.registration', $dependencies['config']);

    $this->regType = $registration_type;
  }

}
