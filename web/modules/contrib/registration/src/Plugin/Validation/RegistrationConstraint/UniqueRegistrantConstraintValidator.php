<?php

namespace Drupal\registration\Plugin\Validation\RegistrationConstraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\registration\Entity\RegistrationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the UniqueRegistrant constraint.
 *
 * This validator adds a cache dependency on the list of registrations,
 * and should be avoided when used as part of access control. Otherwise
 * the access control will need to recalculate too often, making the
 * caching less effective.
 *
 * @phpcs:disable Drupal.Semantics.FunctionT.NotLiteralString
 */
class UniqueRegistrantConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * The current user service.
   */
  protected AccountProxy $currentUser;

  /**
   * Constructs a new UniqueRegistrantConstraintValidator.
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
  public static function create(ContainerInterface $container): UniqueRegistrantConstraintValidator {
    return new static(
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($registration, Constraint $constraint) {
    if ($registration instanceof RegistrationInterface) {
      $host_entity = $registration->getHostEntity();
      if ($settings = $host_entity?->getSettings()) {

        // Recheck if registrations are added, updated or deleted for this host.
        $list_cache_tag = $host_entity->getRegistrationListCacheTag();
        $this->context->getCacheableMetadata()->addCacheTags([$list_cache_tag]);

        $allow_multiple = $settings->getSetting('multiple_registrations');
        if (!$allow_multiple) {

          // Multiple registrations per person are not allowed.
          if ($registration->isNewToHost()) {
            if ($email = $registration->getAnonymousEmail()) {
              $this->validateEmail($registration, $constraint, $email);
            }
            else {
              $this->validateUser($registration, $constraint, $registration->getUser());
            }
          }
          else {
            // For an existing registration, validation is only appropriate
            // when the email or user has changed. An existing registration
            // should not become invalid because another registration is
            // created or the allow_multiple setting is changed.
            $original = $this->context->getOriginal();

            /** @var \Drupal\registration\Entity\RegistrationInterface $original */
            if ($registration->getAnonymousEmail() != $original->getAnonymousEmail()) {
              if ($email = $registration->getAnonymousEmail()) {
                $this->validateEmail($registration, $constraint, $email);
              }
            }
            if ($registration->getUserId() != $original->getUserId()) {
              if ($user = $registration->getUser()) {
                $this->validateUser($registration, $constraint, $user);
              }
            }
          }
        }
      }
    }
  }

  /**
   * Validates the email address.
   *
   * @param \Drupal\registration\Entity\RegistrationInterface $registration
   *   The registration.
   * @param \Symfony\Component\Validator\Constraint $constraint
   *   The constraint.
   * @param string $email
   *   The email address.
   */
  protected function validateEmail(RegistrationInterface $registration, Constraint $constraint, string $email): void {
    /** @var UniqueRegistrantConstraint $constraint */
    $host_entity = $registration->getHostEntity();
    if ($host_entity->isRegistrant(NULL, $email)) {
      $this->context
        ->buildViolation($constraint->emailAlreadyRegisteredMessage, [
          '%mail' => $email,
        ])
        ->atPath('anon_mail')
        ->setCode($constraint->emailAlreadyRegisteredCode)
        ->setCause(t($constraint->emailAlreadyRegisteredCause))
        ->addViolation();
    }
  }

  /**
   * Validates the user account.
   *
   * @param \Drupal\registration\Entity\RegistrationInterface $registration
   *   The registration.
   * @param \Symfony\Component\Validator\Constraint $constraint
   *   The constraint.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   (optional) The user account, defaults to the current user.
   */
  protected function validateUser(RegistrationInterface $registration, Constraint $constraint, ?AccountInterface $account = NULL): void {
    // Default to the current user.
    if (!$account) {
      $account = $this->currentUser;
      // Whether a violation is added or not depends on the logged-in
      // user, so add a cache context based on the user.
      $this->context->getCacheableMetadata()->addCacheContexts(['user']);
    }
    $host_entity = $registration->getHostEntity();

    /** @var UniqueRegistrantConstraint $constraint */
    if ($host_entity->isRegistrant($account)) {
      // The choice of message depends on the current user, so add a cache
      // context based on the user.
      $this->context->getCacheableMetadata()->addCacheContexts(['user']);

      if ($account->id() == $this->currentUser->id()) {
        $this->context
          ->buildViolation($constraint->youAreAlreadyRegisteredMessage)
          ->setCode($constraint->youAreAlreadyRegisteredCode)
          ->setCause(t($constraint->youAreAlreadyRegisteredCause))
          ->addViolation();
      }
      else {
        $this->context
          ->buildViolation($constraint->userAlreadyRegisteredMessage, [
            '%user' => $account->getDisplayName(),
          ])
          ->atPath('user_uid')
          ->setCode($constraint->userAlreadyRegisteredCode)
          ->setCause(t($constraint->userAlreadyRegisteredCause))
          ->addViolation();
      }
    }
  }

}
