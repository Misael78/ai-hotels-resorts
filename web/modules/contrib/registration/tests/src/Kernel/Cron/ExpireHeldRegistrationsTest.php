<?php

namespace Drupal\Tests\registration\Kernel\Cron;

use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\Tests\registration\Traits\RegistrationCreationTrait;

/**
 * Tests the cron job that expires held registrations.
 *
 * @coversDefaultClass \Drupal\registration\Cron\ExpireHeldRegistrations
 *
 * @group registration
 */
class ExpireHeldRegistrationsTest extends CronTestBase {

  use NodeCreationTrait;
  use RegistrationCreationTrait;

  /**
   * @covers ::run
   */
  public function testExpireHeldRegistrations() {
    $registration_type = $this->entityTypeManager
      ->getStorage('registration_type')
      ->load('conference');
    $this->assertNotEmpty($registration_type);

    $node = $this->createAndSaveNode();

    // Cancel a held registration that has expired.
    $registration = $this->createRegistration($node);
    $registration->set('state', 'held');
    $registration->set('changed', strtotime('-1 day'));
    $registration->save();

    $this->cron->run();

    /** @var \Drupal\registration\Entity\RegistrationInterface $registration */
    $registration = $this->reloadEntity($registration);
    $this->assertTrue($registration->isCanceled());

    // Do not cancel a held registration that has expired if its type does not
    // expire held registrations.
    $registration = $this->createRegistration($node);
    $registration->set('state', 'held');
    $registration->set('changed', strtotime('-1 day'));
    $registration->save();

    $registration_type->setHeldExpirationTime(0);
    $registration_type->save();

    $this->cron->run();

    /** @var \Drupal\registration\Entity\RegistrationInterface $registration */
    $registration = $this->reloadEntity($registration);
    $this->assertFalse($registration->isCanceled());

    // Confirm canceled after resetting the expiration time.
    $registration_type->setHeldExpirationTime(1);
    $registration_type->save();
    $this->cron->run();
    /** @var \Drupal\registration\Entity\RegistrationInterface $registration */
    $registration = $this->reloadEntity($registration);
    $this->assertTrue($registration->isCanceled());

    // Do not cancel a held registration that is not expired yet.
    $registration = $this->createRegistration($node);
    $registration->set('state', 'held');
    $registration->set('changed', strtotime('-1 minute'));
    $registration->save();

    $this->cron->run();

    /** @var \Drupal\registration\Entity\RegistrationInterface $registration */
    $registration = $this->reloadEntity($registration);
    $this->assertTrue($registration->isHeld());

    // Do not cancel an active registration.
    $registration = $this->createRegistration($node);
    $registration->set('state', 'pending');
    $registration->set('changed', strtotime('-1 day'));
    $registration->save();

    $this->cron->run();

    /** @var \Drupal\registration\Entity\RegistrationInterface $registration */
    $registration = $this->reloadEntity($registration);
    $this->assertTrue($registration->isActive());
  }

}
