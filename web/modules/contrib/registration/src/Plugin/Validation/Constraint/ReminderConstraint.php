<?php

namespace Drupal\registration\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Prevents invalid reminders from being saved.
 *
 * @Constraint(
 *   id = "ReminderConstraint",
 *   label = @Translation("Enforce constraints on registration settings reminders", context = "Validation")
 * )
 */
class ReminderConstraint extends Constraint {

  /**
   * Reminder in the past.
   *
   * @var string
   */
  public string $pastReminderMessage = "Reminder must be in the future.";

  /**
   * Reminder without a reminder date or template.
   *
   * @var string
   */
  public string $invalidReminderMessage = "If sending a reminder, provide a date and template.";

}
