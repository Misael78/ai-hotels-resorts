<?php

namespace Drupal\registration\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityMalformedException;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\registration\Event\RegistrationDataAlterEvent;
use Drupal\registration\Event\RegistrationEvents;
use Drupal\registration\HostEntityInterface;

/**
 * Defines the registration settings entity class.
 *
 * @ContentEntityType(
 *   id = "registration_settings",
 *   label = @Translation("Registration settings"),
 *   handlers = {
 *     "event" = "Drupal\registration\Event\RegistrationSettingsEvent",
 *     "access" = "Drupal\registration\RegistrationSettingsAccessControlHandler",
 *     "storage" = "Drupal\registration\RegistrationSettingsStorage",
 *     "storage_schema" = "Drupal\registration\RegistrationStorageSchema",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "default" = "Drupal\registration\Form\RegistrationSettingsForm",
 *       "edit" = "Drupal\registration\Form\RegistrationSettingsForm",
 *     },
 *   },
 *   base_table = "registration_settings",
 *   data_table = "registration_settings_field_data",
 *   translatable = TRUE,
 *   entity_keys = {
 *     "id" = "settings_id",
 *     "langcode" = "langcode",
 *     "uuid" = "uuid",
 *   },
 *   field_ui_base_route = "registration.admin_settings",
 *   constraints = {
 *     "MinimumCapacity" = {},
 *     "RedirectConstraint" = {},
 *     "ReminderConstraint" = {},
 *   }
 * )
 */
class RegistrationSettings extends ContentEntityBase implements HostEntityKeysInterface {

  /**
   * Gets the host entity that the settings are for.
   *
   * @return \Drupal\registration\HostEntityInterface|null
   *   The host entity.
   */
  public function getHostEntity(): ?HostEntityInterface {
    $this->get('host_entity')->reset();
    if (!$this->get('host_entity')->isEmpty()) {
      if ($entity = $this->get('host_entity')->entity) {
        $handler = \Drupal::entityTypeManager()->getHandler($entity->getEntityTypeId(), 'registration_host_entity');
        return $handler->createHostEntity($entity, $this->getLangcode());
      }
    }
    return NULL;
  }

  /**
   * Gets the entity ID of the host entity that the settings are for.
   *
   * @return int
   *   The host entity ID.
   */
  public function getHostEntityId(): int {
    if (!$this->get('entity_id')->isEmpty()) {
      return (int) $this->get('entity_id')->first()->getValue()['value'];
    }
    return 0;
  }

