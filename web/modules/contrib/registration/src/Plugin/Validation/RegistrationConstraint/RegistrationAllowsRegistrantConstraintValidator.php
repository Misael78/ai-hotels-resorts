<?php

namespace Drupal\registration\Plugin\Validation\RegistrationConstraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\registration\Entity\RegistrationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the RegistrationAllowsRegistrant constraint.
 *
 * @phpcs:disable Drupal.Semantics.FunctionT.NotLiteralString
 */
class RegistrationAllowsRegistrantConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * The current user service.
   */
  protected AccountProxy $currentUser;

  /**
   * Constructs a new RegistrationAllowsRegistrantConstraintValidator.
   *
   * @param \Drupal\Core\Session\AccountProxy $current_user
   *   The current user.
   */
  public function __construct(AccountProxy $current_user) {
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): RegistrationAllowsRegistrantConstraintValidator {
    return new static(
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($registration, Constraint $constraint) {
    /** @var RegistrationAllowsRegistrantConstraint $constraint */
    if ($registration instanceof RegistrationInterface) {
      $this->context->getCacheableMetadata()->addCacheContexts(['user']);
      $registrant_type = $registration->getRegistrantType($this->currentUser);

      $host_entity = $registration->getHostEntity();
      $needs_check = TRUE;
      if ($registrant_type && !$registration->isNewToHost()) {
        $original = $this->context->getOriginal();
        $needs_check = $registrant_type !== $original->getRegistrantType($this->currentUser);
      }

      if ($needs_check) {
        if ($registrant_type == RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_ME) {
          $access = $host_entity->access('register self', $this->currentUser, TRUE);
          $message = $constraint->selfMessage;
        }
        elseif ($registrant_type == RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_USER) {
          $access = $host_entity->access('register other users', $this->currentUser, TRUE);
          $message = $constraint->otherMessage;
        }
        elseif ($registrant_type == RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_ANON) {
          $operation = $this->currentUser->isAnonymous() ? 'register self' : 'register other anonymous';
          $access = $host_entity->access($operation, $this->currentUser, TRUE);
          $message = $constraint->otherAnonymousMessage;
        }
        else {
          $this->context->buildViolation($constraint->registrantRequiredMessage, [])
            ->setCode($constraint->registrantRequiredCode)
            ->setCause(t($constraint->registrantRequiredCause))
            ->addViolation();
          return;
        }

        $this->context->getCacheableMetadata()->addCacheableDependency($access);

        if (!$access->isAllowed()) {
          $violation_builder = $this->context->buildViolation($message, [])
            ->setCode($constraint->registrantNotAllowedCode)
            ->setCause(t($constraint->registrantNotAllowedCause));
          if ($registration->getAnonymousEmail()) {
            $violation_builder->atPath('anon_mail');
          }
          elseif ($registration->getUserId()) {
            $violation_builder->atPath('user_uid');
          }
          $violation_builder->addViolation();
        }
      }
    }
  }

}
