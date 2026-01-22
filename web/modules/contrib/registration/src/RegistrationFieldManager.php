<?php

namespace Drupal\registration;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityFieldManager;

/**
 * Extends the discovery of entity fields.
 *
 * This is a workaround to field definitions only being available for the
 * current site language in Drupal core.
 */
class RegistrationFieldManager extends EntityFieldManager implements RegistrationFieldManagerInterface {

  /**
   * {@inheritdoc}
   */
  public function getBaseFieldDefinitionsForLanguage(string $entity_type_id, string $langcode): array {
    // Check the static cache.
    if (!isset($this->baseFieldDefinitions[$entity_type_id])) {
      // Not prepared, try to load from cache.
      $cid = 'entity_base_field_definitions:' . $entity_type_id . ':' . $langcode;
      if ($cache = $this->cacheGet($cid)) {
        $this->baseFieldDefinitions[$entity_type_id] = $cache->data;
      }
      else {
        // Rebuild the definitions and put it into the cache.
        $this->baseFieldDefinitions[$entity_type_id] = $this->buildBaseFieldDefinitions($entity_type_id);
        $this->cacheSet($cid, $this->baseFieldDefinitions[$entity_type_id], Cache::PERMANENT, [
          'entity_types',
          'entity_field_info',
        ]);
      }
    }
    return $this->baseFieldDefinitions[$entity_type_id];
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldDefinitionsForLanguage(string $entity_type_id, string $bundle, ?string $langcode): array {
    // Default to the current language if not set.
    if (!$langcode) {
      $langcode = $this->languageManager->getCurrentLanguage()->getId();
    }
    // Use the standard entity field manager function if language matches.
    if ($langcode == $this->languageManager->getCurrentLanguage()->getId()) {
      return $this->getFieldDefinitions($entity_type_id, $bundle);
    }
    // Language doesn't match.
    if (!isset($this->fieldDefinitions[$entity_type_id][$bundle][$langcode])) {
      $base_field_definitions = $this->getBaseFieldDefinitionsForLanguage($entity_type_id, $langcode);
      // Not prepared, try to load from cache.
      $cid = 'entity_bundle_field_definitions:' . $entity_type_id . ':' . $bundle . ':' . $langcode;
      if ($cache = $this->cacheGet($cid)) {
        $bundle_field_definitions = $cache->data;
      }
      else {
        // Rebuild the definitions and put it into the cache.
        $bundle_field_definitions = $this->buildBundleFieldDefinitions($entity_type_id, $bundle, $base_field_definitions);
        $this->cacheSet($cid, $bundle_field_definitions, Cache::PERMANENT, [
          'entity_types',
          'entity_field_info',
        ]);
      }
      // Field definitions consist of the bundle specific overrides and the
      // base fields, merge them together. Use array_replace() to replace base
      // fields with by bundle overrides and keep them in order, append
      // additional by bundle fields.
      $this->fieldDefinitions[$entity_type_id][$bundle][$langcode] = array_replace($base_field_definitions, $bundle_field_definitions);
    }
    return $this->fieldDefinitions[$entity_type_id][$bundle][$langcode];
  }

}
