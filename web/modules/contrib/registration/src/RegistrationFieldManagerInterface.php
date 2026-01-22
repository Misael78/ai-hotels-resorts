<?php

namespace Drupal\registration;

/**
 * Provides an interface for an entity field manager.
 */
interface RegistrationFieldManagerInterface {

  /**
   * Gets the base field definitions for a content entity type and language.
   *
   * This is a work around to field definitions only being available for the
   * current site language in Drupal core.
   *
   * @param string $entity_type_id
   *   The entity type ID. Only entity types that implement
   *   \Drupal\Core\Entity\FieldableEntityInterface are supported.
   * @param string $langcode
   *   The requested language.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface[]
   *   The array of base field definitions for the entity type, keyed by field
   *   name.
   *
   * @throws \LogicException
   *   Thrown if one of the entity keys is flagged as translatable.
   *
   * @see \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  public function getBaseFieldDefinitionsForLanguage(string $entity_type_id, string $langcode): array;

  /**
   * Gets the field definitions for a specific bundle and language.
   *
   * This is a work around to field definitions only being available for the
   * current site language in Drupal core.
   *
   * @param string $entity_type_id
   *   The entity type ID. Only entity types that implement
   *   \Drupal\Core\Entity\FieldableEntityInterface are supported.
   * @param string $bundle
   *   The bundle.
   * @param string|null $langcode
   *   The requested language.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface[]
   *   The array of field definitions for the bundle, keyed by field name.
   */
  public function getFieldDefinitionsForLanguage(string $entity_type_id, string $bundle, ?string $langcode): array;

}
