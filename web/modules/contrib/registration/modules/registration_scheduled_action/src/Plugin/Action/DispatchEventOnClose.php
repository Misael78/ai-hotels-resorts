<?php

namespace Drupal\registration_scheduled_action\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\ConfigurableActionBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\registration\Event\RegistrationEvents;
use Drupal\registration\Event\RegistrationSettingsEvent;
use Drupal\registration\HostEntityInterface;
use Drupal\registration_scheduled_action\Action\QueryableActionInterface;
use Drupal\registration_scheduled_action\Entity\ScheduledActionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Provides an action to dispatch an event when the close date is reached.
 *
 * The event is dispatched regardless of the value of the "status" field.
 *
 * @Action(
 *   id = "dispatch_event_on_close_action",
 *   label = @Translation("Dispatch registration close event"),
 *   type = "registration",
 * )
 */
class DispatchEventOnClose extends ConfigurableActionBase implements ContainerFactoryPluginInterface, QueryableActionInterface {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

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
  protected EventDispatcherInterface $dispatcher;

  /**
   * DispatchEventOnClose constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $dispatcher
   *   The event dispatcher.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Connection $database, EntityTypeManagerInterface $entity_type_manager, EventDispatcherInterface $dispatcher) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->database = $database;
    $this->entityTypeManager = $entity_type_manager;
    $this->dispatcher = $dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): DispatchEventOnClose {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database'),
      $container->get('entity_type.manager'),
      $container->get('event_dispatcher')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function execute($object = NULL) {
    if ($object && ($host_entity = $this->getHostEntity($object))) {
      $event = new RegistrationSettingsEvent($host_entity->getSettings());
      $this->dispatcher->dispatch($event, RegistrationEvents::REGISTRATION_SETTINGS_CLOSE);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = NULL;
    $host_entity = NULL;
    if ($object && ($host_entity = $this->getHostEntity($object))) {
      $result = AccessResult::allowedIf($host_entity->isConfiguredForRegistration());
    }

    if (!$result) {
      $result = AccessResult::forbidden();
    }

    // Recalculate this result if the host entity is updated.
    if ($host_entity) {
      $result->addCacheableDependency($host_entity);
    }

    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function getAllowedPositions(): array {
    // The event can be dispatched before or after the settings close date.
    return ['before', 'after'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDateFieldLabel(): string {
    return $this->t('Registration settings close date');
  }

  /**
   * {@inheritdoc}
   */
  public function getKeyValueStoreCollectionName(): string {
    return 'registration_scheduled_action.registration_settings_close';
  }

  /**
   * {@inheritdoc}
   */
  public function getKeyValueStoreExpirationTime(): int {
    // Store key value entries for 48 hours to prevent re-processing of items
    // within that period. By the time entries expire, the query filter will
    // be selecting different items.
    return 60 * 60 * 48;
  }

  /**
   * {@inheritdoc}
   */
  public function getQuery(ScheduledActionInterface $scheduled_action): SelectInterface {
    $query = $this->database
      ->select('registration_settings_field_data', 'r')
      ->fields('r');

    // Restrict to a specific language if the action specified one.
    $langcode = $scheduled_action->getTargetLangcode();
    if ($langcode != LanguageInterface::LANGCODE_NOT_SPECIFIED) {
      $query->condition('r.langcode', $langcode);
    }

    // Compare the scheduled action date criteria to the registration settings
    // close date.
    $date_times = $scheduled_action->getDateTimeArrayForQuery();
    $query->condition('r.close', $date_times, 'BETWEEN');
    $query->orderBy('r.settings_id');

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function getQueryUniqueKeyColumnName(): string {
    return 'settings_id';
  }

  /**
   * Gets the host entity for a scheduled action.
   *
   * @param mixed $object
   *   The object that the scheduled action is being executed against.
   *
   * @return \Drupal\registration\HostEntityInterface|null
   *   The host entity, if available.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getHostEntity(mixed $object): ?HostEntityInterface {
    $storage = $this->entityTypeManager->getStorage('registration_settings');
    if ($settings = $storage->load($object->settings_id)) {
      /** @var \Drupal\registration\Entity\RegistrationSettings $settings */
      return $settings->getHostEntity();
    }
    return NULL;
  }

}
