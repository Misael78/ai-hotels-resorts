<?php

namespace Drupal\commerce_registration\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityDefinitionUpdateManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\field\Entity\FieldStorageConfig;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates a new registration field.
 */
class PreventProductTypeRegistrationFieldValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * The entity definition update manager.
   *
   * @var \Drupal\Core\Entity\EntityDefinitionUpdateManager
   */
  protected EntityDefinitionUpdateManager $entityUpdateManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a new RegistrationFieldConstraintValidator.
   *
   * @param \Drupal\Core\Entity\EntityDefinitionUpdateManager $entity_update_manager
   *   The entity update manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityDefinitionUpdateManager $entity_update_manager, EntityTypeManagerInterface $entity_type_manager) {
    $this->entityUpdateManager = $entity_update_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity.definition_update_manager'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    if ($value instanceof FieldStorageConfig) {
      $field_storage_config = $value;
      if ($field_storage_config->getType() == 'registration') {
        // Field storage is being created for a registration field.
        $violation = FALSE;
        $entity_type_id = $field_storage_config->get('entity_type');
        if ($entity_type_id == 'commerce_product') {
          // Cannot add registration field to a commerce product.
          // Add to commerce product variations instead.
          $violation = TRUE;
          $this->context->addViolation($constraint->message);
        }
        // Cleanup after a violation by removing the storage that was created.
        // Otherwise end up with "Mismatched entity and/or field definitions".
        if ($violation) {
          $this->entityUpdateManager->uninstallFieldStorageDefinition($field_storage_config);
        }
      }
    }
  }

}
