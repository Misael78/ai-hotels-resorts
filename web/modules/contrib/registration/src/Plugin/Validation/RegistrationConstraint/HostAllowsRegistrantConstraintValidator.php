<?php

namespace Drupal\registration\Plugin\Validation\RegistrationConstraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\registration\HostEntityInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the HostAllowsRegistrant constraint.
 *
 * @phpcs:disable Drupal.Semantics.FunctionT.NotLiteralString
 */
class HostAllowsRegistrantConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * The current user service.
   */
  protected AccountProxy $currentUser;

  /**
   * Constructs a new HostAllowsRegistrantConstraintValidator.
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
  public static function create(ContainerInterface $container): HostAllowsRegistrantConstraintValidator {
    return new static(
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($host_entity, Constraint $constraint) {
    /** @var HostAllowsRegistrantConstraint $constraint */
    if ($host_entity instanceof HostEntityInterface) {
      $settings = $host_entity->getSettings();
      $allow_multiple = (bool) $settings->getSetting('multiple_registrations');
      if (!$allow_multiple && $this->currentUser->isAuthenticated()) {
        // Whether a violation is added or not depends on the logged-in
        // user, so add a cache context based on the user.
        $this->context->getCacheableMetadata()->addCacheContexts(['user']);

        // See if the host allows registration access of various types.
        $result1 = $host_entity->access('register self', $this->currentUser, TRUE);
        $result2 = $host_entity->access('register other users', $this->currentUser, TRUE);
        $result3 = $host_entity->access('register other anonymous', $this->currentUser, TRUE);

        // Accumulate cacheability.
        $this->context
          ->getCacheableMetadata()
          ->addCacheableDependency($result1)
          ->addCacheableDependency($result2)
          ->addCacheableDependency($result3);

        if ($result1->isAllowed() && !$result2->isAllowed() && !$result3->isAllowed()) {
          if ($host_entity->isRegistrant($this->currentUser)) {
            // The current user is logged in, and cannot register anyone else.
            // However, the current user is already registered, and multiple
            // registrations per user are not allowed, so add a violation.
            $this->context
              ->buildViolation($constraint->youAreAlreadyRegisteredMessage)
              ->setCode($constraint->youAreAlreadyRegisteredCode)
              ->setCause(t($constraint->youAreAlreadyRegisteredCause))
              ->addViolation();
          }
        }
      }
    }
  }

}
