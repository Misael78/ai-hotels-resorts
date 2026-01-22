<?php

namespace Drupal\registration_waitlist\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\registration\Entity\RegistrationSettings;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the MinimumWaitListCapacityConstraint constraint.
 */
class MinimumWaitListCapacityConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs the MinimumCapacityConstraintValidator object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): MinimumWaitListCapacityConstraintValidator {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($settings, Constraint $constraint) {
    if (($settings instanceof RegistrationSettings) && ((int) $settings->getSetting('registration_waitlist_capacity') > 0)) {
      $entity_type_id = $settings->getHostEntityTypeId();
      $entity_id = $settings->getHostEntityId();
      $storage = $this->entityTypeManager->getStorage($entity_type_id);
      if ($entity = $storage->load($entity_id)) {
        $handler = $this->entityTypeManager->getHandler($entity_type_id, 'registration_host_entity');
        $host_entity = $handler->createHostEntity($entity);
        if ($host_entity->isWaitListEnabled()) {
          // Prevent setting the capacity to a non-zero value that is less than
          // the number of spaces already reserved by wait listed registrations.
          if ($settings->getSetting('registration_waitlist_capacity') < $host_entity->getWaitListSpacesReserved()) {
            $this->context->buildViolation($constraint->message, [
              '@type' => $host_entity->getRegistrationTypeBundle(),
              '@capacity' => $host_entity->getWaitListSpacesReserved(),
            ])->atPath('registration_waitlist_capacity')
              ->addViolation();
          }
        }
      }
    }
  }

}
