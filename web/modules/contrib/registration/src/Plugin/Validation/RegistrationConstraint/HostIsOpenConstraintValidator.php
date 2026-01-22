<?php

namespace Drupal\registration\Plugin\Validation\RegistrationConstraint;

use Drupal\registration\HostEntityInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the HostIsOpen constraint.
 *
 * @phpcs:disable Drupal.Semantics.FunctionT.NotLiteralString
 */
class HostIsOpenConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    /** @var HostIsOpenConstraint $constraint */
    $host_entity = $constraint->hostEntity ?? $value;

    if ($host_entity instanceof HostEntityInterface) {
      // Check open date.
      if ($host_entity->isBeforeOpen()) {
        $this->context
          ->buildViolation($constraint->notOpenYetMessage, [
            '%label' => $host_entity->label(),
          ])
          ->setCode($constraint->notOpenYetCode)
          ->setCause(t($constraint->notOpenYetCause))
          ->addViolation();
      }

      // Check close date.
      if ($host_entity->isAfterClose()) {
        $this->context
          ->buildViolation($constraint->closedMessage, [
            '%label' => $host_entity->label(),
          ])
          ->setCode($constraint->closedCode)
          ->setCause(t($constraint->closedCause))
          ->addViolation();
      }
    }
  }

}
