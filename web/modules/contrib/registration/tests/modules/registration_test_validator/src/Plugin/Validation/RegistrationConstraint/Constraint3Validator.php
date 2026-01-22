<?php

namespace Drupal\registration_test_validator\Plugin\Validation\RegistrationConstraint;

use Drupal\node\NodeInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates constraint 3.
 */
class Constraint3Validator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    if ($value[2] instanceof NodeInterface) {
      $this->context->getCacheableMetadata()->addCacheableDependency($value[2]);
    }

    $this->context
      ->buildViolation('A random error for constraint 3')
      ->setCode('random3')
      ->addViolation();
  }

}
