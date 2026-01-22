<?php

namespace Drupal\registration\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\registration\Entity\RegistrationInterface;
use Drupal\registration\RegistrationValidatorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the RegistrationConstraint constraint.
 */
class RegistrationConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * The registration validation service.
   */
  protected RegistrationValidatorInterface $validator;

  /**
   * Constructs a new RegistrationConstraintValidator.
   *
   * @param \Drupal\registration\RegistrationValidatorInterface $validator
   *   The registration validation service.
   */
  public function __construct(RegistrationValidatorInterface $validator) {
    $this->validator = $validator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): RegistrationConstraintValidator {
    return new static(
      $container->get('registration.validator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($registration, Constraint $constraint): void {
    /** @var RegistrationConstraint $constraint */
    if ($registration instanceof RegistrationInterface) {
      if ($host_entity = $registration->getHostEntity()) {
        $validation_result = $host_entity->validate($registration);
      }
      else {
        // No host entity. Execute a constraint that will add the violation.
        $validation_result = $this->validator->execute('HostHasSettings', 'HostHasSettings', NULL);
      }

      $this->context->getViolations()->addAll($validation_result->getViolations());
    }
  }

}
