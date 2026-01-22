<?php

namespace Drupal\registration_change_host;

use Drupal\Core\Cache\RefinableCacheableDependencyTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\registration\Entity\RegistrationInterface;
use Drupal\registration\HostEntityInterface;
use Drupal\registration\RegistrationValidationResult;
use Drupal\registration\RegistrationValidationResultInterface;

/**
 * Provides a base class for possible host entities.
 */
class PossibleHostEntity implements PossibleHostEntityInterface {

  use RefinableCacheableDependencyTrait;
  use StringTranslationTrait;

  /**
   * Entity id.
   *
   * @var string|int|null
   */
  protected string|int|NULL $id;

  /**
   * Entity type id.
   *
   * @var string
   */
  protected string $entityTypeId;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Possible host entity.
   *
   * @var \Drupal\registration\HostEntityInterface
   */
  protected HostEntityInterface $hostEntity;

  /**
   * Current host entity.
   *
   * @var \Drupal\registration\HostEntityInterface
   */
  protected HostEntityInterface $currentHostEntity;

  /**
   * Possible host label.
   *
   * @var string|null
   */
  protected ?string $label;

  /**
   * Possible host description.
   *
   * @var string|null
   */
  protected ?string $description;

  /**
   * Possible host URL.
   *
   * @var \Drupal\Core\Url
   */
  protected Url $url;

  /**
   * Whether this is the registration's current host.
   *
   * @var bool
   */
  protected bool $current = FALSE;

  /**
   * Validation result for this as a possible host for this registration.
   *
   * @var \Drupal\registration\RegistrationValidationResultInterface
   */
  protected RegistrationValidationResultInterface $validationResult;

  /**
   * The registration as it would be if changed to the host.
   *
   * This is an unsaved clone of the original registration.
   *
   * @var \Drupal\registration\Entity\RegistrationInterface
   */
  protected RegistrationInterface $registration;

  /**
   * The registration id.
   *
   * @var int
   */
  protected int $registrationId;

  /**
   * The entity underlying the host.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected EntityInterface $entity;

  /**
   * The registration change host manager.
   *
   * @var \Drupal\registration_change_host\RegistrationChangeHostManagerInterface
   */
  protected RegistrationChangeHostManagerInterface $registrationChangeHostManager;

  /**
   * Creates a PossibleHostEntity object.
   *
   * @param \Drupal\registration\HostEntityInterface|EntityInterface $host
   *   The host.
   * @param \Drupal\registration\Entity\RegistrationInterface $registration
   *   The registration.
   */
  public function __construct($host, RegistrationInterface $registration) {
    if ($host instanceof EntityInterface) {
      $this->entity = $host;
    }
    elseif (!$host instanceof HostEntityInterface) {
      throw new \InvalidArgumentException('Host entity passed to __construct() must implement EntityInterface or HostEntityInterface');
    }
    $this->id = $host->id();
    $this->entityTypeId = $host->getEntityTypeId();

    // The entity underlying the host is used as a source for possible
    // host properties like the default label.
    $this->addCacheableDependency($this->getEntity());

    $this->setRegistration($registration);
  }

  /**
   * Sets the registration and changes the host if necessary.
   *
   * @param \Drupal\registration\Entity\RegistrationInterface $registration
   *   The registration.
   */
  protected function setRegistration(RegistrationInterface $registration) {
    $this->registrationId = $registration->id();
    $this->currentHostEntity = $registration->getHostEntity();
    $this->registration = $registration;
    if ($this->getHostEntity()->isConfiguredForRegistration() && !$this->isCurrent()) {
      $this->registration = $this->registrationChangeHostManager()->changeHost($registration, $this->getHostEntity()->getEntityTypeId(), $this->getHostEntity()->id(), TRUE);
    }
  }

  /**
   * Build a host entity for the current language.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return \Drupal\registration\HostEntityInterface
   *   The host entity.
   */
  protected function buildHostEntity(EntityInterface $entity) {
    $handler = \Drupal::entityTypeManager()->getHandler($entity->getEntityTypeId(), 'registration_host_entity');
    $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $host_entity = $handler->createHostEntity($entity, $langcode);
    return $host_entity;
  }

