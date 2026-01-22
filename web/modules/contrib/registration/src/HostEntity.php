<?php

namespace Drupal\registration;

use Drupal\Component\Datetime\DateTimePlus;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Cache\RefinableCacheableDependencyTrait;
use Drupal\Core\Database\Database;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\TypedData\TranslatableInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\registration\Entity\RegistrationInterface;
use Drupal\registration\Entity\RegistrationSettings;
use Drupal\registration\Entity\RegistrationType;
use Drupal\registration\Entity\RegistrationTypeInterface;
use Drupal\registration\Event\RegistrationDataAlterEvent;
use Drupal\registration\Event\RegistrationEvents;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Defines the class for the host entity.
 *
 * This is a pseudo-entity wrapper around a real entity.
 */
class HostEntity implements RefinableCacheableDependencyInterface, HostEntityInterface {

  use DependencySerializationTrait;
  use RefinableCacheableDependencyTrait;
  use StringTranslationTrait;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected AccountProxy $currentUser;

  /**
   * The real entity that is wrapped.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected EntityInterface $entity;

  /**
   * The entity field manager.
   *
   * @var \Drupal\registration\RegistrationFieldManagerInterface
   */
  protected RegistrationFieldManagerInterface $entityFieldManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Contracts\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected Renderer $renderer;

  /**
   * The settings for the host entity.
   *
   * @var \Drupal\registration\Entity\RegistrationSettings|null
   */
  protected ?RegistrationSettings $settings;

  /**
   * The registration validator.
   *
   * @var \Drupal\registration\RegistrationValidatorInterface
   */
  protected RegistrationValidatorInterface $validator;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected TimeInterface $time;

