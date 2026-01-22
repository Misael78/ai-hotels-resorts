<?php

namespace Drupal\Tests\registration_waitlist\Kernel;

use Drupal\Core\Database\Database;
use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\Tests\registration\Traits\RegistrationCreationTrait;
use Drupal\registration\Entity\RegistrationType;

/**
 * Tests registration confirmation emails.
 *
 * @coversDefaultClass \Drupal\registration_waitlist\EventSubscriber\RegistrationEventSubscriber
 *
 * @group registration
 */
class RegistrationWaitListConfirmationTest extends RegistrationWaitListKernelTestBase {

  use NodeCreationTrait;
  use RegistrationCreationTrait;

  /**
   * @covers ::onUpdate
   */
  public function testRegistrationWaitListConfirmation() {
    $node = $this->createAndSaveNode();
    $registration_type = RegistrationType::load('conference');
    $this->assertEquals(0, $this->getLoggedEmailsCount());

    // The registration type has no third party settings yet.
    $registration = $this->createAndSaveRegistration($node);
    $registration->set('state', 'pending');
    $registration->save();
    $this->assertEquals(0, $this->getLoggedEmailsCount());
    $registration->set('state', 'complete');
    $registration->save();
    $this->assertEquals(0, $this->getLoggedEmailsCount());

    // Configure third party setting as disabled.
    $registration_type->setThirdPartySetting('registration_waitlist', 'confirmation_email', FALSE);
    $registration_type->save();

    // The registration type is not configured to send wait list confirmation
    // emails yet.
    $registration = $this->createAndSaveRegistration($node);
    $registration->set('state', 'waitlist');
    $registration->save();
    $this->assertEquals(0, $this->getLoggedEmailsCount());

    // Configure the registration type to send confirmation emails.
    $registration_type->setThirdPartySetting('registration_waitlist', 'confirmation_email', TRUE);
    $registration_type->setThirdPartySetting('registration_waitlist', 'confirmation_email_subject', 'Test message');
    $registration_type->setThirdPartySetting('registration_waitlist', 'confirmation_email_message', [
      'value' => 'This is a test message about the wait list',
      'format' => 'plain_text',
    ]);
    $registration_type->save();

    // Send email on transition to the wait list.
    $registration = $this->createAndSaveRegistration($node);
    $registration->set('state', 'pending');
    $registration->save();
    $this->assertEquals(0, $this->getLoggedEmailsCount());
    $registration->set('state', 'waitlist');
    $registration->save();
    $this->assertEquals(1, $this->getLoggedEmailsCount());

    // Send email if the registration starts out on the wait list.
    $registration = $this->createRegistration($node);
    $registration->set('state', 'waitlist');
    $registration->save();
    $this->assertEquals(2, $this->getLoggedEmailsCount());

    // Do not send email when moving out of the wait list.
    $registration->set('state', 'complete');
    $registration->save();
    $this->assertEquals(2, $this->getLoggedEmailsCount());
  }

  /**
   * Gets the count of logged emails from dblog.
   *
   * @return int
   *   The count.
   */
  protected function getLoggedEmailsCount(): int {
    $database = Database::getConnection();
    $query = $database->select('watchdog')
      ->condition('message', 'Sent wait list confirmation email to %recipient');
    $query->addExpression('count(wid)', 'emails');

    $count = $query->execute()->fetchField();
    $count = empty($count) ? 0 : $count;
    return $count;
  }

}
