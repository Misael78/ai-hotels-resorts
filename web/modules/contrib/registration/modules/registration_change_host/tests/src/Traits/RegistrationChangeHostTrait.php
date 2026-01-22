<?php

namespace Drupal\Tests\registration_change_host\Traits;

use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\registration\Entity\Registration;
use Drupal\registration\Entity\RegistrationInterface;
use Drupal\registration\Entity\RegistrationType;
use Drupal\registration\Entity\RegistrationTypeInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * Defines a trait for creating a test registration and saving it.
 */
trait RegistrationChangeHostTrait {

  /**
   * A registration type for an event.
   *
   * @var \Drupal\registration\Entity\RegistrationTypeInterface
   */
  protected RegistrationTypeInterface $eventRegType;

  /**
   * A registration type for a conference.
   *
   * @var \Drupal\registration\Entity\RegistrationTypeInterface
   */
  protected RegistrationTypeInterface $conferenceRegType;

  /**
   * A registration.
   *
   * @var \Drupal\registration\Entity\RegistrationInterface
   */
  protected RegistrationInterface $registration;

  /**
   * The registration's original host node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected NodeInterface $originalHostNode;

  /**
   * A staff user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $staffUser;

  /**
   * A staff user without update permission.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $staffUserWithoutUpdate;

  /**
   * An admin user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $adminUser;

  /**
   * The registrant user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $registrantUser;

  /**
   * The anonymous user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $anonymousUser;

  /**
   * Setup for registration change host tests.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function registrationChangeHostSetUp() {
    // Create a node type an a registration type called 'conference',
    // and a node type and registration type called 'event'.
    // registration_change_host_test_entity_bundle_field_info() will
    // set the registration type for the corresponding node type.
    if (!NodeType::load('conference')) {
      $node_type = NodeType::create([
        'type' => 'conference',
        'name' => 'Conference',
      ]);
      $node_type->save();
    }
    if (!NodeType::load('event')) {
      $node_type = NodeType::create([
        'type' => 'event',
        'name' => 'Event',
      ]);
      $node_type->save();
    }

    $registration_type = RegistrationType::create([
      'id' => 'event',
      'label' => 'Event',
      'workflow' => 'registration',
      'defaultState' => 'pending',
      'heldExpireTime' => 1,
      'heldExpireState' => 'canceled',
    ]);
    $registration_type->save();
    $this->eventRegType = RegistrationType::load('event');
    $this->conferenceRegType = RegistrationType::load('conference');

    $this->anonymousUser = User::getAnonymousUser();
    $staff_permissions = [
      'create event registration other users',
      'create conference registration other users',
      'update any event registration',
      'update any conference registration',
      'change host any registration',
    ];
    $this->staffUser = $this->drupalCreateUser($staff_permissions);
    $staff_permissions_without_update = [
      'create event registration other users',
      'create conference registration other users',
      'change host any registration',
    ];
    $this->staffUserWithoutUpdate = $this->drupalCreateUser($staff_permissions_without_update);
    $this->adminUser = $this->drupalCreateUser([
      'administer registration',
      // Having admin permissions is not enough to register.
      'create event registration other users',
      'create conference registration other users',
    ]);
    $registrant_permissions = [
      'create event registration self',
      'create conference registration self',
      'update own event registration',
      'update own conference registration',
      'change host own registration',
    ];
    $this->registrantUser = $this->drupalCreateUser($registrant_permissions);

    $this->originalHostNode = Node::create([
      'type' => 'conference',
      'title' => 'original conference',
    ]);
    $this->originalHostNode->save();

    $this->registration = $this->createRegistration($this->originalHostNode);
    $this->registration->set('user_uid', $this->registrantUser);
    $this->registration->set('author_uid', $this->registrantUser);
    $this->registration->save();
  }

  /**
   * Creates a registration for a given node host entity.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   *
   * @return \Drupal\registration\Entity\RegistrationInterface
   *   The created (unsaved) registration.
   */
  protected function createRegistration(NodeInterface $node): RegistrationInterface {
    $handler = $this->entityTypeManager->getHandler('node', 'registration_host_entity');
    $host = $handler->createHostEntity($node);
    return Registration::create([
      'workflow' => 'registration',
      'state' => 'pending',
      'type' => $host->getRegistrationType()->id(),
      'entity_type_id' => 'node',
      'entity_id' => $node->id(),
    ]);
  }

}
