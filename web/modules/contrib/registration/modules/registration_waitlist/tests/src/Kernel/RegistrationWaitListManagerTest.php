<?php

namespace Drupal\Tests\registration_waitlist\Kernel;

use Drupal\Core\Database\Database;
use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\Tests\registration\Traits\RegistrationCreationTrait;
use Drupal\registration_waitlist\RegistrationWaitListManagerInterface;

/**
 * Tests the RegistrationWaitListManager class.
 *
 * @coversDefaultClass \Drupal\registration_waitlist\RegistrationWaitListManager
 *
 * @group registration
 */
class RegistrationWaitListManagerTest extends RegistrationWaitListKernelTestBase {

  use NodeCreationTrait;
  use RegistrationCreationTrait;

  /**
   * The registration wait list manager.
   *
   * @var \Drupal\registration_waitlist\RegistrationWaitListManagerInterface
   */
  protected RegistrationWaitListManagerInterface $waitListManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->waitListManager = $this->container->get('registration_waitlist.manager');
  }

  /**
   * @covers ::autoFill
   * @covers ::getAutoFillSortFieldOptions
   */
  public function testRegistrationWaitListManager() {
    $node = $this->createAndSaveNode();
    $handler = $this->entityTypeManager->getHandler('node', 'registration_host_entity');
    $host_entity = $handler->createHostEntity($node);
    /** @var \Drupal\registration\RegistrationSettingsStorage $storage */
    $storage = $this->entityTypeManager->getStorage('registration_settings');
    $settings = $storage->loadSettingsForHostEntity($host_entity);

    // Allow multiple registrations per user.
    $settings->set('multiple_registrations', TRUE);
    $settings->save();

    // Fill standard capacity.
    $registration = $this->createRegistration($node);
    $registration->set('author_uid', 1);
    $registration->set('count', 5);
    $registration->save();
    $this->assertFalse($host_entity->hasRoomOffWaitList());
    $this->assertEquals(5, $host_entity->getActiveSpacesReserved());

    // Get sort field options.
    $registration_type = $registration->getType();
    $options = $this->waitListManager->getAutoFillSortFieldOptions($registration_type);
    $this->assertCount(4, $options);
    $this->assertArrayHasKey('created', $options);
    $this->assertArrayHasKey('changed', $options);
    $this->assertArrayHasKey('registration_id', $options);
    $this->assertArrayHasKey('count', $options);

    // Add registrations to the wait list.
    $registration = $this->createRegistration($node);
    $registration->set('author_uid', 1);
    $registration->set('count', 2);
    $registration->save();
    $this->assertEquals(2, $host_entity->getWaitListSpacesReserved());

    $registration = $this->createRegistration($node);
    $registration->set('author_uid', 1);
    $registration->set('count', 4);
    $registration->save();
    $this->assertEquals(6, $host_entity->getWaitListSpacesReserved());

    $registration = $this->createRegistration($node);
    $registration->set('author_uid', 1);
    $registration->set('count', 3);
    $registration->save();
    $this->assertEquals(9, $host_entity->getWaitListSpacesReserved());

    // Increase capacity. Autofill is not enabled.
    $settings->set('capacity', 10);
    $settings->save();
    $this->assertEquals(5, $host_entity->getActiveSpacesReserved());
    $this->assertEquals(9, $host_entity->getWaitListSpacesReserved());

    // Decrease capacity and enable autofill.
    $settings->set('capacity', 5);
    $settings->set('registration_waitlist_autofill', TRUE);
    $settings->set('registration_waitlist_autofill_state', 'complete');
    $settings->save();
    $this->assertEquals(5, $host_entity->getActiveSpacesReserved());
    $this->assertEquals(9, $host_entity->getWaitListSpacesReserved());

    // Increase capacity. Autofill is enabled and fills the available spots.
    $settings->set('capacity', 10);
    $settings->save();
    $this->assertEquals(10, $host_entity->getActiveSpacesReserved());
    $this->assertEquals(4, $host_entity->getWaitListSpacesReserved());
    // Two registrations were autofilled.
    $this->assertTrue($this->loggedRegistrationCountMatches(2, 5));

    // Delete a registration. Autofill is enabled and fills the available spots.
    $registration = $this->entityTypeManager->getStorage('registration')->load(1);
    $registration->delete();
    $this->assertEquals(9, $host_entity->getActiveSpacesReserved());
    $this->assertEquals(0, $host_entity->getWaitListSpacesReserved());
    // One registration was autofilled.
    $this->assertTrue($this->loggedRegistrationCountMatches(1, 4));

    // Fill standard capacity.
    $registration = $this->createRegistration($node);
    $registration->set('author_uid', 1);
    $registration->set('count', 1);
    $registration->save();
    $this->assertFalse($host_entity->hasRoomOffWaitList());
    $this->assertEquals(10, $host_entity->getActiveSpacesReserved());

    // Add registrations to the wait list.
    $registration = $this->createRegistration($node);
    $registration->set('author_uid', 1);
    $registration->set('count', 2);
    $registration->save();
    $this->assertEquals(10, $host_entity->getActiveSpacesReserved());
    $this->assertEquals(2, $host_entity->getWaitListSpacesReserved());

    // Close registration.
    $settings->set('status', FALSE);
    $settings->save();

    // Delete a registration. Autofill is enabled, however registration is now
    // closed for the host entity, so autofill does not occur.
    $registration = $this->entityTypeManager->getStorage('registration')->load(2);
    $this->assertEquals(2, $registration->getSpacesReserved());
    $registration->delete();
    $this->assertEquals(8, $host_entity->getActiveSpacesReserved());
    $this->assertEquals(2, $host_entity->getWaitListSpacesReserved());

    // Reopen registration.
    $settings->set('status', TRUE);
    $settings->save();

    // Fill standard capacity.
    $registration = $this->createRegistration($node);
    $registration->set('author_uid', 1);
    $registration->set('count', 2);
    $registration->save();
    $this->assertEquals('pending', $registration->getState()->id());
    $this->assertEquals(10, $host_entity->getActiveSpacesReserved());
    $this->assertEquals(2, $host_entity->getWaitListSpacesReserved());

    $registration_waitlist = $this->createRegistration($node);
    $registration_waitlist->set('author_uid', 1);
    $registration_waitlist->set('count', 2);
    $registration_waitlist->save();
    $this->assertEquals('waitlist', $registration_waitlist->getState()->id());
    $this->assertEquals(10, $host_entity->getActiveSpacesReserved());
    $this->assertEquals(4, $host_entity->getWaitListSpacesReserved());

    $registration_waitlist = $this->createRegistration($node);
    $registration_waitlist->set('author_uid', 1);
    $registration_waitlist->set('count', 2);
    $registration_waitlist->save();
    $this->assertEquals('waitlist', $registration_waitlist->getState()->id());
    $this->assertEquals(10, $host_entity->getActiveSpacesReserved());
    $this->assertEquals(6, $host_entity->getWaitListSpacesReserved());

    // Flip registration autofill to use newest first instead of oldest first.
    $registration_type = $registration->getType();
    $registration_type->setThirdPartySetting('registration_waitlist', 'autofill_sort_order', 'DESC');
    $registration_type->save();

    // Delete a registration. The newest registration should autofill instead
    // of the oldest.
    $registration->delete();
    $this->assertEquals(10, $host_entity->getActiveSpacesReserved());
    $this->assertEquals(4, $host_entity->getWaitListSpacesReserved());

    $registration_autofilled = $this->entityTypeManager->getStorage('registration')->load($registration_waitlist->id());
    $this->assertEquals('complete', $registration_autofilled->getState()->id());
  }

  /**
   * Determines if autofilled registrations match a given count and spaces.
   *
   * @param int $count
   *   The count to check.
   * @param int $spaces
   *   The spaces to check.
   *
   * @return bool
   *   TRUE if the autofill registration count matches, FALSE otherwise.
   */
  protected function loggedRegistrationCountMatches(int $count, int $spaces): bool {
    if ($spaces == 1) {
      $message = \Drupal::translation()->formatPlural($count, 'Automatically filled 1 registration from the wait list.', 'Automatically filled @count registrations from the wait list.');
    }
    else {
      $message = \Drupal::translation()->formatPlural($count, 'Automatically filled 1 registration and @spaces_filled spaces from the wait list.', 'Automatically filled @count registrations and @spaces_filled spaces from the wait list.', [
        '@spaces_filled' => $spaces,
      ]);
    }
    $database = Database::getConnection();
    $query = $database->select('watchdog')
      ->condition('message', $message);
    $query->addExpression('count(wid)', 'registrations');

    $rows = $query->execute()->fetchField();
    $rows = empty($rows) ? 0 : $rows;
    return ($rows == 1);
  }

}
