<?php

namespace Drupal\registration\Plugin\Validation\RegistrationConstraint;

use Drupal\registration\HostEntityInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the HostHasSettings constraint.
 *
 * @phpcs:disable Drupal.Semantics.FunctionT.NotLiteralString
 */
class HostHasSettingsConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    /** @var HostHasSettingsConstraint $constraint */
    $host_entity = $constraint->hostEntity ?? $value;

    if (!($host_entity instanceof HostEntityInterface)) {
      $this->context
        ->buildViolation($constraint->noHostEntityMessage)
        ->setCode($constraint->noHostEntityCode)
        ->setCause(t($constraint->noHostEntityCause))
        ->addViolation();

      // Skip any remaining constraints in the active pipeline.
      $this->context->endPipelineEarly();

      return;
    }

    if (!$host_entity->isConfiguredForRegistration()) {
      $this->context
        ->buildViolation($constraint->disabledMessage, [
          '%label' => $host_entity->label(),
        ])
        ->setCode($constraint->disabledCode)
        ->setCause(t($constraint->disabledCause))
        ->addViolation();

      // Skip any remaining constraints in the active pipeline.
      $this->context->endPipelineEarly();

      return;
    }

    // Check that settings exist.
    $settings = $host_entity->getSettings();
    if (!$settings) {
      $this->context
        ->buildViolation($constraint->noSettingsMessage, [
          '%label' => $host_entity->label(),
        ])
        ->setCode($constraint->noSettingsCode)
        ->setCause(t($constraint->noSettingsCause))
        ->addViolation();

      // Skip any remaining constraints in the active pipeline.
      $this->context->endPipelineEarly();

      return;
    }
  }

}
