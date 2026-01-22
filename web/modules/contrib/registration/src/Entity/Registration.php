<?php

namespace Drupal\registration\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityMalformedException;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\registration\HostEntityInterface;
use Drupal\user\UserInterface;
use Drupal\workflows\StateInterface;
use Drupal\workflows\WorkflowInterface;

/**
 * Defines the registration entity class.
 *
 * @ContentEntityType(
 *   id = "registration",
 *   label = @Translation("Registration"),
 *   label_collection = @Translation("Registrations"),
 *   label_singular = @Translation("registration"),
 *   label_plural = @Translation("registrations"),
 *   label_count = @PluralTranslation(
 *     singular = "@count registration",
 *     plural = "@count registrations",
 *   ),
 *   bundle_label = @Translation("Registration type"),
 *   handlers = {
 *     "event" = "Drupal\registration\Event\RegistrationEvent",
 *     "host_entity" = "Drupal\registration\RegistrationHostEntityHandler",
 *     "storage" = "Drupal\registration\RegistrationStorage",
 *     "storage_schema" = "Drupal\registration\RegistrationStorageSchema",
 *     "access" = "Drupal\registration\RegistrationAccessControlHandler",
 *     "list_builder" = "Drupal\registration\RegistrationListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "default" = "Drupal\registration\Form\RegisterForm",
 *       "edit" = "Drupal\registration\Form\RegisterForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *       "register" = "Drupal\registration\Form\RegisterForm",
 *     },
 *     "route_provider" = {
 *       "default" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   admin_permission = "administer registration",
 *   permission_granularity = "bundle",
 *   storage_schema = "Drupal\registration\RegistrationStorageSchema",
 *   base_table = "registration",
 *   entity_keys = {
 *     "id" = "registration_id",
 *     "bundle" = "type",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "canonical" = "/registration/{registration}",
 *     "edit-form" = "/registration/{registration}/edit",
 *     "delete-form" = "/registration/{registration}/delete",
 *     "collection" = "/admin/people/registrations"
 *   },
 *   bundle_entity_type = "registration_type",
 *   field_ui_base_route = "entity.registration_type.edit_form",
 *   constraints = {
 *     "RegistrationConstraint" = {}
 *   }
 * )
 */
class Registration extends ContentEntityBase implements HostEntityKeysInterface, RegistrationInterface {

  use EntityChangedTrait;

