<?php

namespace Drupal\Tests\registration_purger\Kernel;

use Drupal\Core\Database\Database;
use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\Tests\registration\Traits\RegistrationCreationTrait;

/**
 * Tests registration purger.
 *
 * @coversDefaultClass \Drupal\registration_purger\RegistrationPurger
 *
 * @group registration
 */
class RegistrationPurgerTest extends RegistrationPurgerKernelTestBase {

  use NodeCreationTrait;
  use RegistrationCreationTrait;

  /**
   * @covers ::onEntityDelete
   */
  public function testRegistrationPurger() {
    $node = $this->createAndSaveNode();
    $id1 = $node->id();
    $registration = $this->createAndSaveRegistration($node);
    $registration = $this->createAndSaveRegistration($node);
    $this->assertEquals(1, $this->getSettingsCount($id1));
    $this->assertEquals(2, $this->getRegistrationsCount($id1));

    $node = $this->createAndSaveNode();
    $id2 = $node->id();
    $registration = $this->createAndSaveRegistration($node);
    $registration = $this->createAndSaveRegistration($node);
    $registration = $this->createAndSaveRegistration($node);
    $this->assertEquals(1, $this->getSettingsCount($id2));
    $this->assertEquals(3, $this->getRegistrationsCount($id2));

    // Delete the node.
    $node->delete();
    // Confirm its settings and registrations are gone.
    $this->assertEquals(0, $this->getSettingsCount($id2));
    $this->assertEquals(0, $this->getRegistrationsCount($id2));
    // Confirm the data for the other host is untouched.
    $this->assertEquals(1, $this->getSettingsCount($id1));
    $this->assertEquals(2, $this->getRegistrationsCount($id1));

    // Ensure a node with no settings or registrations can be deleted
    // without incident.
    $node = $this->createAndSaveNode();
    $id3 = $node->id();
    $this->assertEquals(0, $this->getSettingsCount($id3));
    $this->assertEquals(0, $this->getRegistrationsCount($id3));
    $node->delete();
    $this->assertEquals(0, $this->getSettingsCount($id3));
    $this->assertEquals(0, $this->getRegistrationsCount($id3));
    // Confirm the data for the other host is untouched.
    $this->assertEquals(1, $this->getSettingsCount($id1));
    $this->assertEquals(2, $this->getRegistrationsCount($id1));

    // Ensure a node that has registrations, but is no longer configured for
    // registration, is properly purged.
    $node = $this->createAndSaveNode();
    $id4 = $node->id();
    $registration = $this->createAndSaveRegistration($node);
    $this->assertEquals(1, $this->getSettingsCount($id4));
    $this->assertEquals(1, $this->getRegistrationsCount($id4));
    $node->set('event_registration', NULL);
    $node->save();
    $node->delete();
    // Confirm its settings and registrations are gone.
    $this->assertEquals(0, $this->getSettingsCount($id4));
    $this->assertEquals(0, $this->getRegistrationsCount($id4));
    // Confirm the data for the other host is untouched.
    $this->assertEquals(1, $this->getSettingsCount($id1));
    $this->assertEquals(2, $this->getRegistrationsCount($id1));
  }

  /**
   * Gets the count of registrations for a given node.
   *
   * @param int $id
   *   The node ID.
   *
   * @return int
   *   The count.
   */
  protected function getRegistrationsCount(int $id): int {
    $database = Database::getConnection();
    $query = $database->select('registration')
      ->condition('entity_id', $id)
      ->condition('entity_type_id', 'node');

    return $query->countQuery()->execute()->fetchField();
  }

  /**
   * Gets the count of registration settings for a given node.
   *
   * @param int $id
   *   The node ID.
   *
   * @return int
   *   The count. Should return either 1 or zero.
   */
  protected function getSettingsCount(int $id): int {
    $database = Database::getConnection();
    $query = $database->select('registration_settings_field_data')
      ->condition('entity_id', $id)
      ->condition('entity_type_id', 'node');

    return $query->countQuery()->execute()->fetchField();
  }

}
