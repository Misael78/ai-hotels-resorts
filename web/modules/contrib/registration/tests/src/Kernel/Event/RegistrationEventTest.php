<?php

namespace Drupal\Tests\registration\Kernel\Event;

use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\Tests\registration\Traits\RegistrationCreationTrait;

/**
 * Tests registration events.
 *
 * @coversDefaultClass \Drupal\registration\Event\RegistrationEvent
 *
 * @group registration
 */
class RegistrationEventTest extends EventTestBase {

  use NodeCreationTrait;
  use RegistrationCreationTrait;

  /**
   * @covers ::getRegistration
   */
  public function testRegistrationEvent() {
    /* @see \Drupal\registration_test_event\EventSubscriber\RegistrationEventSubscriber */

    $node = $this->createAndSaveNode();

    // The registration_test_event module "borrows" some string fields to record
    // that an event was processed. Check those fields here. These fields are
    // not validated by constraints, so they are good candidates to use for
    // these tests.
    $registration = $this->createAndSaveRegistration($node);
    $host_entity = $registration->getHostEntity();
    $settings = $host_entity->getSettings();
    $this->assertEquals('create', $settings->getSetting('from_address'));
    $this->assertEquals('insert', $settings->getSetting('confirmation'));

    $registration = $this->reloadEntity($registration);
    $this->assertEquals('load', $registration->getLangCode());

    $registration->set('author_uid', 1);
    $registration->save();
    $this->assertEquals('presave', $registration->getLangCode());

    $settings = $this->reloadEntity($settings);
    $this->assertEquals('update', $settings->getSetting('confirmation'));

    $registration->delete();
    $settings = $this->reloadEntity($settings);
    $this->assertEquals('delete', $settings->getSetting('confirmation'));
    $this->assertEquals('predelete', $settings->getSetting('from_address'));
  }

}
