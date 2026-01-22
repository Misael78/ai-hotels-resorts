<?php

namespace Drupal\Tests\registration_change_host\Kernel;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Tests\registration\Kernel\RegistrationKernelTestBase;
use Drupal\Tests\registration_change_host\Traits\RegistrationChangeHostTrait;

/**
 * Base class for kernel tests of the Registration Change Host module.
 *
 * @group registration
 * @group registration_change_host
 */
class RegistrationChangeHostKernelTestBase extends RegistrationKernelTestBase {

  use RegistrationChangeHostTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'node',
    'user',
    'registration_test',
    'registration_change_host',
    'registration_change_host_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig('registration_change_host');

    $schema = $this->container->get('database')->schema();
    if (!$schema->tableExists('node_access')) {
      $this->installSchema('node', 'node_access');
    }

    $this->installEntitySchema('user');
    $this->registrationChangeHostSetUp();
  }

  /**
   * Enable or disable a host.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity that is a host.
   * @param string $name
   *   The name of the setting to set.
   * @param mixed $value
   *   The value to set.
   */
  protected function setHostSetting(EntityInterface $entity, $name, $value): void {
    $handler = $this->entityTypeManager->getHandler($entity->getEntityTypeId(), 'registration_host_entity');
    $host = $handler->createHostEntity($entity);
    $settings = $host->getSettings();
    $settings->set($name, $value);
    $settings->save();
  }

}