  /**
   * Gets the entity type ID of the host entity that the settings are for.
   *
   * @return string
   *   The host entity type ID, for example "node".
   */
  public function getHostEntityTypeId(): string {
    if (!$this->get('entity_type_id')->isEmpty()) {
      return $this->get('entity_type_id')->first()->getValue()['value'];
    }
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getLangcode(): ?string {
    if (!$this->get('langcode')->isEmpty()) {
      return $this->get('langcode')->first()->getValue()['value'];
    }
    return NULL;
  }

  /**
   * Gets a settings value for a given key.
   *
   * @param string $key
   *   The setting name, for example "status", "reminder date" etc.
   *
   * @return mixed
   *   The setting value. The data type depends on the key.
   */
  public function getSetting(string $key): mixed {
    if ($this->hasField($key) && !$this->get($key)->isEmpty()) {
      $setting = $this->get($key)->first()->getValue();
      return $setting['value'];
    }
    return NULL;
  }

  /**
   * Initialize settings for a given host entity from field configuration.
   *
   * @param \Drupal\registration\HostEntityInterface $host_entity
   *   The host entity.
   * @param string|null $langcode
   *   (optional) Force the language the settings should use.
   *
   * @return $this
   *   The settings entity.
   */
  public function initFromDefaults(HostEntityInterface $host_entity, ?string $langcode = NULL): RegistrationSettings {
    // Get all the fields for the settings entity.
    $fields = \Drupal::service('entity_field.manager')
      ->getFieldDefinitions('registration_settings', 'registration_settings');

    // Content entities are not allowed to set the default langcode.
    // @see \Drupal\Core\Entity\ContentEntityBase
    unset($fields['default_langcode']);

    // Get the settings field default values.
    $settings = $host_entity->getDefaultSettings($langcode);

    // Copy default values to the settings entity.
    foreach ($fields as $key => $field) {
      if (isset($settings[$key])) {
        $this->set($key, $settings[$key]);
      }
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    // Ensure host entity fields are set.
    foreach (['entity_type_id', 'entity_id'] as $field) {
      if ($this->get($field)->isEmpty()) {
        throw new EntityMalformedException(sprintf('Required registration settings field "%s" is empty.', $field));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    // Detect and avoid recursion.
    if ($this->isSyncing()) {
      return;
    }

    // Sync untranslatable field settings to all language variants.
    // No further processing is needed for single language sites.
    if (!\Drupal::languageManager()->isMultilingual()) {
      return;
    }

    // Check if sync for registration settings is enabled.
    if (!\Drupal::configFactory()
      ->get('registration.settings')
      ->get('sync_registration_settings')) {
      return;
    }

    // Ensure the host entity is available.
    $host_entity = $this->getHostEntity();
    if (!$host_entity) {
      return;
    }

    // Get all settings fields.
    $fields = \Drupal::service('entity_field.manager')
      ->getFieldDefinitions('registration_settings', 'registration_settings');

    // Remove fields that should not be copied.
    unset($fields['settings_id']);
    unset($fields['uuid']);
    unset($fields['langcode']);
    unset($fields['default_langcode']);
    unset($fields['entity_type_id']);
    unset($fields['entity_id']);
    unset($fields['host_entity']);

    // Remove translatable fields unless sync of all fields has been requested.
    if (!\Drupal::configFactory()
      ->get('registration.settings')
      ->get('sync_registration_settings_all_fields')) {
      $translatable_field_types = [
        'string',
        'string_long',
        'text',
        'text_long',
        'text_with_summary',
      ];
      foreach ($fields as $key => $field) {
        if (in_array($field->getType(), $translatable_field_types)) {
          unset($fields[$key]);
        }
      }
    }

    // Allow subscribers to alter the fields to copy.
    $event = new RegistrationDataAlterEvent($fields, [
      'host_entity' => $host_entity,
      'settings' => $this,
    ]);
    \Drupal::service('event_dispatcher')->dispatch($event, RegistrationEvents::REGISTRATION_SETTINGS_ALTER_SYNC_FIELDS);
    $fields = $event->getData();

    // Perform the sync.
    $values = [
      'entity_type_id' => $this->getHostEntityTypeId(),
      'entity_id' => $this->getHostEntityId(),
    ];
    $storage = \Drupal::entityTypeManager()->getStorage('registration_settings');
    $languages = \Drupal::languageManager()->getLanguages();
    $default_langcode = \Drupal::languageManager()->getDefaultLanguage()->getId();
    foreach ($languages as $langcode => $language) {
      if ($this->getLangcode() == $langcode) {
        // Skip the language variant being saved.
        continue;
      }
      $values['langcode'] = $langcode;
      $settings = $storage->loadByProperties($values);
      $save_needed = FALSE;
      if (empty($settings)) {
        // No settings for the language yet. Create a new settings entity
        // with language specific defaults.
        $settings_entity = $storage->create($values);
        $settings_entity->initFromDefaults($host_entity, $default_langcode);
        if ($langcode != $default_langcode) {
          $settings_entity->initFromDefaults($host_entity, $langcode);
        }

        $save_needed = TRUE;
      }
      else {
        // Found a variant that needs syncing.
        $settings_entity = reset($settings);
      }

      // Copy field values to the variant.
      foreach ($fields as $key => $field) {
        if (!$this->get($key)->isEmpty()) {
          // Field value exists on the source. Copy to destination.
          $settings_entity->set($key, $this->get($key)->getValue());
        }
        else {
          // Field value does not exist on the source. Remove from
          // destination.
          $settings_entity->set($key, NULL);
        }
        $save_needed = TRUE;
      }

      if ($save_needed) {
        // Set the syncing flag to avoid recursion.
        $settings_entity->setSyncing(TRUE);

        // Save the settings.
        $settings_entity->save();
      }
    }
  }

  /**
   * The list cache tags to invalidate for this entity.
   *
   * @return string[]
   *   Set of list cache tags.
   *
   * @see \Drupal\registration\HostEntity::getCacheTags()
   */
  protected function getListCacheTagsToInvalidate() {
    $tags = parent::getListCacheTagsToInvalidate();
    if ($host_entity = $this->getHostEntity()) {
      // Invalidate the host entity registration settings list when settings
      // are added, updated or deleted, so registration forms and other objects
      // that depend on the settings rebuild. This list is specific to the
      // host entity, and so improves cacheability compared to the standard
      // registration settings list cache tag, which invalidates across all
      // host entities.
      $tags[] = $host_entity->getRegistrationSettingsListCacheTag();
    }
    return $tags;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['entity_type_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Entity type ID'))
      ->setDescription(t('The machine name of the host entity type this registration setting is attached to.'))
      ->setSetting('max_length', EntityTypeInterface::ID_MAX_LENGTH);

    $fields['entity_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Entity ID'))
      ->setDescription(t('The ID of the host entity this registration setting is attached to.'))
      ->setSetting('unsigned', TRUE);

    $fields['langcode'] = BaseFieldDefinition::create('language')
      ->setLabel(t('Language'))
      ->setDescription(t('The language.'))
      ->setTranslatable(TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['host_entity'] = BaseFieldDefinition::create('registration_host_entity')
      ->setLabel(t('Host entity'))
      ->setDescription(t('The host entity for the registration settings.'))
      ->setComputed(TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Enable'))
      ->setDescription(t('Check to enable registrations.'))
      ->setRequired(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['capacity'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Capacity'))
      ->setDescription(t('The maximum number of registrants. Leave at 0 for no limit.'))
      ->setRequired(TRUE)
      ->setDefaultValue(0)
      ->setSetting('min', 0)
      ->setSetting('max', 99999)
      ->setDisplayOptions('form', [
        'type' => 'number',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['open'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Open date'))
      ->setDescription(t('When to automatically open registrations.'))
      ->setRequired(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'datetime_default',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['close'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Close date'))
      ->setDescription(t('When to automatically close registrations.'))
      ->setRequired(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'datetime_default',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['send_reminder'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Send reminder'))
      ->setDescription(t('If checked, a reminder will be sent to registrants on the following date.'))
      ->setRequired(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['reminder_date'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Reminder date'))
      ->setDescription(t('When to send reminders.'))
      ->setRequired(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'datetime_default',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['reminder_template'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Reminder template'))
      ->setDescription(t('The reminder email template.'))
      ->setRequired(FALSE)
      ->setTranslatable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['maximum_spaces'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Spaces allowed'))
      ->setDescription(t('The maximum number of spaces allowed for each registrations. For no limit, use 0. (Default is 1)'))
      ->setRequired(TRUE)
      ->setDefaultValue(1)
      ->setSetting('min', 0)
      ->setSetting('max', 99999)
      ->setDisplayOptions('form', [
        'type' => 'number',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['multiple_registrations'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Allow multiple registrations'))
      ->setDescription(t('If selected, each person can create multiple registrations for this event.'))
      ->setRequired(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['from_address'] = BaseFieldDefinition::create('string')
      ->setLabel(t('From address'))
      ->setDescription(t('From email address to use for confirmations, reminders, and broadcast emails.'))
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['confirmation'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Confirmation message'))
      ->setDescription(t('The message to display when someone registers. Leave blank for the default message.'))
      ->setRequired(FALSE)
      ->setTranslatable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['confirmation_redirect'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Confirmation redirect path'))
      ->setDescription(t('Optional path to redirect to when someone registers. Leave blank to redirect to the registration itself if the user has permission or the host entity if they do not.'))
      ->setRequired(FALSE)
      ->setTranslatable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}
