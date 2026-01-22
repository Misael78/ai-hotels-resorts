<?php

namespace Drupal\registration\Plugin\Field\FieldType;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataReferenceDefinition;

/**
 * Plugin implementation of the 'registration_host_entity' field type.
 *
 * This is a computed field used for rendering links to host entities.
 *
 * UI has been disabled, since only registrations and registration settings
 * entities should use this field.
 *
 * @FieldType(
 *   id = "registration_host_entity",
 *   label = @Translation("Host entity"),
 *   description = @Translation("Field to display a host entity for a registration."),
 *   default_formatter = "registration_host_entity",
 *   cardinality = 1,
 *   no_ui = TRUE,
 *   list_class = "\Drupal\registration\Plugin\Field\HostEntityFieldItemList",
 * )
 */
class HostEntityItem extends FieldItemBase {

  /**
   * Whether the value has been calculated.
   *
   * @var bool
   */
  protected bool $isCalculated = FALSE;

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition): array {
    // The host entity is the entity that a registration is for. For example,
    // when registering for an event, the host entity is often an event node.
    // Since registrations can be for entities of any type, a custom computed
    // field must be used instead of a computed entity reference field, since
    // entity reference fields require a specific target type known in advance.
    $properties['entity'] = DataReferenceDefinition::create('entity')
      ->setLabel(new TranslatableMarkup('Host entity'))
      ->setDescription(new TranslatableMarkup('The referenced entity'))
      ->setComputed(TRUE)
      ->setReadOnly(FALSE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function __get($name) {
    $this->ensureCalculated();
    return parent::__get($name);
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    $this->ensureCalculated();
    return parent::getValue();
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty(): bool {
    $this->ensureCalculated();
    $value = $this->get('entity')->getValue();
    return ($value === NULL) || ($value === '');
  }

  /**
   * Resets the calculated state of the field.
   */
  public function reset(): void {
    $this->isCalculated = FALSE;
  }

  /**
   * Calculates the value of the field and sets it.
   */
  protected function ensureCalculated() {
    if (!$this->isCalculated) {
      // The entity is either a registration or a registration settings entity.
      $entity = $this->getEntity();
      /** @var \Drupal\registration\Entity\HostEntityKeysInterface $entity */
      if ($entity_id = $entity->getHostEntityId()) {
        $entity_type_id = $entity->getHostEntityTypeId();
        try {
          $storage = \Drupal::entityTypeManager()->getStorage($entity_type_id);
          $this->set('entity', $storage->load($entity_id));
        }
        catch (PluginNotFoundException) {
          $this->set('entity', NULL);
        }
        $this->isCalculated = TRUE;
      }
    }
  }

}
