<?php

namespace Drupal\Tests\registration\Kernel;

use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\Tests\registration\Traits\RegistrationCreationTrait;
use Drupal\node\NodeInterface;
use Drupal\registration\Entity\RegistrationInterface;
use Drupal\registration\HostEntityInterface;
use Drupal\registration\RegistrationManagerInterface;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * Tests the RegistrationManager class.
 *
 * @coversDefaultClass \Drupal\registration\RegistrationManager
 *
 * @group registration
 */
class RegistrationManagerTest extends RegistrationKernelTestBase {

  use NodeCreationTrait;
  use RegistrationCreationTrait;

  /**
   * The host entity.
   *
   * @var \Drupal\registration\HostEntityInterface
   */
  protected HostEntityInterface $hostEntity;

  /**
   * The node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected NodeInterface $node;

  /**
   * The registration manager.
   *
   * @var \Drupal\registration\RegistrationManagerInterface
   */
  protected RegistrationManagerInterface $registrationManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->node = $this->createAndSaveNode();

    $this->registrationManager = $this->container->get('registration.manager');

    $registration = $this->createRegistration($this->node);
    $registration->set('anon_mail', 'test@example.com');
    $registration->save();
    $this->hostEntity = $registration->getHostEntity();
  }

  /**
   * @covers ::getBaseRouteName
   * @covers ::getEntityFromParameters
   * @covers ::getRegistrantOptions
   * @covers ::getRegistrationEnabledEntityTypes
   * @covers ::getRegistrationFieldDefinitions
   * @covers ::getRoute
   * @covers ::getWorkflowStateOptions
   * @covers ::hasRegistrationField
   * @covers ::userHasRegistrations
   */
  public function testRegistrationManager() {
    $entity_type = $this->node->getEntityType();
    $base_route_name = $this->registrationManager->getBaseRouteName($entity_type);
    $this->assertEquals('entity.node.canonical', $base_route_name);

    $parameter_bag = new ParameterBag([
      'node' => $this->node,
    ]);

    $host_entity = $this->registrationManager->getEntityFromParameters($parameter_bag, TRUE);
    $this->assertEquals($this->hostEntity->id(), $host_entity->id());
    $entity = $this->registrationManager->getEntityFromParameters($parameter_bag, FALSE);
    $this->assertEquals($this->node->id(), $entity->id());

    $settings = $this->hostEntity->getSettings();
    $list = $this->hostEntity->getRegistrationList();
    $registration = reset($list);

    $account = $this->createUser([
      'create conference registration self',
      'create conference registration other users',
      'create conference registration other anonymous',
    ]);
    $this->setCurrentUser($account);
    $options = $this->registrationManager->getRegistrantOptions($registration, $settings);
    $this->assertArrayHasKey(RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_ANON, $options);
    $this->assertArrayHasKey(RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_USER, $options);
    $this->assertArrayHasKey(RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_ME, $options);

    $registration2 = $this->createRegistration($this->node);
    $registration2->set('user_uid', 1);
    $registration2->save();

    $options = $this->registrationManager->getRegistrantOptions($registration, $settings);
    $this->assertArrayHasKey(RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_ANON, $options);
    $this->assertArrayHasKey(RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_USER, $options);
    // The current user (1) is already registered.
    $this->assertArrayNotHasKey(RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_ME, $options);

    $account = $this->createUser(['access registration overview']);
    $this->setCurrentUser($account);
    $options = $this->registrationManager->getRegistrantOptions($registration, $settings);
    // The existing option is always included for an existing registration.
    $this->assertArrayHasKey(RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_ANON, $options);
    $this->assertArrayNotHasKey(RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_USER, $options);
    $this->assertArrayNotHasKey(RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_ME, $options);

    $account = $this->createUser(['create conference registration self']);
    $this->setCurrentUser($account);
    $options = $this->registrationManager->getRegistrantOptions($registration, $settings);
    // The existing option is always included for an existing registration.
    $this->assertArrayHasKey(RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_ANON, $options);
    $this->assertArrayNotHasKey(RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_USER, $options);
    $this->assertArrayHasKey(RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_ME, $options);

    $account = $this->createUser(['create conference registration other users']);
    $this->setCurrentUser($account);
    $options = $this->registrationManager->getRegistrantOptions($registration2, $settings);
    $this->assertArrayNotHasKey(RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_ANON, $options);
    $this->assertArrayHasKey(RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_USER, $options);
    $this->assertArrayNotHasKey(RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_ME, $options);

    $account = $this->createUser(['create conference registration other anonymous']);
    $this->setCurrentUser($account);
    $options = $this->registrationManager->getRegistrantOptions($registration, $settings);
    $this->assertArrayHasKey(RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_ANON, $options);
    $this->assertArrayNotHasKey(RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_USER, $options);
    $this->assertArrayNotHasKey(RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_ME, $options);

    $types = $this->registrationManager->getRegistrationEnabledEntityTypes();
    $this->assertArrayHasKey('node', $types);

    $field_definitions = $this->registrationManager->getRegistrationFieldDefinitions();
    $field_definition = reset($field_definitions);
    $this->assertEquals('event_registration', $field_definition->getName());
    $this->assertEquals('node', $field_definition->getTargetEntityTypeId());

    $route = $this->registrationManager->getRoute($entity_type, 'register');
    $this->assertEquals('/node/{node}/register', $route->getPath());

    $state_options = $this->registrationManager->getWorkflowStateOptions();
    $this->assertArrayHasKey('pending', $state_options);
    $this->assertArrayHasKey('held', $state_options);
    $this->assertArrayHasKey('complete', $state_options);
    $this->assertArrayHasKey('canceled', $state_options);

    $this->assertTrue($this->registrationManager->hasRegistrationField($entity_type));
    $this->assertTrue($this->registrationManager->hasRegistrationField($entity_type, 'event'));
    $user_entity_type = $this->entityTypeManager->getDefinition('user');
    $this->assertFalse($this->registrationManager->hasRegistrationField($user_entity_type));

    /** @var \Drupal\user\UserInterface $user */
    $user = $this->entityTypeManager->getStorage('user')->load(1);
    $this->assertTrue($this->registrationManager->userHasRegistrations($user));
    $this->assertFalse($this->registrationManager->userHasRegistrations($account));
  }

}
