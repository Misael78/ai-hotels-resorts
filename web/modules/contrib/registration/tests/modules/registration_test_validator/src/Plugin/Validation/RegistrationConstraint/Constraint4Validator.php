<?php

namespace Drupal\registration_test_validator\Plugin\Validation\RegistrationConstraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates constraint 4.
 */
class Constraint4Validator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    $this->context->getCacheableMetadata()
      ->addCacheContexts(['user.permissions'])
      ->setCacheMaxAge(0);

    $this->context
      ->buildViolation('A random error for constraint 4')
      ->setCode('random4')
      ->addViolation();
  }

}
