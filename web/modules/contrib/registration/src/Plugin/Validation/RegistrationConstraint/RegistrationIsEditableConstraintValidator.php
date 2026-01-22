<?php

namespace Drupal\registration\Plugin\Validation\RegistrationConstraint;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\registration\Entity\RegistrationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the RegistrationIsEditable constraint.
 *
 * @phpcs:disable Drupal.Semantics.FunctionT.NotLiteralString
 */
class RegistrationIsEditableConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * The current user.
   */
  protected AccountProxy $currentUser;

  /**
   * The configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  /**
   * Constructs a new RegistrationIsEditableConstraintValidator.
   *
   * @param \Drupal\Core\Session\AccountProxy $current_user
   *   The current user.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   */
  public function __construct(AccountProxy $current_user, ConfigFactoryInterface $config_factory) {
    $this->currentUser = $current_user;
    $this->config = $config_factory->get('registration.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): RegistrationIsEditableConstraintValidator {
    return new static(
      $container->get('current_user'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($registration, Constraint $constraint) {
    /** @var RegistrationIsEditableConstraint $constraint */
    if ($registration instanceof RegistrationInterface) {
      $host_entity = $registration->getHostEntity();
      $settings = $host_entity?->getSettings();

      // These checks only apply to existing registrations for a regular user.
      if (!$registration->isNew() && $settings) {
        // Check for administrator access.
        $access_result = $registration->access('administer', $constraint->account ?? $this->currentUser, TRUE);
        $admin = $access_result->isAllowed();

        // Add the access result to cacheability.
        $this->context->getCacheableMetadata()->addCacheableDependency($access_result);

        if (!$admin) {
          // Check the main status setting.
          $enabled = (bool) $settings->getSetting('status');
          if (!$enabled) {
            $prevent_edit = $this->config->get('prevent_edit_disabled');

            $this->context->getCacheableMetadata()->addCacheableDependency($this->config);

            if ($prevent_edit) {
              $this->context
                ->buildViolation($constraint->disabledMessage, [
                  '%label' => $host_entity->label(),
                ])
                ->setCode($constraint->disabledCode)
                ->setCause(t($constraint->disabledCause))
                ->addViolation();
              return;
            }
          }

          // Check close date.
          if ($host_entity->isAfterClose()) {
            $prevent_edit = $this->config->get('prevent_edit_disabled');

            $this->context->getCacheableMetadata()->addCacheableDependency($this->config);

            if ($prevent_edit) {
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
    }
  }

}