  /**
   * {@inheritdoc}
   */
  public function id(): string|int|NULL {
    return $this->id;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeId(): string {
    return $this->entityTypeId;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntity(): EntityInterface {
    if (!isset($this->entity)) {
      $this->entity = \Drupal::entityTypeManager()->getStorage($this->getEntityTypeId())->load($this->id());
    }
    return $this->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getHostEntity(): HostEntityInterface {
    if (!isset($this->hostEntity)) {
      $this->hostEntity = $this->buildHostEntity($this->getEntity());
    }
    return $this->hostEntity;
  }

  /**
   * {@inheritdoc}
   */
  public function isAvailable(bool $return_as_object = FALSE): bool|RegistrationValidationResultInterface {
    if (!isset($this->validationResult)) {
      if ($this->isCurrent()) {
        // Whether or not the current host is offered as a possible host,
        // other validation criteria are never relevant to it. By default it is
        // excluded, but this can be overridden by possible host event
        // subscribers.
        $this->validationResult = new RegistrationValidationResult([], $this->registration);
        $this->validationResult->addViolation('Host cannot be changed to current host.', [], NULL, NULL, NULL, 'current_host', $this->t('Currently registered'));
      }
      else {
        // Validate the host.
        $this->validationResult = $this->getHostEntity()->validate($this->registration);

        // Add a data loss violation if appropriate.
        if ($this->getHostEntity()->isConfiguredForRegistration()) {
          $storage = $this->entityTypeManager()->getStorage('registration');
          $original_registration = $storage->loadUnchanged($this->registration->id());
          $original_host_entity = $original_registration->getHostEntity();
          $original_registration_type = $original_host_entity->getRegistrationType();
          $this->validationResult->addCacheableDependency($original_registration_type);
          if (!$original_registration_type->getThirdPartySetting('registration_change_host', 'allow_data_loss')) {
            if ($this->registrationChangeHostManager()->isDataLostWhenHostChanges($this->registration, $this->getEntityTypeId(), $this->id())) {
              $this->validationResult->addViolation('Host cannot be changed to %host_label because data will be lost as there are non-empty fields on the registration that are not present on the %type registration type.', [
                '%label' => $this->label(),
                '%type' => $this->getHostEntity()->getRegistrationType()->label(),
              ], NULL, NULL, NULL, 'data_loss', $this->t('Incompatible.'));
            }
          }
        }

        // Allow new host to reject changes.
        // This is allowed unless explicitly forbidden.
        $host_access = $this->getHostEntity()->access('accept changed host', NULL, TRUE);
        if ($host_access->isForbidden()) {
          $this->validationResult->addViolation('Changing an existing registration to %host_label is not allowed.', [
            '%label' => $this->label(),
          ], NULL, NULL, NULL, 'new_host_disallows', $this->t('Not allowed.'));
        }
      }

      $this->validationResult->addCacheableDependency($this->registration);
      // The violations are part of the renderable information about the
      // possible host.
      $this->addCacheableDependency($this->validationResult);
    }
    return $return_as_object ? $this->validationResult : $this->validationResult->isValid();
  }

  /**
   * {@inheritdoc}
   */
  public function label(): ?string {
    return !empty($this->label) ? $this->label : $this->getHostEntity()->label();
  }

  /**
   * {@inheritdoc}
   */
  public function setLabel(?string $label = NULL): PossibleHostEntityInterface {
    $this->label = $label;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): ?string {
    return $this->description ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setDescription(?string $description = NULL): PossibleHostEntityInterface {
    $this->description = $description;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getUrl(): ?Url {
    if (!isset($this->url)) {
      // By default the current host is not available as it's not a change of
      // host. But if this is overridden and made available, it should link to
      // the edit form not the change host form.
      if ($this->isCurrent()) {
        $this->setUrl($this->registration->toUrl('edit-form'));
      }
      else {
        $this->setUrl(Url::fromRoute('registration_change_host.change_host_form', [
          'registration' => $this->registrationId,
          'host_type_id' => $this->getEntityTypeId(),
          'host_id' => $this->id(),
        ]));
      }
    }
    return $this->url;
  }

  /**
   * {@inheritdoc}
   */
  public function setUrl($url = NULL): PossibleHostEntityInterface {
    $this->url = $url;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isCurrent(): bool {
    return ($this->currentHostEntity->id() === $this->id()) && ($this->currentHostEntity->getEntityTypeId() === $this->getEntityTypeId());
  }

  /**
   * {@inheritdoc}
   */
  public function __sleep() {
    if (!empty($this->registration)) {
      // @phpstan-ignore-next-line
      $this->_registrationId = $this->registration->id();
      unset($this->registration);
    }

    $properties = get_object_vars($this);
    $computed_properties = ['entity', 'validationResult', 'hostEntity'];
    foreach ($computed_properties as $property) {
      if (isset($properties[$property])) {
        unset($properties[$property]);
      }
    }

    return array_keys($properties);
  }

  /**
   * {@inheritdoc}
   */
  public function __wakeup() {
    if ($this->id && $this->entityTypeId) {
      $entity_storage = \Drupal::entityTypeManager()->getStorage($this->entityTypeId);
      $entity = $entity_storage->load($this->id);
      $this->hostEntity = $this->buildHostEntity($entity);
    }
    if (!empty($this->_registrationId)) {
      $registration_storage = \Drupal::entityTypeManager()->getStorage('registration');
      /** @var \Drupal\registration\Entity\RegistrationInterface $registration */
      $registration = $registration_storage->load($this->_registrationId);
      unset($this->_registrationId);
      $this->setRegistration($registration);
    }
  }

  /**
   * Retrieves the entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  protected function entityTypeManager(): EntityTypeManagerInterface {
    if (!isset($this->entityTypeManager)) {
      $this->entityTypeManager = \Drupal::getContainer()->get('entity_type.manager');
    }
    return $this->entityTypeManager;
  }

  /**
   * Retrieves the registration change host manager.
   *
   * @return \Drupal\registration_change_host\RegistrationChangeHostManagerInterface
   *   The registration change host manager.
   */
  protected function registrationChangeHostManager(): RegistrationChangeHostManagerInterface {
    if (!isset($this->registrationChangeHostManager)) {
      $this->registrationChangeHostManager = \Drupal::getContainer()->get('registration_change_host.manager');
    }
    return $this->registrationChangeHostManager;
  }

}
