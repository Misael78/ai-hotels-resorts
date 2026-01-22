<?php

namespace Drupal\Tests\registration\Kernel\Entity;

use Drupal\Tests\registration\Kernel\RegistrationKernelTestBase;
use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\Tests\registration\Traits\RegistrationCreationTrait;
use Drupal\registration\Entity\RegistrationInterface;
use Drupal\user\UserInterface;

/**
 * Tests the Registration entity.
 *
 * @coversDefaultClass \Drupal\registration\Entity\Registration
 *
 * @group registration
 */
class RegistrationTest extends RegistrationKernelTestBase {

  use NodeCreationTrait;
  use RegistrationCreationTrait;

  /**
   * A sample user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $user;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $user = $this->createUser(['administer registration']);
    $user = $this->reloadEntity($user);
    /** @var \Drupal\user\UserInterface $user */
    $this->user = $user;
    $this->setCurrentUser($user);
  }

  /**
   * @covers ::label
   * @covers ::getAnonymousEmail
   * @covers ::getAuthor
   * @covers ::getAuthorDisplayName
   * @covers ::getEmail
   * @covers ::getHostEntity
   * @covers ::getHostEntityId
   * @covers ::getHostEntityTypeId
   * @covers ::getHostEntityTypeLabel
   * @covers ::getLangcode
   * @covers ::getRegistrantType
   * @covers ::getSpacesReserved
   * @covers ::getType
   * @covers ::getUser
   * @covers ::getUserId
   * @covers ::getWorkflow
   * @covers ::getState
   * @covers ::getCompletedTime
   * @covers ::getCreatedTime
   * @covers ::setCreatedTime
   * @covers ::isActive
   * @covers ::isCanceled
   * @covers ::isComplete
   * @covers ::isHeld
   * @covers ::requiresCapacityCheck
   */
  public function testRegistration() {
    $node = $this->createAndSaveNode();
    $node = $this->reloadEntity($node);

    /** @var \Drupal\node\NodeInterface $node */
    $registration = $this->createRegistration($node);
    $registration->set('user_uid', $this->user->id());
    $registration->save();

    $this->assertEquals('Registration #1 for My event', $registration->label());
    $this->assertEquals('', $registration->getAnonymousEmail());
    $this->assertEquals($this->user, $registration->getAuthor());
    $this->assertEquals($this->user->getDisplayName(), $registration->getAuthorDisplayName());
    $this->assertEquals($this->user->getEmail(), $registration->getEmail());
    $this->assertEquals($node, $registration->getHostEntity()->getEntity());
    $this->assertEquals($node->id(), $registration->getHostEntityId());
    $this->assertEquals($node->getEntityTypeId(), $registration->getHostEntityTypeId());
    $this->assertEquals('Event', $registration->getHostEntityTypeLabel());
    $this->assertEquals('en', $registration->getLangcode());
    $this->assertEquals(RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_ME, $registration->getRegistrantType($this->user));
    $this->assertEquals(1, $registration->getSpacesReserved());
    $this->assertEquals($this->regType->id(), $registration->getType()->id());
    $this->assertEquals($this->user, $registration->getUser());
    $this->assertEquals($this->user->id(), $registration->getUserId());
    $this->assertEquals($this->regType->getWorkflow()->id(), $registration->getWorkflow()->id());
    $this->assertEquals($this->regType->getDefaultState(), $registration->getState()->id());
    $this->assertTrue($registration->isActive());
    $this->assertFalse($registration->isCanceled());
    $this->assertFalse($registration->isComplete());
    $this->assertNull($registration->getCompletedTime());
    $this->assertFalse($registration->isHeld());
    $this->assertFalse($registration->requiresCapacityCheck());

    $registration->set('state', 'canceled');
    $this->assertFalse($registration->requiresCapacityCheck());
    $this->assertTrue($registration->requiresCapacityCheck(TRUE));
    $registration->save();
    $this->assertFalse($registration->requiresCapacityCheck());
    $this->assertFalse($registration->requiresCapacityCheck(TRUE));
    $registration->set('state', 'pending');
    $registration->save();
    $registration->set('count', 2);
    $this->assertTrue($registration->requiresCapacityCheck());
    $this->assertTrue($registration->requiresCapacityCheck(TRUE));
    $registration->save();
    $this->assertFalse($registration->requiresCapacityCheck());
    $this->assertFalse($registration->requiresCapacityCheck(TRUE));
    $registration->set('count', 1);

    $registration->setCreatedTime(635879700);
    $registration->save();
    $this->assertEquals(635879700, $registration->getCreatedTime());

    $registration->set('state', 'complete');
    $registration->save();
    $this->assertTrue($registration->isComplete());
    $this->assertEquals(\Drupal::time()->getRequestTime(), $registration->getCompletedTime());

    $registration = $this->createRegistration($node);
    $registration->set('anon_mail', 'admin@example.org');
    $registration->save();
    $this->assertEquals(1, $registration->getSpacesReserved());
    $this->assertEquals('admin@example.org', $registration->getAnonymousEmail());

    // A registration that starts out complete should have a completed time.
    $registration = $this->createRegistration($node);
    $registration->set('user_uid', $this->user->id());
    $registration->set('state', 'complete');
    $registration->save();
    $this->assertTrue($registration->isComplete());
    $this->assertEquals(\Drupal::time()->getRequestTime(), $registration->getCompletedTime());

    // A registration completed in a presave hook should have a completed time.
    // @see registration_test_registration_presave().
    $registration = $this->createRegistration($node);
    $registration->set('anon_mail', 'trigger_presave_hook@example.org');
    $registration->save();
    $this->assertTrue($registration->isComplete());
    $this->assertEquals(\Drupal::time()->getRequestTime(), $registration->getCompletedTime());

    // Delete the host entity.
    $node->delete();
    $registration = $this->reloadEntity($registration);
    $this->assertNull($registration->getHostEntity());
    $this->assertNull($registration->getHostEntityTypeLabel());
  }

  /**
   * @covers ::isNewToHost
   */
  public function testIsNewToHost(): void {
    $node = $this->createAndSaveNode();
    $node = $this->reloadEntity($node);
    $node2 = $this->createAndSaveNode();

    $storage = $this->entityTypeManager->getStorage('registration');
    $registration = $storage->create(['type' => 'conference']);
    $this->assertTrue($registration->isNewToHost());

    $registration = $this->createRegistration($node);
    $this->assertTrue($registration->isNewToHost());
    $registration->set('entity_id', $node2->id());
    $this->assertTrue($registration->isNewToHost());

    $registration->save();
    $this->assertFalse($registration->isNewToHost());
    $registration->set('entity_id', $node->id());
    $this->assertTrue($registration->isNewToHost());
    $registration->set('entity_id', $node2->id());
    $this->assertFalse($registration->isNewToHost());
    $registration->set('entity_id', $node->id());
    $registration->save();
    $this->assertFalse($registration->isNewToHost());

    $registration->set('entity_type_id', 'user');
    $this->assertTrue($registration->isNewToHost());
    $registration->set('entity_type_id', 'node');
    $this->assertFalse($registration->isNewToHost());
    $registration->set('entity_type_id', 'user');
    $registration->save();
    $this->assertFalse($registration->isNewToHost());
  }

  /**
   * @covers ::getHostEntity
   */
  public function testGetHostEntity(): void {
    $node = $this->createAndSaveNode();
    $storage = $this->entityTypeManager->getStorage('registration');

    /** @var \Drupal\registration\Entity\RegistrationInterface */
    $registration = $storage->create([
      'type' => 'conference',
      'entity_id' => $node->id(),
      'entity_type_id' => 'node',
    ]);

    $this->assertEquals($node->id(), $registration->getHostEntity()->id());
    $this->assertSame('node', $registration->getHostEntity()->getEntityTypeId());

    // The host updates after being changed.
    $node2 = $this->createAndSaveNode();
    $registration->set('entity_id', $node2->id());
    $this->assertEquals($node2->id(), $registration->getHostEntity()->id());
    $this->assertSame('node', $registration->getHostEntity()->getEntityTypeId());

    // The host is still correct after the change is saved.
    $registration->save();
    $this->assertEquals($node2->id(), $registration->getHostEntity()->id());
    $this->assertSame('node', $registration->getHostEntity()->getEntityTypeId());
    $registration = $this->reloadEntity($registration);
    $this->assertEquals($node2->id(), $registration->getHostEntity()->id());
    $this->assertSame('node', $registration->getHostEntity()->getEntityTypeId());

    // The host updates after being changed following a save.
    $registration->set('entity_id', $node->id());
    $this->assertEquals($node->id(), $registration->getHostEntity()->id());
    $this->assertSame('node', $registration->getHostEntity()->getEntityTypeId());

    // The host is null if set to an invalid entity type ID.
    $registration->set('entity_type_id', 'cruft');
    $this->assertNull($registration->getHostEntity());
    $this->assertSame('cruft', $registration->getHostEntityTypeId());
    $registration->save();
    $this->assertSame('cruft', $registration->getHostEntityTypeId());
    $this->assertNull($registration->getHostEntity());
    $registration = $this->reloadEntity($registration);
    $this->assertSame('cruft', $registration->getHostEntityTypeId());
    $this->assertNull($registration->getHostEntity());

    // The host is null if set to an invalid entity ID.
    $registration->set('entity_type_id', 'node');
    $registration->set('entity_id', 999);
    $this->assertNull($registration->getHostEntity());
    $this->assertEquals(999, $registration->getHostEntityId());
    $registration->save();
    $this->assertEquals(999, $registration->getHostEntityId());
    $this->assertNull($registration->getHostEntity());
    $registration = $this->reloadEntity($registration);
    $this->assertEquals(999, $registration->getHostEntityId());
    $this->assertNull($registration->getHostEntity());
  }

}
