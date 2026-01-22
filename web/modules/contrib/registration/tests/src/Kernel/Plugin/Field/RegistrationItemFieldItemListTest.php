<?php

namespace Drupal\Tests\registration\Plugin\Field;

use Drupal\Tests\registration\Kernel\RegistrationKernelTestBase;
use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\Tests\registration\Traits\RegistrationCreationTrait;

/**
 * Tests the RegistrationItemFieldItemList class.
 *
 * @coversDefaultClass \Drupal\registration\Plugin\Field\RegistrationItemFieldItemList
 *
 * @group registration
 */
class RegistrationItemFieldItemListTest extends RegistrationKernelTestBase {

  use NodeCreationTrait;
  use RegistrationCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $admin_user = $this->createUser();
    $this->setCurrentUser($admin_user);
  }

  /**
   * @covers ::createHostEntity
   */
  public function testRegistrationItemFieldItemList() {
    $node = $this->createAndSaveNode();
    $registration = $this->createRegistration($node);
    $registration->save();
    $host_entity_node = $node->get('event_registration')->createHostEntity();
    $host_entity_registration = $registration->getHostEntity();
    $this->assertEquals($host_entity_node->getEntityTypeId(), $host_entity_registration->getEntityTypeId());
    $this->assertEquals($host_entity_node->id(), $host_entity_registration->id());
  }

}
