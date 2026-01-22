<?php

namespace Drupal\registration\Plugin\Validation\RegistrationConstraint;

use Drupal\registration\Entity\RegistrationInterface;
use Drupal\registration\HostEntityInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the RegistrationWithinMaximumSpaces constraint.
 *
 * @phpcs:disable Drupal.Semantics.FunctionT.NotLiteralString
 */
class RegistrationWithinMaximumSpacesConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    /** @var RegistrationWithinMaximumSpaces $constraint */
    $host_entity = $constraint->hostEntity ?? $value;

    if ($host_entity instanceof HostEntityInterface) {
      if ($settings = $host_entity->getSettings()) {
        $registration = ($value instanceof RegistrationInterface) ? $value : NULL;
        if (!$registration || $registration->requiresCapacityCheck()) {
          $spaces = $constraint->spaces ?? ($registration ? $registration->getSpacesReserved() : 1);
          $maximum_spaces = (int) $settings->getSetting('maximum_spaces');
          if ($maximum_spaces && ($spaces > $maximum_spaces)) {
            $this->context
              ->buildViolation($constraint->tooManySpacesMessage)
              ->setParameter('@count', $maximum_spaces)
              ->setPlural($maximum_spaces)
              ->atPath('count')
              ->setCode($constraint->tooManySpacesCode)
              ->setCause(t($constraint->tooManySpacesCause))
              ->addViolation();
          }
        }
      }
    }
  }

}
