<?php

namespace Drupal\Tests\registration\Kernel\Plugin\Validation\Constraint;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\Tests\registration\Kernel\RegistrationKernelTestBase;
use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\Tests\registration\Traits\RegistrationCreationTrait;

/**
 * Tests the Reminder constraint.
 *
 * @coversDefaultClass \Drupal\registration\Plugin\Validation\Constraint\ReminderConstraint
 *
 * @group registration
 */
class ReminderConstraintTest extends RegistrationKernelTestBase {

  use NodeCreationTrait;
  use RegistrationCreationTrait;

  /**
   * @covers ::validate
   */
  public function testReminderConstraint() {
    $node = $this->createAndSaveNode();
    $registration = $this->createAndSaveRegistration($node);
    $host_entity = $registration->getHostEntity();
    $settings = $host_entity->getSettings();

    // Allow no reminder.
    $settings->set('send_reminder', FALSE);
    $violations = $settings->validate();
    $this->assertEquals(0, $violations->count());

    // Allow a reminder with a date and template.
    $settings->set('send_reminder', TRUE);
    $settings->set('reminder_date', '2100-01-01T00:00:00');
    $settings->set('reminder_template', 'This is an example reminder template');
    $violations = $settings->validate();
    $this->assertEquals(0, $violations->count());

    // Prevent a reminder without a date.
    $settings->set('send_reminder', TRUE);
    $settings->set('reminder_date', NULL);
    $settings->set('reminder_template', 'This is an example reminder template');
    $violations = $settings->validate();
    $this->assertEquals(1, $violations->count());

    // Prevent a reminder without a template.
    $settings->set('send_reminder', TRUE);
    $settings->set('reminder_date', '2100-01-01T00:00:00');
    $settings->set('reminder_template', NULL);
    $violations = $settings->validate();
    $this->assertEquals(1, $violations->count());

    // Prevent a reminder without a date or template.
    $settings->set('send_reminder', TRUE);
    $settings->set('reminder_date', NULL);
    $settings->set('reminder_template', NULL);
    $violations = $settings->validate();
    $this->assertEquals(2, $violations->count());

    // Prevent a reminder in the past.
    $settings->set('send_reminder', TRUE);
    $settings->set('reminder_date', '2000-01-01T00:00:00');
    $settings->set('reminder_template', 'This is an example reminder template');
    $violations = $settings->validate();
    $this->assertEquals(1, $violations->count());
    $this->assertEquals('Reminder must be in the future.', (string) $violations[0]->getMessage());

    // Validation is skipped when no reminder is to be sent.
    $settings->set('send_reminder', FALSE);
    $settings->set('reminder_date', '2000-01-01T00:00:00');
    $settings->set('reminder_template', NULL);
    $violations = $settings->validate();
    $this->assertEquals(0, $violations->count());

    // Allow a reminder in the near future.
    $near_future = (new DrupalDateTime('now', new \DateTimeZone('Europe/Amsterdam')))->modify('+1 hour');
    $settings->set('send_reminder', TRUE);
    $settings->set('reminder_date', $near_future->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, [
      'timezone' => 'UTC',
    ]));
    $settings->set('reminder_template', 'This is an example reminder template');
    $violations = $settings->validate();
    $this->assertEquals(0, $violations->count());

    // Prevent a reminder in the recent past.
    $near_future = (new DrupalDateTime('now', new \DateTimeZone('Europe/Amsterdam')))->modify('-1 hour');
    $settings->set('send_reminder', TRUE);
    $settings->set('reminder_date', $near_future->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, [
      'timezone' => 'UTC',
    ]));
    $settings->set('reminder_template', 'This is an example reminder template');
    $violations = $settings->validate();
    $this->assertEquals(1, $violations->count());
  }

}
