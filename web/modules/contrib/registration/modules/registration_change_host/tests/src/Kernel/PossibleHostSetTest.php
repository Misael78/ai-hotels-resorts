<?php

namespace Drupal\Tests\registration_change_host\Kernel;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultReasonInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\node\Entity\Node;
use Drupal\registration_change_host\PossibleHostEntity;
use Drupal\registration_change_host\PossibleHostSet;

/**
 * Tests the possible host set.
 *
 * @coversDefaultClass \Drupal\registration_change_host\PossibleHostSet
 *
 * @group registration
 * @group registration_change_host
 */
class PossibleHostSetTest extends RegistrationChangeHostKernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'entity_test',
  ];

  /**
   * A new host entity with no registrations or settings specified.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $newHost;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->newHost = Node::create([
      'type' => 'event',
      'title' => 'possible event',
    ]);
    $this->newHost->save();
    $this->registrantUser->set('name', 'Fred')->save();
  }

  /**
   * Test the PossibleHostEntity::isCurrent() method.
   */
  public function testIsCurrent() {
    $set = $this->createSetWithHost($this->newHost);
    $current_host = $set->getHost($this->originalHostNode);
    $this->assertTrue($current_host->isCurrent());
    $possible_host = $set->getHost($this->newHost);
    $this->assertFalse($possible_host->isCurrent());
  }

  /**
   * Test access on possible host.
   */
  public function testPossibleHostAccessPermission() {
    $anonymous_user = $this->drupalCreateUser();
    $reason = "Not allowed.";
    $this->setCurrentUser($anonymous_user);
    $this->assertPossibleHostAvailable($this->newHost, FALSE, $reason);

    $registerable_user = $this->drupalCreateUser(['create registration']);
    $this->setCurrentUser($registerable_user);
    $this->assertPossibleHostAvailable($this->newHost, TRUE, '');
  }

  /**
   * Test default access on host already registered for.
   */
  public function testPossibleHostAccessIfRegistered() {
    $this->setHostSetting($this->newHost, 'multiple_registrations', FALSE);
    // The registrant is already registered for the new host.
    $other_registration = $this->createRegistration($this->newHost);
    $other_registration->set('user_uid', $this->registrantUser);
    $other_registration->save();
    $reason = "Already registered.";
    $this->setCurrentUser($this->registrantUser);
    $this->assertPossibleHostAvailable($this->newHost, FALSE, $reason);
  }

  /**
   * Test default access on host with no capacity.
   */
  public function testPossibleHostAccessIfNoCapacity() {
    $this->setHostSetting($this->newHost, 'capacity', 1);
    // The 1 available spot is already filled.
    $this->createRegistration($this->newHost)->save();
    $reason = "No room.";
    $this->setCurrentUser($this->registrantUser);
    $this->assertPossibleHostAvailable($this->newHost, FALSE, $reason);
  }

  /**
   * Test default access on disabled host.
   */
  public function testPossibleHostAccessWithDisabledHost() {
    $this->setHostSetting($this->newHost, 'status', FALSE);
    $reason = "Disabled.";
    $this->setCurrentUser($this->registrantUser);
    $this->assertPossibleHostAvailable($this->newHost, FALSE, $reason);
  }

  /**
   * Test admins can access disabled hosts.
   */
  public function testPossibleHostAccessForAdminsIfDisabled() {
    $this->setHostSetting($this->newHost, 'status', FALSE);
    $reason = "Disabled.";
    $this->setCurrentUser($this->adminUser);
    $this->assertPossibleHostAvailable($this->newHost, FALSE, $reason);
  }

  /**
   * Test hosts that are not configured for registration are not available.
   */
  public function testPossibleHostAccessForNotConfiguredHost() {
    $not_configured_host = EntityTest::create(['name' => 'Not configured']);
    $reason = "Disabled.";
    $this->setCurrentUser($this->adminUser);
    $this->assertPossibleHostAvailable($not_configured_host, FALSE, $reason);
  }

  /**
   * Test possible host access depends on new host not current host.
   */
  public function testPossibleHostAccessViaNewHost() {
    $this->assertSame('conference', $this->originalHostNode->bundle());
    $this->assertSame('event', $this->newHost->bundle());
    $original_admin = $this->drupalCreateUser([
      "administer conference registration",
      "create conference registration other users",
    ]);
    $this->setCurrentUser($original_admin);
    $reason = "Not allowed.";
    $this->assertPossibleHostAvailable($this->newHost, FALSE, $reason);

    $new_admin = $this->drupalCreateUser(["administer event registration", "create event registration other users"]);
    $this->setCurrentUser($new_admin);
    $this->assertPossibleHostAvailable($this->newHost, TRUE, '');
  }

  /**
   * Test that the data loss validation works.
   *
   * @covers \Drupal\registration_change_host\PossibleHostEntity::isAvailable()
   */
  public function testDataLossValidation() {
    // Create a new host that uses a different registration type.
    $event = Node::create([
      'type' => 'event',
      'title' => 'event',
      'host_possible' => 'always',
    ]);
    $event->save();
    $handler = $this->entityTypeManager->getHandler('node', 'registration_host_entity');
    $event_host = $handler->createHostEntity($event);
    $this->assertNotSame($event_host->getRegistrationType()->id(), $this->registration->getHostEntity()->getRegistrationType()->id());

    $registration_type = $this->registration->getHostEntity()->getRegistrationType();
    $this->assertNotTrue($registration_type->getThirdPartySetting('registration_change_host', 'data_loss_allowed'));

    // By default there is no data loss so the new host is allowed despite
    // using a different registration type.
    $this->setCurrentUser($this->adminUser);
    $set = $this->createSetWithHost($event);
    $event_possible_host = $set->getHost($event);
    $this->assertInstanceOf(PossibleHostEntity::class, $event_possible_host);
    $this->assertTrue($event_possible_host->isAvailable(TRUE)->isValid());

    // If the conference text field is set then there is data loss.
    $this->registration->set('conference_text', 'some text');
    $this->registration->save();
    $manager = \Drupal::service('registration_change_host.manager');
    $this->assertTrue($manager->isDataLostWhenHostChanges($this->registration, 'node', $event->id()));

    $set = $this->createSetWithHost($event);
    $event_possible_host = $set->getHost($event);
    $this->assertInstanceOf(PossibleHostEntity::class, $event_possible_host);
    $validation_result = $event_possible_host->isAvailable(TRUE);
    $this->assertFalse($validation_result->isValid());
    $this->assertCount(1, $validation_result->getViolations());
    $this->assertSame('data_loss', $validation_result->getViolations()[0]->getCode());

    // It's possible to opt-in to allowing data loss.
    $registration_type->setThirdPartySetting('registration_change_host', 'data_loss_allowed', TRUE);
    $set = $this->createSetWithHost($event);
    $event_possible_host = $set->getHost($event);
    $this->assertInstanceOf(PossibleHostEntity::class, $event_possible_host);
    $validation_result = $event_possible_host->isAvailable(TRUE);
    $this->assertFalse($validation_result->isValid());
    $this->assertCount(1, $validation_result->getViolations());
    $this->assertSame('data_loss', $validation_result->getViolations()[0]->getCode());
  }

  /**
   * Assert the possible host default access.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param \Drupal\Core\Access\AccessResult $expected
   *   The expected access result.
   * @param string $reason
   *   The expected reason.
   */
  protected function assertPossibleHostAvailable(EntityInterface $entity, bool $expected, string $reason): void {
    $set = $this->createSetWithHost($entity);
    $added_host = $set->getHost($entity);
    $this->assertInstanceOf(PossibleHostEntity::class, $added_host);
    $reason = $added_host->isAvailable() ? '' : (string) $added_host->isAvailable(TRUE)->getReason();
    $this->assertSame($expected, $added_host->isAvailable(), $reason);
    // Cannot use assertEquals() as it gives a very arcane error that is likely
    // due to some upstream bug.
    $this->assertStringContainsString($reason, $reason);
  }

  /**
   * Create a possible host set.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return \Drupal\registration_change_host\PossibleHostSet
   *   A possible host set.
   */
  protected function createSetWithHost(EntityInterface $entity): PossibleHostSet {
    $set = new PossibleHostSet($this->registration);
    $possible_host = $set->buildNewPossibleHost($entity);
    $set->addHost($possible_host);
    return $set;
  }

  /**
   * Get a message for asserting the access result.
   *
   * @param \Drupal\Core\Access\AccessResultInterface $access
   *   The access result.
   * @param string $message
   *   A message to display.
   *
   * @return string
   *   The message including access result reason.
   */
  protected function getAccessMessage(AccessResult $access, $message = '') {
    if (!$access->isAllowed()) {
      $message .= " \n" . ($access instanceof AccessResultReasonInterface ? $access->getReason() : 'No reason given, likely indicates a cached access result was returned.');
    }
    return $message;
  }

}