  /**
   * Creates a HostEntity object.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The real entity being wrapped.
   * @param string|null $langcode
   *   (optional) The language the real entity should use, if available.
   */
  public function __construct(EntityInterface $entity, ?string $langcode = NULL) {
    // Get the entity in the appropriate language if requested. Since the
    // entity type is not known until runtime, need to make sure it is
    // translatable before proceeding.
    if ($langcode) {
      if ($entity->getEntityType()->entityClassImplements(TranslatableInterface::class)) {
        /** @var \Drupal\Core\TypedData\TranslatableInterface $entity */
        if ($entity->isTranslatable() && ($entity->language()->getId() != $langcode)) {
          // Switch to the requested language if the entity has a translation
          // available.
          if ($entity->hasTranslation($langcode)) {
            $entity = $entity->getTranslation($langcode);
          }
        }
      }
    }
    $this->entity = $entity;

    // Initialize cacheability with a dependency on the wrapped entity.
    $this->addCacheableDependency($this->entity);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(EntityInterface $entity, ?string $langcode = NULL): HostEntityInterface {
    $handler = \Drupal::entityTypeManager()->getHandler($entity->getEntityTypeId(), 'registration_host_entity');
    return $handler->createHostEntity($entity, $langcode);
  }

  /**
   * {@inheritdoc}
   */
  public function access($operation, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $handler = $this->entityTypeManager()->getHandler($this->getEntityTypeId(), 'registration_host_access');
    return $handler->access($this, $operation, $account, $return_as_object);
  }

  /**
   * {@inheritdoc}
   */
  public function bundle(): string {
    return $this->getEntity()->bundle();
  }

  /**
   * {@inheritdoc}
   */
  public function getEntity(): EntityInterface {
    return $this->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeId(): string {
    return $this->getEntity()->getEntityTypeId();
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeLabel(): string {
    $entity_type = $this->getEntity()->getEntityType();
    if ($bundle_type = $entity_type->getBundleEntityType()) {
      return $this->entityTypeManager()
        ->getStorage($bundle_type)
        ->load($this->bundle())
        ->label();
    }
    else {
      return $entity_type->getLabel();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function id(): string|int|NULL {
    return $this->getEntity()->id();
  }

  /**
   * {@inheritdoc}
   */
  public function isNew(): bool {
    return $this->getEntity()->isNew();
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    return $this->getEntity()->label();
  }

  /**
   * {@inheritdoc}
   */
  public function addCacheableDependencies(array &$build, array $other_entities = []) {
    @trigger_error('HostEntity::addCacheableDependencies() is deprecated in registration:3.4.0 and is removed from registration:4.0.0. See https://www.drupal.org/node/3506325', E_USER_DEPRECATED);

    // Rebuild if the host entity is updated.
    $this->renderer()->addCacheableDependency($build, $this);

    // Rebuild if other entities are updated.
    foreach ($other_entities as $entity) {
      if (isset($entity)) {
        $this->renderer()->addCacheableDependency($build, $entity);
      }
    }

    // Rebuild when registrations are added, updated or deleted for this host.
    $tags = $build['#cache']['tags'];
    $build['#cache']['tags'] = Cache::mergeTags($tags, [$this->getRegistrationListCacheTag()]);

    // Rebuild per user permissions or anonymous session.
    if ($this->currentUser()->isAnonymous()) {
      $build['#cache']['contexts'] = Cache::mergeContexts($build['#cache']['contexts'], ['session']);
    }
    else {
      $build['#cache']['contexts'] = Cache::mergeContexts($build['#cache']['contexts'], ['user.permissions']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function createRegistration(bool $save = FALSE): RegistrationInterface {
    $values = [
      'entity_type_id' => $this->getEntityTypeId(),
      'entity_id' => $this->id(),
      'type' => $this->getRegistrationTypeBundle(),
      'count' => 1,
    ];
    /** @var \Drupal\registration\Entity\RegistrationInterface $registration */
    $registration = $this->entityTypeManager()->getStorage('registration')->create($values);
    if ($save) {
      $registration->save();
    }
    return $registration;
  }

  /**
   * {@inheritdoc}
   */
  public function generateSampleRegistration(bool $save = FALSE): RegistrationInterface {
    $registration = $this->createRegistration();
    $registration->set('user_uid', $this->currentUser()->id());
    $registration->set('mail', $this->currentUser()->getEmail());
    if ($save) {
      $registration->save();
    }
    return $registration;
  }

  /**
   * {@inheritdoc}
   */
  public function getActiveSpacesReserved(?RegistrationInterface $registration = NULL): int {
    $states = [];

    if ($registration_type = $this->getRegistrationType()) {
      $states = $registration_type->getActiveOrHeldStates();
    }

    // Ensure we have active states before querying against them.
    if (empty($states)) {
      return 0;
    }

    $database = Database::getConnection();
    $query = $database->select('registration')
      ->condition('entity_id', $this->id())
      ->condition('entity_type_id', $this->getEntityTypeId())
      ->condition('state', array_keys($states), 'IN');

    if ($registration && !$registration->isNew()) {
      $query->condition('registration_id', $registration->id(), '<>');
    }

    $query->addExpression('sum(count)', 'spaces');

    $spaces = $query->execute()->fetchField();
    $spaces = empty($spaces) ? 0 : $spaces;

    // Allow other modules to alter the number of spaces reserved.
    $event = new RegistrationDataAlterEvent($spaces, [
      'host_entity' => $this,
      'settings' => $this->getSettings(),
      'registration' => $registration,
    ]);
    $this->eventDispatcher()->dispatch($event, RegistrationEvents::REGISTRATION_ALTER_USAGE);
    return $event->getData() ?? 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    $cache_contexts = $this->cacheContexts;

    // Add cache contexts for entities the host depends on.
    if ($registration_type = $this->getRegistrationType()) {
      $cache_contexts = Cache::mergeContexts($cache_contexts, $registration_type->getCacheContexts());
    }
    if ($field = $this->getRegistrationField()) {
      $cache_contexts = Cache::mergeContexts($cache_contexts, $field->getCacheContexts());
    }
    if ($settings = $this->getSettings()) {
      $cache_contexts = Cache::mergeContexts($cache_contexts, $settings->getCacheContexts());
    }

    return $cache_contexts;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge(): int {
    $cache_max_age = $this->cacheMaxAge;

    // Merge max-age for entities the host depends on.
    if ($registration_type = $this->getRegistrationType()) {
      $cache_max_age = Cache::mergeMaxAges($cache_max_age, $registration_type->getCacheMaxAge());
    }
    if ($field = $this->getRegistrationField()) {
      $cache_max_age = Cache::mergeMaxAges($cache_max_age, $field->getCacheMaxAge());
    }
    if ($settings = $this->getSettings()) {
      $cache_max_age = Cache::mergeMaxAges($cache_max_age, $settings->getCacheMaxAge());
    }

    // Set a cache expiration if applicable.
    if ($max_age = $this->calculateMaxAge()) {
      $cache_max_age = Cache::mergeMaxAges($cache_max_age, $max_age);
    }

    return $cache_max_age;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    $cache_tags = $this->cacheTags;

    // Add cache tags for entities the host depends on. The dependency on the
    // wrapped entity is handled in the constructor for this class.
    if ($registration_type = $this->getRegistrationType()) {
      $cache_tags = Cache::mergeTags($cache_tags, $registration_type->getCacheTags());
    }
    if ($field = $this->getRegistrationField()) {
      $cache_tags = Cache::mergeTags($cache_tags, $field->getCacheTags());
    }

    // If the host has saved settings, they should be included in cacheability.
    if (($settings = $this->getSettings()) && !$settings->isNew()) {
      $cache_tags = Cache::mergeTags($cache_tags, $settings->getCacheTags());
    }
    else {
      // No settings, or they have not been saved yet. Add a dependency on the
      // list, so that when settings are finally saved, anything dependent on
      // this host entity will rebuild. Without this, changes to the settings
      // will never be reflected in dependent objects.
      $cache_tags = Cache::mergeTags($cache_tags, [$this->getRegistrationSettingsListCacheTag()]);
    }

    return $cache_tags;
  }

  /**
   * {@inheritdoc}
   */
  public function getCloseDate(): ?DateTimePlus {
    $close = $this->getSetting('close');
    if ($close) {
      $storage_timezone = new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE);
      return DrupalDateTime::createFromFormat(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $close, $storage_timezone);
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getOpenDate(): ?DateTimePlus {
    $open = $this->getSetting('open');
    if ($open) {
      $storage_timezone = new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE);
      return DrupalDateTime::createFromFormat(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $open, $storage_timezone);
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getReminderDate(): ?DateTimePlus {
    $reminder_date = $this->getSetting('reminder_date');
    if ($reminder_date) {
      $storage_timezone = new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE);
      return DrupalDateTime::createFromFormat(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $reminder_date, $storage_timezone);
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getSpacesRemaining(?RegistrationInterface $registration = NULL): ?int {
    if ($capacity = $this->getSetting('capacity')) {
      // Allow other modules to alter the number of spaces remaining.
      $spaces_remaining = $capacity - $this->getActiveSpacesReserved($registration);
      $event = new RegistrationDataAlterEvent($spaces_remaining, [
        'host_entity' => $this,
        'settings' => $this->getSettings(),
        'registration' => $registration,
      ]);
      $this->eventDispatcher()->dispatch($event, RegistrationEvents::REGISTRATION_ALTER_SPACES_REMAINING);
      return $event->getData() ?? NULL;
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultSettings(?string $langcode = NULL): array {
    $entity_type_id = $this->getEntityTypeId();
    $bundle = $this->bundle();
    if (!$langcode) {
      $langcode = $this->getEntity()->language()->getId();
    }
    $fields = $this->entityFieldManager()->getFieldDefinitionsForLanguage($entity_type_id, $bundle, $langcode);
    foreach ($fields as $field) {
      if ($field->getType() == 'registration') {
        $settings = $field->getDefaultValueLiteral();
        // If the registration field has saved default values, return those.
        if (isset($settings[0], $settings[0]['registration_settings'])) {
          // Default settings are stored in configuration as a serialized array.
          // @see \Drupal\registration\Plugin\Field\RegistrationItemFieldItemList
          return RegistrationHelper::flatten(unserialize($settings[0]['registration_settings']));
        }
        else {
          /** @var \Drupal\registration\Plugin\Field\RegistrationItemFieldItemList $item_list */
          $item_list = $this->getEntity()->get($field->getName());
          // No defaults have been saved to the field. Use fallback settings.
          return RegistrationHelper::flatten($item_list->getFallbackSettings());
        }
      }
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getRegistrationCount(): int {
    $count = $this->getRegistrationQuery()->count()->execute();

    // Allow other modules to alter the count.
    $event = new RegistrationDataAlterEvent($count, [
      'host_entity' => $this,
      'settings' => $this->getSettings(),
    ]);
    $this->eventDispatcher()->dispatch($event, RegistrationEvents::REGISTRATION_ALTER_COUNT);
    return $event->getData() ?? 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getRegistrationField(): ?FieldDefinitionInterface {
    $fields = $this->entityFieldManager()->getFieldDefinitions($this->getEntityTypeId(), $this->bundle());
    foreach ($fields as $field) {
      if ($field->getType() == 'registration') {
        return $field;
      }
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getRegistrationList(array $states = [], ?string $langcode = NULL): array {
    $properties = [];
    if (!empty($states)) {
      $properties['state'] = $states;
    }

    // Filter on host entity language if a language code was not specified.
    if (!$langcode) {
      $langcode = $this->getEntity()->language()->getId();
    }
    // Do not filter on language if it would be "undefined" since nothing would
    // match.
    if ($langcode != 'und') {
      $properties['langcode'] = $langcode;
    }
    $ids = $this->getRegistrationQuery($properties)->execute();
    return $ids ? $this->entityTypeManager()->getStorage('registration')->loadMultiple($ids) : [];
  }

  /**
   * {@inheritdoc}
   */
  public function getRegistrationListCacheTag(): string {
    return 'registration_list.host_entity:' . $this->getEntityTypeId() . ':' . (string) $this->id();
  }

  /**
   * {@inheritdoc}
   */
  public function getRegistrationSettingsListCacheTag(): string {
    return 'registration_settings_list.host_entity:' . $this->getEntityTypeId() . ':' . (string) $this->id();
  }

  /**
   * {@inheritdoc}
   */
  public function getRegistrationQuery(array $properties = [], ?AccountInterface $account = NULL, ?string $email = NULL): QueryInterface {
    $query = $this->entityTypeManager()->getStorage('registration')->getQuery()
      ->accessCheck(FALSE)
      ->condition('entity_type_id', $this->getEntityTypeId())
      ->condition('entity_id', $this->id());

    // Add property conditions using same logic as
    // EntityStorageBase::loadByProperties().
    foreach ($properties as $name => $value) {
      // Cast scalars to array, so we can consistently use an IN condition.
      $query->condition($name, (array) $value, 'IN');
    }

    // Add special handling for identifying the registrant.
    $emails = [];
    $uids = [];
    if ($account) {
      $uids[] = $account->id();
      if ($account->getEmail()) {
        $emails[] = $account->getEmail();
      }
    }
    if ($email) {
      $emails[] = $email;
      // Check for other users based on provided email.
      if (!$account || $account->getEmail() !== $email) {
        $user_query = $this->entityTypeManager()->getStorage('user')->getQuery()->accessCheck(FALSE);
        $uids = array_merge($uids, $user_query->condition('mail', $email)->execute());
      }
    }
    if ($emails || $uids) {
      $orGroup = $query->orConditionGroup();
      if ($emails) {
        $orGroup->condition('anon_mail', $emails, 'IN');
      }
      if ($uids) {
        $orGroup->condition('user_uid', $uids, 'IN');
      }
      $query->condition($orGroup);
    }

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function getRegistrationType(): ?RegistrationTypeInterface {
    $registration_type = NULL;

    if ($bundle = $this->getRegistrationTypeBundle()) {
      $registration_type = RegistrationType::load($bundle);
    }

    return $registration_type;
  }

  /**
   * {@inheritdoc}
   */
  public function getRegistrationTypeBundle(): ?string {
    $bundle = NULL;

    if ($field = $this->getRegistrationField()) {
      if ($this->getEntity()->hasField($field->getName())) {
        if (!$this->getEntity()->get($field->getName())->isEmpty()) {
          $value = $this->getEntity()->get($field->getName())->getValue();
          if (!empty($value)) {
            $value = reset($value);
            if (is_array($value) && isset($value['registration_type'])) {
              $bundle = $value['registration_type'];
            }
          }
        }
      }
    }

    return $bundle;
  }

  /**
   * {@inheritdoc}
   */
  public function getSetting(string $key): mixed {
    if ($settings = $this->getSettings()) {
      return $settings->getSetting($key);
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettings(): ?RegistrationSettings {
    if (!isset($this->settings)) {
      $this->settings = NULL;
      if ($this->getRegistrationTypeBundle()) {
        /** @var \Drupal\registration\RegistrationSettingsStorage $storage */
        $storage = $this->entityTypeManager()->getStorage('registration_settings');
        $this->settings = $storage->loadSettingsForHostEntity($this);
      }
    }
    return $this->settings;
  }

  /**
   * {@inheritdoc}
   */
  public function hasRoom(int $spaces = 1, ?RegistrationInterface $registration = NULL): bool {
    if ($this->needsCapacityCheck($spaces, $registration)) {
      $capacity = $this->getSetting('capacity');
      if ($capacity) {
        $projected_usage = $this->getActiveSpacesReserved($registration) + $spaces;
        if (($capacity - $projected_usage) < 0) {
          return FALSE;
        }
      }
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function hasRoomForRegistration(int $spaces = 1, bool $return_as_object = FALSE): bool|RegistrationValidationResultInterface {
    $configuration = ['spaces' => $spaces];
    $validation_result = $this->validator()->execute('has_room_for_registration', [
      'HostHasSettings' => [],
      'HostIsOpen' => [],
      'HostIsEnabled' => [],
      'HostHasRoom' => $configuration,
      'HostAllowsRegistrant' => [],
    ], $this);
    return $return_as_object ? $validation_result : $validation_result->isValid();
  }

  /**
   * {@inheritdoc}
   */
  public function isAvailableForRegistration(bool $return_as_object = FALSE): bool|RegistrationValidationResultInterface {
    $validation_result = $this->validator()->execute('available_for_registration', [
      'HostHasSettings',
      'HostIsOpen',
      'HostIsEnabled',
      'HostHasRoom',
      'HostAllowsRegistrant',
    ], $this);
    return $return_as_object ? $validation_result : $validation_result->isValid();
  }

  /**
   * {@inheritdoc}
   */
  public function isOpenForRegistration(bool $return_as_object = FALSE): bool|RegistrationValidationResultInterface {
    $validation_result = $this->validator()->execute('open_for_registration', [
      'HostHasSettings',
      'HostIsOpen',
      'HostIsEnabled',
    ], $this);
    return $return_as_object ? $validation_result : $validation_result->isValid();
  }

  /**
   * {@inheritdoc}
   */
  public function isConfiguredForRegistration(): bool {
    return !is_null($this->getRegistrationTypeBundle());
  }

  /**
   * {@inheritdoc}
   */
  public function isEditableRegistration(RegistrationInterface $registration, ?AccountInterface $account = NULL, bool $return_as_object = FALSE): bool|RegistrationValidationResultInterface {
    $validation_result = $this->validator()->execute('editable_registration', [
      'HostHasSettings' => ['hostEntity' => $registration->getHostEntity()],
      'RegistrationIsEditable' => ['account' => $account],
    ], $registration);
    return $return_as_object ? $validation_result : $validation_result->isValid();
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabledForRegistration(int $spaces = 1, ?RegistrationInterface $registration = NULL, array &$errors = []): bool {
    @trigger_error('HostEntity::isEnabledForRegistration() is deprecated in registration:3.4.0 and is removed from registration:4.0.0. See https://www.drupal.org/node/3496339', E_USER_DEPRECATED);

    $validation_result = $this->validator()->execute('enabled_for_registration', [
      'HostHasSettings' => ['hostEntity' => $this],
      'HostIsOpen' => ['hostEntity' => $this],
      'HostIsEnabled' => ['hostEntity' => $this],
      'HostHasRoom' => ['hostEntity' => $this],
      'RegistrationWithinMaximumSpaces' => ['spaces' => $spaces],
    ], $registration ?? $this);

    $errors = $validation_result->getLegacyErrors();
    return $validation_result->isValid();
  }

  /**
   * {@inheritdoc}
   */
  public function isEmailRegistered(string $email): bool {
    @trigger_error('HostEntity::isEmailRegistered() is deprecated in registration:3.1.5 and is removed from registration:4.0.0. See https://www.drupal.org/node/3465690', E_USER_DEPRECATED);
    $states = [];

    if ($registration_type = $this->getRegistrationType()) {
      $states = $registration_type->getActiveOrHeldStates();
    }

    // Ensure we have active states before querying against them.
    if (empty($states)) {
      return FALSE;
    }

    $database = Database::getConnection();
    $query = $database->select('registration')
      ->condition('entity_id', $this->id())
      ->condition('entity_type_id', $this->getEntityTypeId())
      ->condition('anon_mail', $email)
      ->condition('state', array_keys($states), 'IN');

    $count = $query->countQuery()->execute()->fetchField();
    return ($count > 0);
  }

  /**
   * {@inheritdoc}
   */
  public function isEmailRegisteredInStates(string $email, array $states): bool {
    @trigger_error('HostEntity::isEmailRegisteredInStates() is deprecated in registration:3.1.5 and is removed from registration:4.0.0. See https://www.drupal.org/node/3465690', E_USER_DEPRECATED);
    // Ensure we have states before querying against them.
    if (empty($states)) {
      return FALSE;
    }

    $database = Database::getConnection();
    $query = $database->select('registration')
      ->condition('entity_id', $this->id())
      ->condition('entity_type_id', $this->getEntityTypeId())
      ->condition('anon_mail', $email)
      ->condition('state', $states, 'IN');

    $count = $query->countQuery()->execute()->fetchField();
    return ($count > 0);
  }

  /**
   * {@inheritdoc}
   */
  public function isUserRegistered(AccountInterface $account): bool {
    @trigger_error('HostEntity::isUserRegistered() is deprecated in registration:3.1.5 and is removed from registration:4.0.0. See https://www.drupal.org/node/3465690', E_USER_DEPRECATED);
    $states = [];

    if ($registration_type = $this->getRegistrationType()) {
      $states = $registration_type->getActiveOrHeldStates();
    }

    // Ensure we have active states before querying against them.
    if (empty($states)) {
      return FALSE;
    }

    $database = Database::getConnection();
    $query = $database->select('registration')
      ->condition('entity_id', $this->id())
      ->condition('entity_type_id', $this->getEntityTypeId())
      ->condition('user_uid', $account->id())
      ->condition('state', array_keys($states), 'IN');

    $count = $query->countQuery()->execute()->fetchField();
    return ($count > 0);
  }

  /**
   * {@inheritdoc}
   */
  public function isUserRegisteredInStates(AccountInterface $account, array $states): bool {
    @trigger_error('HostEntity::isUserRegisteredInStates() is deprecated in registration:3.1.5 and is removed from registration:4.0.0. See https://www.drupal.org/node/3465690', E_USER_DEPRECATED);
    // Ensure we have states before querying against them.
    if (empty($states)) {
      return FALSE;
    }

    $database = Database::getConnection();
    $query = $database->select('registration')
      ->condition('entity_id', $this->id())
      ->condition('entity_type_id', $this->getEntityTypeId())
      ->condition('user_uid', $account->id())
      ->condition('state', $states, 'IN');

    $count = $query->countQuery()->execute()->fetchField();
    return ($count > 0);
  }

  /**
   * {@inheritdoc}
   */
  public function isRegistrant(?AccountInterface $account = NULL, ?string $email = NULL, array $states = []): bool {
    if (!$account && !$email) {
      throw new \InvalidArgumentException("Either an account or an email must be passed to HostEntity::isRegistrant().");
    }

    // Default to active or held states if none specified.
    if (!$states && $registration_type = $this->getRegistrationType()) {
      $states = array_keys($registration_type->getActiveOrHeldStates());
    }
    // Ensure we have active states before querying against them.
    if (empty($states)) {
      return FALSE;
    }

    $query = $this->getRegistrationQuery(['state' => $states], $account, $email);
    return (!empty($query->execute()));
  }

  /**
   * {@inheritdoc}
   */
  public function isBeforeOpen(): bool {
    // Check open date.
    $open = $this->getOpenDate();
    if ($open) {
      $storage_timezone = new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE);
      $now = new DrupalDateTime('now', $storage_timezone);
    }
    return ($open && ($now < $open));
  }

  /**
   * {@inheritdoc}
   */
  public function isAfterClose(): bool {
    // Check close date.
    $close = $this->getCloseDate();
    if ($close) {
      $storage_timezone = new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE);
      $now = new DrupalDateTime('now', $storage_timezone);
    }
    return ($close && ($now >= $close));
  }

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value): RegistrationValidationResultInterface {
    // Validate a registration.
    if ($value instanceof RegistrationInterface) {
      // Setup configuration for those constraints that can take either a host
      // entity or a registration as the value, and require the host entity to
      // be passed as an option when the value is a registration.
      $configuration = ['hostEntity' => $this];

      // All registrations must have a host entity with settings.
      $pipeline = [
        'HostHasSettings' => $configuration,
      ];

      // Checks that apply to new registrations.
      if ($value->isNewToHost()) {
        $pipeline += [
          'HostIsOpen' => $configuration,
          'HostIsEnabled' => $configuration,
          'HostHasRoom' => $configuration,
        ];
      }

      // Checks that apply to all registrations.
      $pipeline += [
        'RegistrationIsEditable' => [],
        'RegistrationWithinMaximumSpaces' => $configuration,
        'RegistrationWithinCapacity' => [],
        'RegistrationAllowsRegistrant' => [],
        'RegistrationAllowsUpdate' => [],
        'UniqueRegistrant' => [],
      ];

      $validation_result = $this->validator()->execute('validate_registration', $pipeline, $value);
    }

    // Dispatch an event so other objects can be validated.
    $event = new RegistrationDataAlterEvent($validation_result ?? NULL, [
      'host_entity' => $this,
      'value' => $value,
    ]);
    $this->eventDispatcher()->dispatch($event, RegistrationEvents::REGISTRATION_ALTER_HOST_VALIDATION);

    /** @var \Drupal\registration\RegistrationValidationResultInterface $validation_result */
    $validation_result = $event->getData();

    // An object other than a registration was validated, but an event
    // subscriber to handle the validation was not provided.
    if (!isset($validation_result)) {
      throw new \InvalidArgumentException("Value could not be validated");
    }

    return $validation_result;
  }

  /**
   * Calculates a max-age based on the host entity open or close dates.
   *
   * If registration for the host entity has closed, or the host entity does
   * not have open or close dates, then NULL is returned.
   *
   * @return int|null
   *   The calculated max age, if available.
   */
  protected function calculateMaxAge(): ?int {
    $expiration = NULL;

    // Expire this validation result on the open date if one exists and it's in
    // the future.
    if ($this->isBeforeOpen()) {
      $expiration = $this->getOpenDate();
    }

    // Expire this validation result on the close date if one exists and it's in
    // the future.
    elseif (($close = $this->getCloseDate()) && !$this->isAfterClose()) {
      $expiration = $close;
    }

    // If an open or close date in the future was found, calculate the amount
    // of time before the relevant date, and use that as the max age.
    if ($expiration) {
      return $expiration->getTimestamp() - $this->time()->getCurrentTime();
    }

    return NULL;
  }

  /**
   * Returns the current user.
   *
   * @return \Drupal\Core\Session\AccountInterface|\Drupal\Core\Session\AccountProxy
   *   The current user.
   */
  protected function currentUser(): AccountInterface|AccountProxy {
    if (!isset($this->currentUser)) {
      $this->currentUser = $this->container()->get('current_user');
    }
    return $this->currentUser;
  }

  /**
   * Retrieves the entity field manager.
   *
   * @return \Drupal\registration\RegistrationFieldManagerInterface
   *   The entity field manager.
   */
  protected function entityFieldManager(): RegistrationFieldManagerInterface {
    if (!isset($this->entityFieldManager)) {
      $this->entityFieldManager = $this->container()->get('registration.field_manager');
    }
    return $this->entityFieldManager;
  }

  /**
   * Retrieves the entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  protected function entityTypeManager(): EntityTypeManagerInterface {
    if (!isset($this->entityTypeManager)) {
      $this->entityTypeManager = $this->container()->get('entity_type.manager');
    }
    return $this->entityTypeManager;
  }

  /**
   * Retrieves the event dispatcher.
   *
   * @return \Symfony\Contracts\EventDispatcher\EventDispatcherInterface
   *   The event dispatcher.
   */
  protected function eventDispatcher(): EventDispatcherInterface {
    if (!isset($this->eventDispatcher)) {
      $this->eventDispatcher = $this->container()->get('event_dispatcher');
    }
    return $this->eventDispatcher;
  }

  /**
   * Determines if a registration needs a capacity check.
   *
   * @param int $spaces
   *   The number of spaces requested.
   * @param \Drupal\registration\Entity\RegistrationInterface|null $registration
   *   (optional) If set, an existing registration to check.
   *
   * @return bool
   *   TRUE if a check is needed, FALSE otherwise.
   */
  protected function needsCapacityCheck(int $spaces, ?RegistrationInterface $registration): bool {
    if ($registration) {
      return $registration->requiresCapacityCheck() || ($spaces > $registration->getSpacesReserved());
    }
    return TRUE;
  }

  /**
   * Retrieves the renderer.
   *
   * @return \Drupal\Core\Render\Renderer
   *   The renderer.
   */
  protected function renderer(): Renderer {
    if (!isset($this->renderer)) {
      $this->renderer = $this->container()->get('renderer');
    }
    return $this->renderer;
  }

  /**
   * Returns the time service.
   *
   * @return \Drupal\Component\Datetime\TimeInterface
   *   The time service.
   */
  protected function time(): TimeInterface {
    if (!isset($this->time)) {
      $this->time = $this->container()->get('datetime.time');
    }
    return $this->time;
  }

  /**
   * Retrieves the registration validator.
   *
   * @return \Drupal\registration\RegistrationValidatorInterface
   *   The registration validator.
   */
  protected function validator(): RegistrationValidatorInterface {
    if (!isset($this->validator)) {
      $this->validator = $this->container()->get('registration.validator');
    }
    return $this->validator;
  }

  /**
   * Returns the service container.
   *
   * This method is marked private to prevent subclasses from retrieving
   * services from the container through it. Instead,
   * \Drupal\Core\DependencyInjection\ContainerInjectionInterface should be used
   * for injecting services.
   *
   * @return \Symfony\Component\DependencyInjection\ContainerInterface
   *   The service container.
   */
  private function container(): ContainerInterface {
    return \Drupal::getContainer();
  }

}
