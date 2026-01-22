<?php

namespace Drupal\registration_test_validator\Plugin\Validation\RegistrationConstraint;

use Drupal\node\NodeInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates constraint 1.
 */
class Constraint1Validator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    if ($value[0] instanceof NodeInterface) {
      $this->context->getCacheableMetadata()->addCacheableDependency($value[0]);
    }
    else {
      $this->context
        ->buildViolation('Invalid value')
        ->setCode('early')
        ->addViolation();

      $this->context->endPipelineEarly();
    }
  }

}