  /**
   * The host entity for the registration.
   *
   * This will likely never be NULL unless a migration imports a registration
   * without a matching host entity in the destination database.
   *
   * @var \Drupal\registration\HostEntityInterface|null
   */
  protected HostEntityInterface|NULL $hostEntity;

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    $host_entity = $this->getHostEntity();
    if (!$this->isNew() && $host_entity) {
      return t('Registration #@id for', [
        '@id' => $this->id(),
      ]) . ' ' . $host_entity->label();
    }
    elseif ($this->isNew() && $host_entity) {
      return t('Registration for') . ' ' . $host_entity->label();
    }
    elseif (!$this->isNew()) {
      return t('Registration #@id', [
        '@id' => $this->id(),
      ]);
    }
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getAnonymousEmail(): string {
    if (!$this->get('anon_mail')->isEmpty()) {
      return $this->get('anon_mail')->first()->getValue()['value'];
    }
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthor(): ?UserInterface {
    if (!$this->get('author_uid')->isEmpty()) {
      $author = NULL;
      if ($entities = $this->get('author_uid')->referencedEntities()) {
        $author = $entities[0];
      }
      if ($author && $author->isAuthenticated()) {
        return $author;
      }
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthorDisplayName(): ?string {
    if ($author = $this->getAuthor()) {
      return $author->getDisplayName();
    }
    // No author, must be an anonymous self registration.
    // Return the name of the anonymous site visitor.
    return \Drupal::config('user.settings')->get('anonymous');
  }

  /**
   * {@inheritdoc}
   */
  public function getEmail(): string {
    if (!$this->get('mail')->isEmpty()) {
      return $this->get('mail')->first()->getValue()['value'];
    }
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getHostEntity(?string $langcode = NULL): ?HostEntityInterface {
    if ($this->shouldBuildHostEntity()) {
      $this->hostEntity = NULL;
      $this->get('host_entity')->reset();
      if (!$this->get('host_entity')->isEmpty()) {
        $entity = $this->get('host_entity')->first()->get('entity')->getValue();
        // Check if a specific language was requested. If not then default
        // to the current site language.
        if (!$langcode) {
          $langcode = \Drupal::languageManager()
            ->getCurrentLanguage()
            ->getId();
        }
        $this->hostEntity = $this->entityTypeManager()
          ->getHandler($entity->getEntityTypeId(), 'registration_host_entity')
          ->createHostEntity($entity, $langcode);
      }
    }
    return $this->hostEntity;
  }

  /**
   * {@inheritdoc}
   */
  public function getHostEntityId(): int|string|NULL {
    if (!$this->get('entity_id')->isEmpty()) {
      return (int) $this->get('entity_id')->first()->getValue()['value'];
    }
    return 0;
  }

  /**
   * {@inheritdoc}
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
  public function getHostEntityTypeLabel(): ?string {
    return $this->getHostEntity()?->getEntityTypeLabel();
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
   * {@inheritdoc}
   */
  public function getRegistrantType(AccountInterface $account): ?string {
    $reg_type = NULL;
    if ($account->id() && ($account->id() == $this->getUserId())) {
      $reg_type = self::REGISTRATION_REGISTRANT_TYPE_ME;
    }
    elseif ($this->getUserId()) {
      $reg_type = self::REGISTRATION_REGISTRANT_TYPE_USER;
    }
    elseif ($this->getAnonymousEmail()) {
      $reg_type = self::REGISTRATION_REGISTRANT_TYPE_ANON;
    }
    return $reg_type;
  }

  /**
   * {@inheritdoc}
   */
  public function getSpacesReserved(): int {
    if (!$this->get('count')->isEmpty()) {
      return (int) $this->get('count')->first()->getValue()['value'];
    }
    else {
      return 1;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getType(): RegistrationTypeInterface {
    return $this->type->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getUser(): ?UserInterface {
    if (!$this->get('user_uid')->isEmpty()) {
      if ($entities = $this->get('user_uid')->referencedEntities()) {
        return $entities[0];
      }
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getUserId(): int {
    if (!$this->get('user_uid')->isEmpty()) {
      return (int) $this->get('user_uid')->first()->getValue()['target_id'];
    }
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getWorkflow(): WorkflowInterface {
    if ($this->get('workflow')->isEmpty()) {
      return $this->getType()->getWorkflow();
    }
    else {
      return $this->workflow->entity;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getState(): StateInterface {
    $workflow = $this->getWorkflow();
    if ($this->get('state')->isEmpty()) {
      return $workflow->getTypePlugin()->getState($this->getType()->getDefaultState());
    }
    else {
      return $workflow->getTypePlugin()->getState($this->get('state')->first()->getValue()['value']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCompletedTime(): ?int {
    if (!$this->get('completed')->isEmpty()) {
      return $this->get('completed')->value;
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime(): int {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime(int $timestamp): RegistrationInterface {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isActive(): bool {
    return $this->getState()->isActive();
  }

  /**
   * {@inheritdoc}
   */
  public function isCanceled(): bool {
    return $this->getState()->isCanceled();
  }

  /**
   * {@inheritdoc}
   */
  public function isComplete(): bool {
    // Unlike other states, only one complete state per workflow can be
    // configured. Workflows that need more than one complete state can
    // override this method.
    $is_complete = FALSE;
    $plugin = $this->getWorkflow()->getTypePlugin();
    $configuration = $plugin->getConfiguration();
    if (!empty($configuration['complete_registration_state'])) {
      $complete_state = $configuration['complete_registration_state'];
      $is_complete = ($this->getState()->id() == $complete_state);
    }
    return $is_complete;
  }

  /**
   * {@inheritdoc}
   */
  public function isHeld(): bool {
    return $this->getState()->isHeld();
  }

  /**
   * {@inheritdoc}
   */
  public function isNewToHost(): bool {
    if (!$this->isNew()) {
      $original = $this->entityTypeManager()->getStorage('registration')->loadUnchanged($this->id());
      if ($original instanceof RegistrationInterface) {
        $different_host_entity_type = $original->getHostEntityTypeId() !== $this->getHostEntityTypeId();
        $different_host_id = $original->getHostEntityId() !== $this->getHostEntityId();
        return $different_host_entity_type || $different_host_id;
      }
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function requiresCapacityCheck(bool $checkCanceled = FALSE): bool {
    $requires_check = TRUE;

    // A check may not be needed for canceled registrations.
    if (!$checkCanceled && $this->getState()->isCanceled()) {
      $requires_check = FALSE;
    }

    // An existing registration must be checked if its host has changed,
    // its state has changed, or the number of spaces reserved has increased.
    elseif (!$this->isNewToHost()) {
      $original = $this->entityTypeManager()->getStorage('registration')->loadUnchanged($this->id());
      $status_changed = ($this->getState()->id() != $original->getState()->id());
      $spaces_increased = ($this->getSpacesReserved() > $original->getSpacesReserved());
      $requires_check = $status_changed || $spaces_increased;
    }

    return $requires_check;
  }

  /**
   * Determines whether the host entity needs to be built.
   *
   * @return bool
   *   TRUE if the host entity needs to be built, FALSE otherwise.
   */
  protected function shouldBuildHostEntity(): bool {
    if (isset($this->hostEntity)) {
      // Rebuild if there is a new host.
      return ($this->hostEntity->id() != $this->getHostEntityId()) || ($this->hostEntity->getEntityTypeId() != $this->getHostEntityTypeId());
    }
    // Build if not set yet.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    // Ensure host entity fields are set.
    foreach (['entity_type_id', 'entity_id'] as $field) {
      if ($this->get($field)->isEmpty()) {
        throw new EntityMalformedException(sprintf('Required registration field "%s" is empty.', $field));
      }
    }

    // Author default.
    if ($this->get('author_uid')->isEmpty()) {
      $current_user = \Drupal::service('current_user');
      if ($current_user->isAuthenticated()) {
        $this->set('author_uid', $current_user->id());
      }
    }
    // Count default.
    if ($this->get('count')->isEmpty()) {
      $this->set('count', 1);
    }
    // Mail default.
    if ($this->get('mail')->isEmpty()) {
      if ($user = $this->getUser()) {
        $this->set('mail', $user->getEmail());
      }
      else {
        $this->set('mail', $this->getAnonymousEmail());
      }
    }
    // Status default.
    if ($this->get('state')->isEmpty()) {
      $this->set('state', $this->getState()->id());
    }
    // Workflow default.
    if ($this->get('workflow')->isEmpty()) {
      $this->set('workflow', $this->getType()->getWorkflowId());
    }
    // Language default.
    if ($this->get('langcode')->isEmpty()) {
      $langcode = '';
      // For an authenticated user registration, use the preferred language set
      // in their account, if any.
      if ($user = $this->getUser()) {
        $langcode = $user->getPreferredLangcode(FALSE);
      }
      // If an anonymous registration, or the user does not have a language
      // set in their account, then use the current language for the site.
      if (empty($langcode)) {
        $langcode = \Drupal::languageManager()
          ->getCurrentLanguage()
          ->getId();
      }
      $this->set('langcode', $langcode);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    // Ensure registrations are backed by stored settings.
    if (!$update) {
      $settings = NULL;
      $host_entity = $this->getHostEntity();
      $entity_type_manager = $this->entityTypeManager();
      if ($langcode = $this->getLangcode()) {
        $settings = $entity_type_manager
          ->getStorage('registration_settings')
          ->loadSettingsForHostEntity($host_entity, $langcode);
        if ($settings->isNew()) {
          $settings->save();
        }
      }

      // Ensure the site default language has settings, if different from the
      // current language.
      if ($langcode = $settings?->getLangcode()) {
        $default_langcode = \Drupal::languageManager()
          ->getDefaultLanguage()
          ->getId();
        if ($langcode != $default_langcode) {
          $settings = $entity_type_manager
            ->getStorage('registration_settings')
            ->loadSettingsForHostEntity($host_entity, $default_langcode);
          if ($settings->isNew()) {
            $settings->save();
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTagsToInvalidate() {
    $tags = parent::getCacheTagsToInvalidate();
    if (!$this->isNew() && ($user = $this->getUser())) {
      // Invalidate the registration user so the user registrations task
      // rebuilds as needed.
      $tags[] = 'registration.user:' . $user->id();
    }
    return $tags;
  }

  /**
   * The list cache tags to invalidate for this entity.
   *
   * @return string[]
   *   Set of list cache tags.
   */
  protected function getListCacheTagsToInvalidate() {
    $tags = parent::getListCacheTagsToInvalidate();
    if ($host_entity = $this->getHostEntity()) {
      // Invalidate the host entity registration list when registrations are
      // added, updated or deleted, so registration forms and other objects
      // that depend on the host entity rebuild. This ensures that the forms
      // and objects reflect the latest information about host entity capacity.
      $tags[] = $host_entity->getRegistrationListCacheTag();
    }
    return $tags;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['workflow'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Workflow'))
      ->setDescription(t('The workflow the registration is in.'))
      ->setSetting('target_type', 'workflow')
      ->setRequired(TRUE);

    $fields['entity_type_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Entity type ID'))
      ->setDescription(t('The machine name of the host entity type this registration is attached to.'))
      ->setSetting('max_length', EntityTypeInterface::ID_MAX_LENGTH);

    $fields['entity_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Entity ID'))
      ->setDescription(t('The ID of the host entity this registration is attached to.'))
      ->setSetting('unsigned', TRUE);

    $fields['langcode'] = BaseFieldDefinition::create('language')
      ->setLabel(t('Language'))
      ->setDescription(t('The language used for the registration.'))
      ->setTranslatable(TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['host_entity'] = BaseFieldDefinition::create('registration_host_entity')
      ->setLabel(t('Host entity'))
      ->setDescription(t('The host entity for the registration.'))
      ->setComputed(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'registration_host_entity',
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['anon_mail'] = BaseFieldDefinition::create('email')
      ->setLabel(t('Email'))
      ->setDescription(t('The email to associate with this registration.'))
      ->setDisplayOptions('form', [
        'type' => 'email_default',
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['user_uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User'))
      ->setDescription(t('Select a user by typing their username to get a list of matches.'))
      ->setSetting('target_type', 'user')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'author',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['count'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Spaces'))
      ->setDescription(t('How many spaces the registration should use towards the total capacity for the event.'))
      ->setSetting('min', 1)
      ->setDisplayOptions('form', [
        'type' => 'registration_spaces_default',
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'number_integer',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['mail'] = BaseFieldDefinition::create('email')
      ->setLabel(t('Email address'))
      ->setDescription(t('The email (anonymous or authenticated) associated with this registration.'))
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'email_mailto',
        'weight' => -10,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['author_uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Author'))
      ->setDescription(t('The user who created the registration.'))
      ->setSetting('target_type', 'user')
      ->setRequired(TRUE)
      ->setReadOnly(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'author',
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['state'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Status'))
      ->setDescription(t('The registration status.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'registration_state_default',
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'registration_state',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time when the registration was created.'))
      ->setTranslatable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 10,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'timestamp',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time when the registration was last saved.'))
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'timestamp',
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['completed'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Completed'))
      ->setDescription(t('The time when the registration was completed.'))
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'timestamp',
      ])
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}
