<?php

namespace Drupal\Tests\registration_waitlist\Kernel;

use Drupal\registration\Entity\RegistrationInterface;
use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\Tests\registration\Traits\RegistrationCreationTrait;

/**
 * Tests registration state change on save.
 *
 * @group registration
 */
class RegistrationWaitListSaveTest extends RegistrationWaitListKernelTestBase {

  use NodeCreationTrait;
  use RegistrationCreationTrait;

  /**
   * Tests registration save.
   *
   * @see registration_waitlist_registration_presave()
   */
  public function testRegistrationSave(): void {
    // Set up the host entity and first registration.
    $node = $this->createAndSaveNode();
    // Enable the wait list with a capacity of 1 in both standard capacity
    // and wait list capacity.
    $handler = $this->entityTypeManager->getHandler('node', 'registration_host_entity');
    $host_entity = $handler->createHostEntity($node);
    /** @var \Drupal\registration\RegistrationSettingsStorage $storage */
    $storage = $this->entityTypeManager->getStorage('registration_settings');
    $settings = $storage->loadSettingsForHostEntity($host_entity);
    $settings->set('capacity', 2);
    $settings->set('registration_waitlist_enable', TRUE);
    $settings->set('registration_waitlist_capacity', 0);
    $settings->save();

    // If capacity is no issue, a registration can have any state.
    $registration = $this->createAndSaveRegistration($node);
    $this->assertSame('pending', $registration->getState()->id());
    $this->assertRegistrationCanHaveState($registration, 'pending');
    $this->assertRegistrationCanHaveState($registration, 'complete');
    $this->assertRegistrationCanHaveState($registration, 'pending');
    $this->assertRegistrationCanHaveState($registration, 'waitlist');
    $this->assertRegistrationCanHaveState($registration, 'complete');
    $this->assertRegistrationCanHaveState($registration, 'waitlist');

    // The last registration within capacity can have any state.
    $registration = $this->createAndSaveRegistration($node);
    $this->assertSame('pending', $registration->getState()->id());
    $this->assertRegistrationCanHaveState($registration, 'pending');
    $this->assertRegistrationCanHaveState($registration, 'complete');
    $this->assertRegistrationCanHaveState($registration, 'pending');
    $this->assertRegistrationCanHaveState($registration, 'waitlist');
    $this->assertRegistrationCanHaveState($registration, 'complete');
    $this->assertRegistrationCanHaveState($registration, 'waitlist');

    // Reducing capacity does not affect already completed registrations.
    $registration->set('state', 'complete');
    $registration->save();
    $this->assertSame('complete', $registration->getState()->id());
    $this->assertTrue($host_entity->hasRoomOffWaitlist(1, $registration));
    $settings->set('capacity', 1);
    $settings->save();
    $this->assertTrue($host_entity->hasRoomOffWaitlist(1, $registration));
    $registration->save();
    $this->assertSame('complete', $registration->getState()->id());

    // Normal capacity is now full, waitlist is not.
    $this->assertFalse($host_entity->hasRoomOffWaitlist());
    $this->assertTrue($host_entity->hasRoomOnWaitlist());

    // Once capacity is full, registrations can be only waitlist or canceled.
    $registration = $this->createAndSaveRegistration($node);
    $this->assertSame('waitlist', $registration->getState()->id());
    $registration->set('state', 'pending');
    $registration->save();
    $this->assertSame('waitlist', $registration->getState()->id());
    $registration->set('state', 'held');
    $registration->save();
    $this->assertSame('waitlist', $registration->getState()->id());
    $registration->set('state', 'canceled');
    $registration->save();
    $this->assertSame('canceled', $registration->getState()->id());

    // A waitlisted registration cannot be moved to complete if no space.
    $registration->set('state', 'complete');
    $registration->save();
    $this->assertSame('waitlist', $registration->getState()->id());

    // A waitlisted registration can be moved to complete if space exists.
    $settings->set('capacity', 20);
    $settings->save();
    // Refresh static caches.
    $storage = $this->entityTypeManager->getStorage('registration');
    $registration = $storage->loadUnchanged($registration->id());
    $registration->set('state', 'complete');
    $registration->save();
    $this->assertSame('complete', $registration->getState()->id());
  }

  /**
   * Assert a registration can enter and stay in a state.
   *
   * @param \Drupal\registration\Entity\RegistrationInterface $registration
   *   The registration.
   * @param string $state
   *   The state id.
   */
  protected function assertRegistrationCanHaveState(RegistrationInterface $registration, string $state): void {
    $registration->set('state', $state);
    $registration->save();
    $this->assertSame($state, $registration->getState()->id());
    $registration->save();
    $this->assertSame($state, $registration->getState()->id());
  }

}
