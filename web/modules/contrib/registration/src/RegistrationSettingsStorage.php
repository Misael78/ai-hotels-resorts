<?php

namespace Drupal\registration;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\registration\Entity\RegistrationSettings;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the storage handler class for registration settings entities.
 */
class RegistrationSettingsStorage extends RegistrationStorage {

  /**
   * The UUID interface.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected UuidInterface $uuid;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): RegistrationSettingsStorage {
    $instance = parent::createInstance($container, $entity_type);
    $instance->uuid = $container->get('uuid');
    return $instance;
  }

  /**
   * Load the settings entity for a given host entity.
   *
   * Creates one if settings do not exist yet.
   *
   * @param \Drupal\registration\HostEntityInterface $host_entity
   *   The host entity.
   * @param string|null $langcode
   *   (optional) Force the language the settings should use.
   *
   * @return \Drupal\registration\Entity\RegistrationSettings
   *   The settings entity.
   */
  public function loadSettingsForHostEntity(HostEntityInterface $host_entity, ?string $langcode = NULL): RegistrationSettings {
    // If no language set, use the current site language.
    if (!$langcode) {
      $langcode = $this->languageManager->getCurrentLanguage()->getId();
    }

    // Look for settings for the given host entity and language.
    $values = [
      'entity_type_id' => $host_entity->getEntityTypeId(),
      'entity_id' => $host_entity->id(),
      'langcode' => $langcode,
    ];
    $settings = $this->loadByProperties($values);

    // If settings were found, then return those.
    if (!empty($settings)) {
      $settings_entity = reset($settings);
      return $settings_entity;
    }

    // Unable to find the settings for the host entity and language. If the
    // language requested is not the default for the site, try the default
    // and use it as a basis for creating a new settings entity for the
    // requested language.
    $default_langcode = $this->languageManager->getDefaultLanguage()->getId();
    if ($langcode != $default_langcode) {
      $values['langcode'] = $default_langcode;
      $settings = $this->loadByProperties($values);
      if (!empty($settings)) {
        /** @var \Drupal\registration\Entity\RegistrationSettings $settings_entity */
        $settings_entity = reset($settings);
        // Copy language specific default settings to the entity.
        // For example, the reminder template is language specific.
        $settings_entity->initFromDefaults($host_entity, $langcode);
        // Set language to the override.
        $settings_entity->set('langcode', $langcode);
        // Make it new, otherwise save will overwrite the original.
        $settings_entity->set('settings_id', NULL);
        $settings_entity->set('uuid', $this->uuid->generate());
        $settings_entity->enforceIsNew();
        return $settings_entity;
      }
    }

    // Settings entity still does not exist yet. Create it.
    $values['langcode'] = $langcode;
    /** @var \Drupal\registration\Entity\RegistrationSettings $settings_entity */
    $settings_entity = $this->create($values);

    // Add default settings for the default language.
    $settings_entity->initFromDefaults($host_entity, $default_langcode);

    // Add override settings for the specific language if needed.
    if ($langcode != $default_langcode) {
      $settings_entity->initFromDefaults($host_entity, $langcode);
    }

    return $settings_entity;
  }

}
