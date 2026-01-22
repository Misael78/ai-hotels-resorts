<?php

namespace Drupal\registration_cancel_by\Plugin\Validation\Constraint;

use Drupal\registration\Entity\RegistrationSettings;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the CancelByConstraint constraint.
 */
class CancelByConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($settings, Constraint $constraint) {
    if ($settings instanceof RegistrationSettings) {
      // The "cancel by" date cannot be before the open date.
      if ($settings->getSetting('open') && $settings->getSetting('cancel_by')) {
        $open = $settings->getSetting('open');
        $cancel_by = $settings->getSetting('cancel_by');
        if ($cancel_by < $open) {
          $this->context->buildViolation($constraint->beforeOpenMessage)
            ->atPath('cancel_by')
            ->addViolation();
        }
      }
    }
  }

}
