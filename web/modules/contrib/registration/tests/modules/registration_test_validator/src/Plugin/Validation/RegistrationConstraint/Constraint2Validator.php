<?php

namespace Drupal\registration_test_validator\Plugin\Validation\RegistrationConstraint;

use Drupal\node\NodeInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates constraint 2.
 */
class Constraint2Validator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    if ($value[1] instanceof NodeInterface) {
      $this->context->getCacheableMetadata()->addCacheableDependency($value[1]);
    }
  }

}
