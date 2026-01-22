<?php

namespace Drupal\registration\Plugin\Validation\Constraint;

use Drupal\Component\Utility\UrlHelper;
use Drupal\registration\Entity\RegistrationSettings;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the RedirectConstraint constraint.
 */
class RedirectConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($settings, Constraint $constraint) {
    if ($settings instanceof RegistrationSettings) {
      if ($redirect = $settings->getSetting('confirmation_redirect')) {
        // Internal paths must start with a forward slash.
        if (!UrlHelper::isExternal($redirect) && ($redirect[0] != '/')) {
          $this->context->buildViolation($constraint->message)
            ->atPath('confirmation_redirect')
            ->addViolation();
        }
      }
    }
  }

}
