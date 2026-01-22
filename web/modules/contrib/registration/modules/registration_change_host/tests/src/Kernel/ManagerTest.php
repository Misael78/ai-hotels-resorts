<?php

namespace Drupal\Tests\registration_change_host\Kernel;

use Drupal\Core\Entity\EntityInterface;
use Drupal\node\Entity\Node;

/**
 * Tests the RegistrationChangeHostManager class.
 *
 * @coversDefaultClass \Drupal\registration_change_host\RegistrationChangeHostManager
 *
 * @group registration
 * @group registration_change_host
 */
class ManagerTest extends RegistrationChangeHostKernelTestBase {

  /**
   * The registration change host manager.
   *
   * @var \Drupal\registration_change_host\RegistrationChangeHostManagerInterface
   */
  protected $registrationChangeHostManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->registrationChangeHostManager = $this->container->get('registration_change_host.manager');
  }

  /**
   * Test the current host's behavior as a possible host.
   *
   * @covers ::getPossibleHosts
   */
  public function testCurrentHostAsPossibleHost() {
    // The current host should always be possible even
    // if the event subscribers do not return it.
    $this->originalHostNode->set('host_possible', 'never')->save();
    \Drupal::entityTypeManager()->getHandler('registration', 'access')->resetCache();
    // Use the anonymous user so to check current host possibility is not
    // dependent on permissions.
    $this->setCurrentUser($this->anonymousUser);
    $hosts = $this->registrationChangeHostManager->getPossibleHosts($this->registration)->getHosts();
    $this->assertCount(1, $hosts, "Exactly 1 host should be found");
    // Current host is possible but unavailable by default.
    $this->assertHostPossible($this->originalHostNode, $hosts, FALSE);

    // The unavailability is not a consequence of the anonymous user's lack
    // of permissions, it's true for admin user as well.
    $this->setCurrentUser($this->adminUser);
    $hosts = $this->registrationChangeHostManager->getPossibleHosts($this->registration)->getHosts();
    $this->assertCount(1, $hosts, "Exactly 1 host should be found");
    $this->assertHostPossible($this->originalHostNode, $hosts, FALSE);
  }

  /**
   * Assert that it is or is not allowed to change a registration to a host.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The possible host to change to.
   * @param array $hosts
   *   An array of discovered possible hosts.
   * @param bool $is_expected_available
   *   Whether or not to expect it to be available.
   */
  protected function assertHostPossible(EntityInterface $entity, array $hosts, bool $is_expected_available) {
    $type_id = $entity->getEntityTypeId();
    $id = $entity->id();
    $label = $entity->label();
    $key = "$type_id:$id";
    $this->assertArrayHasKey($key, $hosts, "The host '$label' ({$key}) is not present in the hosts: " . print_r(array_keys($hosts), TRUE));
    /** @var \Drupal\registration_change_host\PossibleHostEntityInterface $host */
    $host = $hosts[$key];
    $should_message = $is_expected_available ? 'should' : 'should not';
    $reason = $host->isAvailable() ? '' : (string) $host->isAvailable(TRUE)->getReason();
    $this->assertSame($is_expected_available, $host->isAvailable(), "The host '$label' ($key) $should_message be available. $reason");
  }

  /**
   * Test getting possible posts for a registration.
   *
   * @covers ::getPossibleHosts
   */
  public function testPossibleHosts() {
    // This host is included by the test event subscriber.
    $hostNode2 = Node::create([
      'type' => 'conference',
      'title' => 'possible available conference',
      'host_violation' => 'NONE',
      'host_possible' => 'always',
    ]);
    $hostNode2->save();

    // This host is not included by the test event subscriber.
    $hostNode3 = Node::create([
      'type' => 'conference',
      'title' => 'impossible conference',
      'host_violation' => 'NONE',
      'host_possible' => 'never',
    ]);
    $hostNode3->save();

    // This host is added by the subscriber despite being unavailable.
    $hostNode4 = Node::create([
      'type' => 'conference',
      'title' => 'unavailable conference',
      'host_violation' => 'some_cause',
      'host_possible' => 'always',
    ]);
    $hostNode4->save();

    // This host is considered by the test event subscriber but set as
    // unavailable and so rejected by ::addHostIfAvailable().
    $hostNode5 = Node::create([
      'type' => 'conference',
      'title' => 'unavailable conference',
      'host_violation' => 'some_cause',
      'host_possible' => 'if_available',
    ]);
    $hostNode5->save();

    // This host is included by the test event subscriber despite being a
    // different bundle.
    $hostNode6 = Node::create([
      'type' => 'event',
      'title' => 'possible available event',
      'host_violation' => 'NONE',
      'host_possible' => 'if_available',
    ]);
    $hostNode6->save();

    // This host is included by the test event subscriber only if user has
    // permissions.
    $hostNode7 = Node::create([
      'type' => 'conference',
      'title' => 'conditional conference',
      'host_possible' => 'if_available',
    ]);
    $hostNode7->save();

    $access_handler = $this->entityTypeManager->getAccessControlHandler('registration');

    // Try as admin.
    // The non-current hosts are available as admin
    // has permission to create registrations of appropriate type.
    $this->assertTrue($this->adminUser->hasPermission('administer registration'));
    $this->assertTrue($access_handler->createAccess('event', $this->adminUser));
    $this->setCurrentUser($this->adminUser);
    $hosts = $this->registrationChangeHostManager->getPossibleHosts($this->registration)->getHosts();
    // Original node + 4 possible new nodes (2 impossible) = 5 nodes.
    $this->assertCount(5, $hosts, "Exactly 5 hosts should be found");
    // The current host is not available by default.
    $this->assertHostPossible($this->originalHostNode, $hosts, FALSE);
    $this->assertHostPossible($hostNode2, $hosts, TRUE);
    $this->assertHostPossible($hostNode4, $hosts, FALSE);
    $this->assertHostPossible($hostNode6, $hosts, TRUE);
    $this->assertHostPossible($hostNode7, $hosts, TRUE);

    // Try as staff.
    // The non-current hosts are available as registrant
    // has permission to create registrations of appropriate type.
    $this->assertTrue($this->staffUser->hasPermission('create event registration other users'));
    $this->assertTrue($this->staffUser->hasPermission('create conference registration other users'));
    $this->assertTrue($this->staffUser->hasPermission('change host any registration'));
    $this->assertTrue($this->staffUser->hasPermission('update any conference registration'));
    $this->setCurrentUser($this->staffUser);
    $hosts = $this->registrationChangeHostManager->getPossibleHosts($this->registration)->getHosts();
    // Original node + 4 possible new nodes (2 impossible) = 5 nodes.
    $this->assertCount(5, $hosts, "Exactly 5 hosts should be found");
    // The current host is not available by default.
    $this->assertHostPossible($this->originalHostNode, $hosts, FALSE);
    $this->assertHostPossible($hostNode2, $hosts, TRUE);
    $this->assertHostPossible($hostNode4, $hosts, FALSE);
    $this->assertHostPossible($hostNode6, $hosts, TRUE);
    $this->assertHostPossible($hostNode7, $hosts, TRUE);

    // Try as registrant.
    // The non-current hosts are available as registrant
    // has permission to create registrations of appropriate type.
    $this->assertTrue($this->registrantUser->hasPermission('create event registration self'));
    $this->assertTrue($this->registrantUser->hasPermission('create conference registration self'));
    $this->assertTrue($this->registrantUser->hasPermission('change host own registration'));
    $this->assertTrue($this->registrantUser->hasPermission('update own conference registration'));
    $this->setCurrentUser($this->registrantUser);
    $hosts = $this->registrationChangeHostManager->getPossibleHosts($this->registration)->getHosts();
    // Original node + 4 possible new nodes (2 impossible) = 4 nodes.
    $this->assertCount(5, $hosts, "Exactly 5 hosts should be found");
    $this->assertHostPossible($this->originalHostNode, $hosts, FALSE);
    $this->assertHostPossible($hostNode2, $hosts, TRUE);
    $this->assertHostPossible($hostNode4, $hosts, FALSE);
    $this->assertHostPossible($hostNode6, $hosts, TRUE);
    $this->assertHostPossible($hostNode7, $hosts, TRUE);

    // Try as anonymous user.
    // The non-current hosts are unavailable as anonymous user
    // lacks permission to change host.
    $this->assertFalse($this->anonymousUser->hasPermission('create event registration other users'));
    $this->assertFalse($this->anonymousUser->hasPermission('create conference registration other users'));
    $this->assertFalse($this->anonymousUser->hasPermission('change host any registration'));
    $this->assertFalse($this->anonymousUser->hasPermission('update any conference registration'));
    $this->setCurrentUser($this->anonymousUser);
    $hosts = $this->registrationChangeHostManager->getPossibleHosts($this->registration)->getHosts();
    // Nodes 6 & 7 are only added if available but anonymous lacks permission.
    // However, node 6 skips all violations so is available.
    $this->assertCount(4, $hosts, "Exactly 4 hosts should be found");
    $this->assertHostPossible($this->originalHostNode, $hosts, FALSE);
    $this->assertHostPossible($hostNode2, $hosts, TRUE);
    $this->assertHostPossible($hostNode4, $hosts, FALSE);
    $this->assertHostPossible($hostNode6, $hosts, TRUE);
  }

  /**
   * Test changing a registration host to a host of the same bundle.
   *
   * @covers ::changeHost
   */
  public function testChangeHostSameType() {
    $hostNode2 = Node::create([
      'type' => 'conference',
      'title' => 'possible conference',
      'host_possible' => 'always',
    ]);
    $hostNode2->save();

    $this->assertTrue($this->registration->getHostEntity()->id() == $this->originalHostNode->id());
    $this->assertTrue($this->registration->bundle() === 'conference');
    $this->registration->set('count', 2)->save();
    $this->assertEquals($this->registrantUser->id(), $this->registration->getUserId());

    $old_host_entity = $this->registration->getHostEntity();
    $registration = $this->registrationChangeHostManager->changeHost($this->registration, 'node', $hostNode2->id());
    $this->assertEquals($this->registration->id(), $registration->id(), "Cloned registration should have same id as original.");
    // @todo Cannot test getHostEntity() because it is stale.
    $this->assertTrue($registration->bundle() === 'conference');
    $this->assertEquals(2, $registration->get('count')->value, "Count value should persist through host change.");
    $this->assertEquals($this->registrantUser->id(), $registration->getUserId(), "Registrant id should persist through host change.");

    $this->registrationChangeHostManager->saveChangedHost($registration, $old_host_entity, fn() => $registration->save());
    $storage = $this->entityTypeManager->getStorage('registration');
    /** @var \Drupal\registration\Entity\RegistrationInterface $registration */
    $registration = $storage->loadUnchanged($registration->id());

    $this->assertTrue($registration->getHostEntity()->id() == $hostNode2->id());
    $this->assertTrue($registration->bundle() === 'conference');
    $this->assertEquals(2, $registration->get('count')->value, "Count value should persist through host change.");
    $this->assertEquals($this->registrantUser->id(), $registration->getUserId(), "Registrant id should persist through host change.");
  }

  /**
   * Test changing a registration host to a host of a different bundle.
   *
   * @covers ::changeHost
   */
  public function testChangeHostDifferentType() {
    $hostNode2 = Node::create([
      'type' => 'event',
      'title' => 'possible event',
      'host_possible' => 'always',
    ]);
    $hostNode2->save();

    $this->assertTrue($this->registration->getHostEntity()->id() == $this->originalHostNode->id());
    $this->assertTrue($this->registration->bundle() === 'conference');
    $this->registration->set('count', 2)->save();
    $this->assertEquals($this->registrantUser->id(), $this->registration->getUserId());

    $old_host_entity = $this->registration->getHostEntity();
    $registration = $this->registrationChangeHostManager->changeHost($this->registration, 'node', $hostNode2->id());
    $this->assertEquals($this->registration->id(), $registration->id(), "Cloned registration should have same id as original.");
    $this->assertEquals($hostNode2->id(), $registration->getHostEntity()->id());
    $this->assertSame('event', $registration->bundle());
    $this->assertEquals(2, $registration->get('count')->value, "Count value should persist despite host change.");
    $this->assertEquals($this->registrantUser->id(), $registration->getUserId(), "Registrant id should persist through host change.");

    $this->registrationChangeHostManager->saveChangedHost($registration, $old_host_entity, fn() => $registration->save());
    $storage = $this->entityTypeManager->getStorage('registration');
    /** @var \Drupal\registration\Entity\RegistrationInterface $registration */
    $registration = $storage->loadUnchanged($registration->id());

    $this->assertEquals($hostNode2->id(), $registration->getHostEntity()->id());
    $this->assertSame('event', $registration->bundle());
    $this->assertEquals(2, $registration->get('count')->value, "Count value should persist despite host change.");
    $this->assertEquals($this->registrantUser->id(), $registration->getUserId(), "Registrant id should persist through host change.");
  }

  /**
   * Test changing a registration host without modifying passed-in object.
   *
   * @covers ::changeHost
   */
  public function testAlwaysClone() {
    $hostNode2 = Node::create([
      'type' => 'conference',
      'title' => 'possible conference',
      'host_possible' => 'always',
    ]);
    $hostNode2->save();

    $registration = $this->registrationChangeHostManager->changeHost($this->registration, 'node', $hostNode2->id(), TRUE);
    $this->assertEquals($this->originalHostNode->id(), $this->registration->getHostEntity()->id());
    $this->assertEquals($hostNode2->id(), $registration->getHostEntity()->id());
  }

  /**
   * Test that the save operation is rolled back if the save fails.
   *
   * @covers ::saveChangedHost
   */
  public function testSaveChangedHostRollback(): void {
    $id = $this->registration->id();

    // Test without rollback.
    // saveChangedHost() doesn't actually care if the host is changed.
    $registration = $this->createRegistration($this->originalHostNode);
    $registration->set('registration_id', $id);
    // Set the count so we can check that this object is saved.
    $registration->set('count', 2);
    $old_host_entity = $registration->getHostEntity();

    // saveChangedHost() replaces any existing registration if the passed-in
    // registration is new but has the same id.
    $this->registrationChangeHostManager->saveChangedHost($registration, $old_host_entity, fn() => $registration->save());

    $storage = $this->entityTypeManager->getStorage('registration');
    $registration = $storage->loadUnchanged($id);
    $this->assertFalse($registration->isNew());
    $this->assertSame($id, $registration->id());
    $this->assertEquals(2, $registration->get('count')->value);

    // Test rollback.
    \Drupal::state()->set('registration_change_host_test.throw_exception_on_save', TRUE);

    $registration = $this->createRegistration($this->originalHostNode);
    $registration->set('registration_id', $id);
    $registration->set('count', 3);
    $old_host_entity = $registration->getHostEntity();

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Test exception in hook_registration_presave');

    $this->registrationChangeHostManager->saveChangedHost($registration, $old_host_entity, fn() => $registration->save());
    /** @var \Drupal\registration\Entity\RegistrationInterface $registration */
    $registration = $this->reloadEntity($registration);

    // Verify the original registration still exists unchanged.
    $this->assertFalse($registration->isNew());
    $this->assertSame($id, $registration->id());
    $this->assertEquals(2, $registration->get('count')->value, 'Original registration should remain unchanged after failed save attempt');
  }

  /**
   * @covers ::isDataLostWhenHostChanges
   */
  public function testLosesDataWhenHostChanges() {
    // Create a new host that uses the same registration type
    // as the original host.
    $conference = Node::create([
      'type' => 'conference',
      'title' => 'conference',
    ]);
    $conference->save();
    $handler = $this->entityTypeManager->getHandler('node', 'registration_host_entity');
    $conference_host = $handler->createHostEntity($conference);
    $this->assertSame($conference_host->getRegistrationType()->id(), $this->registration->getHostEntity()->getRegistrationType()->id());

    $loses_data = $this->registrationChangeHostManager->isDataLostWhenHostChanges($this->registration, 'node', $conference->id());
    $this->assertFalse($loses_data, "No data lost when changing to new host that uses same registration type");

    // Create a new host that uses a different registration type.
    $event = Node::create([
      'type' => 'event',
      'title' => 'event',
    ]);
    $event->save();
    $event_host = $handler->createHostEntity($event);
    $this->assertNotSame($event_host->getRegistrationType()->id(), $this->registration->getHostEntity()->getRegistrationType()->id());

    $loses_data = $this->registrationChangeHostManager->isDataLostWhenHostChanges($this->registration, 'node', $event->id());
    $this->assertFalse($loses_data, "No data lost because incompatible field on registration is empty.");

    $this->registration->set('count', 2);
    $this->registration->save();
    $loses_data = $this->registrationChangeHostManager->isDataLostWhenHostChanges($this->registration, 'node', $event->id());
    $this->assertFalse($loses_data, "No data lost because despite non-default value as count field is shared across bundles.");

    $this->registration->set('conference_text', 'some text');
    $this->registration->save();
    $loses_data = $this->registrationChangeHostManager->isDataLostWhenHostChanges($this->registration, 'node', $event->id());
    $this->assertTrue($loses_data, "Data lost because incompatible field on registration is not empty.");
  }

}
