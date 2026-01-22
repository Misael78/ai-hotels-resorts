<?php

namespace Drupal\Tests\registration_waitlist\Kernel\Event;

use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\Tests\registration\Traits\RegistrationCreationTrait;
use Drupal\Tests\registration_waitlist\Kernel\RegistrationWaitListKernelTestBase;

/**
 * Tests registration wait list events.
 *
 * @coversDefaultClass \Drupal\registration_waitlist\Event\RegistrationWaitListEvents
 *
 * @group registration
 */
class RegistrationWaitListEventTest extends RegistrationWaitListKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'registration_waitlist_test_event',
  ];

  use NodeCreationTrait;
  use RegistrationCreationTrait;

  /**
   * Tests registration wait list event subscribers.
   */
  public function testRegistrationWaitListEvent() {
    // Fill standard capacity.
    $node = $this->createAndSaveNode();
    $registration = $this->createRegistration($node);
    $registration->set('author_uid', 1);
    $registration->set('count', 5);
    $registration->save();

    // Confirm that the wait list event was not dispatched.
    $this->assertNotEquals('waitlisted', $registration->getLangcode());

    // Add to the wait list.
    $registration2 = $this->createRegistration($node);
    $registration2->set('author_uid', 1);
    $registration2->set('count', 2);
    $registration2->save();
    $this->assertEquals('waitlist', $registration2->getState()->id());

    /* @see \Drupal\registration_waitlist_test_event\EventSubscriber\RegistrationEventSubscriber */

    // Confirm that the wait list event was dispatched.
    $this->assertEquals('waitlisted', $registration2->getLangcode());

    // Reset the language to a valid value, otherwise host entity operations
    // will not be able to find the registration.
    $registration2->set('langcode', $registration->getLangcode());
    $registration2->save();

    // Delete the first registration, which auto fills the second.
    $registration->delete();
    $registration2 = $this->entityTypeManager->getStorage('registration')->load($registration2->id());
    $this->assertEquals('complete', $registration2->getState()->id());

    /* @see \Drupal\registration_waitlist_test_event\EventSubscriber\RegistrationEventSubscriber */

    // Confirm that the auto fill events were dispatched.
    $this->assertEquals('preautofill@example.org', $registration2->getEmail());
    $this->assertEquals('autofilled', $registration2->getLangcode());
  }

}
