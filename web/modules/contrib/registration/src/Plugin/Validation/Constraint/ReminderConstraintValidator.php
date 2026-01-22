<?php

namespace Drupal\registration\Plugin\Validation\Constraint;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\registration\Entity\RegistrationSettings;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the ReminderConstraint constraint.
 */
class ReminderConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($settings, Constraint $constraint) {
    if ($settings instanceof RegistrationSettings) {
      // If sending a reminder, validate reminder date and template.
      if ($settings->getSetting('send_reminder')) {
        $reminder_date = $settings->getSetting('reminder_date');
        $reminder_template = $settings->getSetting('reminder_template');

        // Reminder date must be set.
        if (empty($reminder_date)) {
          $this->context->buildViolation($constraint->invalidReminderMessage)
            ->atPath('reminder_date')
            ->addViolation();
        }

        // Reminder template must be set.
        if (empty($reminder_template)) {
          $this->context->buildViolation($constraint->invalidReminderMessage)
            ->atPath('reminder_template')
            ->addViolation();
        }

        // Ensure reminder date is not in the past.
        if (!empty($reminder_date)) {
          $storage_timezone = new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE);
          $reminder_date = DrupalDateTime::createFromFormat(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $reminder_date, $storage_timezone);

          if ($reminder_date instanceof DrupalDateTime) {
            // Ensure dates are compared using the storage timezone for both.
            $now = new DrupalDateTime('now', $storage_timezone);

            if ($reminder_date <= $now) {
              $this->context->buildViolation($constraint->pastReminderMessage)
                ->atPath('reminder_date')
                ->addViolation();
            }
          }
        }
      }
    }
  }

}
