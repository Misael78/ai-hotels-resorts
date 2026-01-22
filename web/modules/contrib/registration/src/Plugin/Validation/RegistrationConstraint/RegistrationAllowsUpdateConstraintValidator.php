<?php

namespace Drupal\registration\Plugin\Validation\RegistrationConstraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\registration\Entity\RegistrationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the RegistrationAllowsUpdate constraint.
 *
 * @phpcs:disable Drupal.Semantics.FunctionT.NotLiteralString
 */
class RegistrationAllowsUpdateConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * The current user.
   */
  protected AccountProxy $currentUser;

  /**
   * Constructs a new RegistrationIsEditableConstraintValidator.
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
  public static function create(ContainerInterface $container): RegistrationAllowsUpdateConstraintValidator {
    return new static(
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($registration, Constraint $constraint) {
    /** @var RegistrationAllowsUpdateConstraint $constraint */
    if ($registration instanceof RegistrationInterface) {
      $host_entity = $registration->getHostEntity();
      $settings = $host_entity?->getSettings();

      // These checks only apply to existing registrations for a regular user,
      // and the user is attempting to increase the number of spaces or change
      // the registration status or registrant.
      $registrant_changed = $this->registrantChanged($registration);
      if (!$registration->isNew() && $settings && ($registration->requiresCapacityCheck(TRUE) || $registrant_changed)) {
        $status_changed = ($registration->getState()->id() != $this->context->getOriginal()->getState()->id());
        $spaces_increased = ($registration->getSpacesReserved() > $this->context->getOriginal()->getSpacesReserved());

        // Check for administrator access.
        $access_result = $registration->access('administer', $constraint->account ?? $this->currentUser, TRUE);
        $admin = $access_result->isAllowed();

        // Add the access result to cacheability.
        $this->context->getCacheableMetadata()->addCacheableDependency($access_result);

        if (!$admin) {
          // Check the main status setting.
          $enabled = (bool) $settings->getSetting('status');
          if (!$enabled) {
            if ($status_changed) {
              $this->context
                ->buildViolation($constraint->disabledStatusMessage, [
                  '%label' => $host_entity->label(),
                ])
                ->atPath('state')
                ->setCode($constraint->disabledStatusCode)
                ->setCause(t($constraint->disabledStatusCause))
                ->addViolation();
            }
            if ($spaces_increased) {
              $this->context
                ->buildViolation($constraint->disabledSpacesMessage, [
                  '%label' => $host_entity->label(),
                ])
                ->atPath('count')
                ->setCode($constraint->disabledSpacesCode)
                ->setCause(t($constraint->disabledSpacesCause))
                ->addViolation();
            }
            if ($registrant_changed) {
              $this->context
                ->buildViolation($constraint->disabledRegistrantMessage, [
                  '%label' => $host_entity->label(),
                ])
                ->setCode($constraint->disabledRegistrantCode)
                ->setCause(t($constraint->disabledRegistrantCause))
                ->addViolation();
            }
          }

          // Check close date.
          if ($host_entity->isAfterClose()) {
            if ($status_changed) {
              $this->context
                ->buildViolation($constraint->closedStatusMessage, [
                  '%label' => $host_entity->label(),
                ])
                ->atPath('state')
                ->setCode($constraint->closedStatusCode)
                ->setCause(t($constraint->closedStatusCause))
                ->addViolation();
            }
            if ($spaces_increased) {
              $this->context
                ->buildViolation($constraint->closedSpacesMessage, [
                  '%label' => $host_entity->label(),
                ])
                ->atPath('count')
                ->setCode($constraint->closedSpacesCode)
                ->setCause(t($constraint->closedSpacesCause))
                ->addViolation();
            }
            if ($registrant_changed) {
              $this->context
                ->buildViolation($constraint->closedRegistrantMessage, [
                  '%label' => $host_entity->label(),
                ])
                ->setCode($constraint->closedRegistrantCode)
                ->setCause(t($constraint->closedRegistrantCause))
                ->addViolation();
            }
          }
        }
      }
    }
  }

  /**
   * Determines if the registrant was changed for a registration.
   *
   * @param \Drupal\registration\Entity\RegistrationInterface $registration
   *   The registration.
   *
   * @return bool
   *   TRUE if the registrant was changed, FALSE otherwise.
   */
  protected function registrantChanged(RegistrationInterface $registration): bool {
    if (!$registration->isNew()) {
      $user_changed = ($registration->getUserId() != $this->context->getOriginal()->getUserId());
      $mail_changed = ($registration->getAnonymousEmail() != $this->context->getOriginal()->getAnonymousEmail());
      return $user_changed || $mail_changed;
    }
    return FALSE;
  }

}
