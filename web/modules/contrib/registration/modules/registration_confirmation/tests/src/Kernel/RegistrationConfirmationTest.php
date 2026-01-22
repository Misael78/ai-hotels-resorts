<?php

namespace Drupal\Tests\registration_confirmation\Kernel;

use Drupal\Core\Database\Database;
use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\Tests\registration\Traits\RegistrationCreationTrait;
use Drupal\registration\Entity\RegistrationType;

/**
 * Tests registration confirmation emails.
 *
 * @coversDefaultClass \Drupal\registration_confirmation\EventSubscriber\RegistrationEventSubscriber
 *
 * @group registration
 */
class RegistrationConfirmationTest extends RegistrationConfirmationKernelTestBase {

  use NodeCreationTrait;
  use RegistrationCreationTrait;

  /**
   * @covers ::onUpdate
   */
  public function testRegistrationConfirmation() {
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
    $registration_type->setThirdPartySetting('registration_confirmation', 'enable', FALSE);
    $registration_type->save();

    // The registration type is not configured to send confirmation emails yet.
    $registration = $this->createAndSaveRegistration($node);
    $registration->set('state', 'pending');
    $registration->save();
    $this->assertEquals(0, $this->getLoggedEmailsCount());
    $registration->set('state', 'complete');
    $registration->save();
    $this->assertEquals(0, $this->getLoggedEmailsCount());

    // Configure the registration type to send confirmation emails.
    $registration_type->setThirdPartySetting('registration_confirmation', 'enable', TRUE);
    $registration_type->setThirdPartySetting('registration_confirmation', 'subject', 'Test message');
    $registration_type->setThirdPartySetting('registration_confirmation', 'message', [
      'value' => 'This is a test message',
      'format' => 'plain_text',
    ]);
    $registration_type->save();

    // Do not send email on cancel.
    $registration = $this->createAndSaveRegistration($node);
    $registration->set('state', 'pending');
    $registration->save();
    $this->assertEquals(0, $this->getLoggedEmailsCount());
    $registration->set('state', 'canceled');
    $registration->save();
    $this->assertEquals(0, $this->getLoggedEmailsCount());

    // Send email on transition to complete.
    $registration = $this->createAndSaveRegistration($node);
    $registration->set('state', 'pending');
    $registration->save();
    $this->assertEquals(0, $this->getLoggedEmailsCount());
    $registration->set('state', 'complete');
    $registration->save();
    $this->assertEquals(1, $this->getLoggedEmailsCount());

    // Send email if the registration starts out complete.
    $registration = $this->createRegistration($node);
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
      ->condition('message', 'Sent registration confirmation email to %recipient');
    $query->addExpression('count(wid)', 'emails');

    $count = $query->execute()->fetchField();
    $count = empty($count) ? 0 : $count;
    return $count;
  }

}
