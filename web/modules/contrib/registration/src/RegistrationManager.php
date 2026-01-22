<?php

namespace Drupal\registration;

use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\registration\Entity\RegistrationInterface;
use Drupal\registration\Entity\RegistrationSettings;
use Drupal\registration\Event\RegistrationDataAlterEvent;
use Drupal\registration\Event\RegistrationEvents;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\Routing\Route;

/**
 * Defines the class for the registration manager service.
 */
class RegistrationManager implements RegistrationManagerInterface {

  use StringTranslationTrait;

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected AccountProxy $currentUser;

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected EntityDisplayRepositoryInterface $entityDisplayRepository;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected EntityFieldManager $entityFieldManager;

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfo
   */
  protected EntityTypeBundleInfo $entityTypeBundleInfo;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The route provider.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected RouteProviderInterface $routeProvider;

  /**
   * Creates a RegistrationManager object.
   *
   * @param \Drupal\Core\Session\AccountProxy $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   * @param \Drupal\Core\Entity\EntityFieldManager $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfo $entity_type_bundle_info
   *   The entity type bundle info.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Routing\RouteProviderInterface $route_provider
   *   The route provider.
   */
  public function __construct(AccountProxy $current_user, EntityDisplayRepositoryInterface $entity_display_repository, EntityFieldManager $entity_field_manager, EntityTypeBundleInfo $entity_type_bundle_info, EntityTypeManagerInterface $entity_type_manager, RouteProviderInterface $route_provider) {
    $this->currentUser = $current_user;
    $this->entityDisplayRepository = $entity_display_repository;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->entityTypeManager = $entity_type_manager;
    $this->routeProvider = $route_provider;
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseRouteName(EntityTypeInterface $entity_type): ?string {
    $base_route = NULL;

    if ($template = $this->getBaseTemplate($entity_type)) {
      $entity_type_id = $entity_type->id();
      // Must convert edit-form to edit_form.
      $base_route = "entity.$entity_type_id." . str_replace('-', '_', $template);
    }

    return $base_route;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityFromParameters(ParameterBag $parameters, bool $return_host_entity = FALSE): EntityInterface|HostEntityInterface|NULL {
    $entity = NULL;

    foreach ($parameters as $parameter) {
      if ($parameter instanceof EntityInterface) {
        if ($parameter->getEntityType()->entityClassImplements(FieldableEntityInterface::class)) {
          $entity = $parameter;
          break;
        }
      }
    }

    // Wrap the entity if requested.
    if ($entity && !($entity instanceof RegistrationInterface) && $return_host_entity) {
      $handler = $this->entityTypeManager->getHandler($entity->getEntityTypeId(), 'registration_host_entity');
      // Although the createHostEntity function takes a langcode parameter,
      // it is not necessary here, since the entity loaded in the parameter
      // bag has the appropriate language already set from the route match.
      $entity = $handler->createHostEntity($entity);
    }

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldConfigSetting(EntityTypeInterface $entity_type, string $key): mixed {
    $setting_value = NULL;

    if ($entity_type->entityClassImplements(FieldableEntityInterface::class)) {
      $entity_type_id = $entity_type->id();
      $bundle_info = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);
      // If there are multiple bundles with a registration field, the field
      // instance for the last bundle will determine the setting value. This
      // is not ideal but replicates the behavior of the D7 module.
      foreach ($bundle_info as $bundle => $info) {
        $fields = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle);
        foreach ($fields as $field) {
          if ($field->getType() == 'registration') {
            $value = $this->getFormDisplaySetting($entity_type, $field, $key, $bundle);
            if (!is_null($value)) {
              $setting_value = $value;
            }
          }
        }
      }
    }

    return $setting_value;
  }

  /**
   * {@inheritdoc}
   */
  public function getRegistrantOptions(RegistrationInterface $registration, RegistrationSettings $settings): array {
    $options = [];
    $host_entity = $registration->getHostEntity();

    // Me:
    $my_registration = ($registration->getUserId() == $this->currentUser->id());
    $allow_multiple = $settings->getSetting('multiple_registrations');
    if ($this->currentUser->isAuthenticated()
      && $host_entity->access('register self', $this->currentUser)
      && ($my_registration || $allow_multiple || !$host_entity->isRegistrant($this->currentUser))
    ) {
      $options[RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_ME] = $this->t('Myself');
    }

    // Other users:
    $user_is_anonymous = $this->currentUser->isAnonymous();
    if ($host_entity->access('register other users', $this->currentUser) && !$user_is_anonymous) {
      $options[RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_USER] = $this->t('Other account');
    }

    // Other anonymous people:
    if ($host_entity->access('register other anonymous', $this->currentUser) && !$user_is_anonymous) {
      $options[RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_ANON] = $this->t('Other person');
    }

    // Anonymous self-registration:
    if ($user_is_anonymous && $host_entity->access('register self', $this->currentUser)) {
      $options[RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_ANON] = $this->t('Myself');
    }

    // Allow the current option if editing an existing registration.
    if (!$registration->isNew()) {
      if (!$user_is_anonymous && $my_registration) {
        $options[RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_ME] = $this->t('Myself');
      }
      elseif (!$user_is_anonymous && $registration->getUserId()) {
        $options[RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_USER] = $this->t('Other account');
      }
      elseif ($registration->getAnonymousEmail()) {
        $options[RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_ANON] = $this->t('Other person');
      }
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function getRegistrationEnabledEntityTypes(): array {
    $entity_types = [];

    $definitions = $this->entityTypeManager->getDefinitions();
    foreach ($definitions as $id => $definition) {
      if ($this->hasRegistrationField($definition)) {
        $entity_types[$id] = $definition;
      }
    }

    return $entity_types;
  }

  /**
   * {@inheritdoc}
   */
  public function getRegistrationFieldDefinitions(): array {
    $field_definitions = [];

    $entity_types = $this->getRegistrationEnabledEntityTypes();
    foreach ($entity_types as $entity_type_id => $entity_type) {
      $bundle_info = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);
      foreach ($bundle_info as $type => $info) {
        $fields = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $type);
        foreach ($fields as $field) {
          if ($field->getType() == 'registration') {
            $field_definitions[] = $field;
          }
        }
      }
    }

    return $field_definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function getRoute(EntityTypeInterface $entity_type, string $route_id): ?Route {
    $path = $this->getBasePath($entity_type);
    if (!$path) {
      return NULL;
    }

    // Build the 'manage' route and adjust for other routes.
    $route = $this->buildManageRoute($entity_type, $path);
    switch ($route_id) {
      case 'broadcast':
        $route
          ->setPath($route->getPath() . '/broadcast')
          ->setDefaults([
            '_form' => '\Drupal\registration\Form\EmailRegistrantsForm',
            '_title' => 'Email registrants',
          ]);
        break;

      case 'manage':
        break;

      case 'register':
        $route
          ->setPath($path . '/register')
          ->setDefaults([
            '_entity_form' => 'registration.register',
            '_title' => 'Register',
          ])
          ->setRequirements([
            '_register_access_check' => 'TRUE',
          ]);
        break;

      case 'settings':
        $route
          ->setPath($route->getPath() . '/settings')
          ->setDefaults([
            '_entity_form' => 'registration_settings.edit',
            '_title' => 'Registration settings',
          ]);
        break;
    }

    return $route;
  }

  /**
   * {@inheritdoc}
   */
  public function getWorkflowStateOptions(bool $show_on_form_only = FALSE): array {
    $states = [];
    $workflows = $this->entityTypeManager->getStorage('workflow')->loadByProperties([
      'type' => 'registration',
    ]);
    foreach ($workflows as $workflow) {
      $all_states = $workflow->getTypePlugin()->getStates();
      foreach ($all_states as $id => $state) {
        /** @var \Drupal\registration\RegistrationState $state */
        if (!$show_on_form_only || $state->isShownOnForm()) {
          $states[$id] = $state->label();
        }
      }
    }
    return $states;
  }

  /**
   * {@inheritdoc}
   */
  public function hasRegistrationField(EntityTypeInterface $entity_type, $bundle = NULL): bool {
    if ($entity_type->entityClassImplements(FieldableEntityInterface::class)) {
      $entity_type_id = $entity_type->id();
      $bundle_info = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);
      foreach ($bundle_info as $type => $info) {
        $fields = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $type);
        foreach ($fields as $field) {
          if ($field->getType() == 'registration') {
            if (is_null($bundle) || ($type == $bundle)) {
              return TRUE;
            }
          }
        }
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function userHasRegistrations(UserInterface $user): bool {
    $database = Database::getConnection();
    $query = $database->select('registration')
      ->condition('user_uid', $user->id());

    $count = $query->countQuery()->execute()->fetchField();

    // Allow other modules to alter the count.
    $event = new RegistrationDataAlterEvent($count, [
      'user' => $user,
    ]);
    \Drupal::service('event_dispatcher')->dispatch($event, RegistrationEvents::REGISTRATION_ALTER_COUNT);
    return $event->getData() ?? 0;
  }

  /**
   * Build the manage registrations route for an entity type and base path.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   * @param string $path
   *   The base path.
   *
   * @return \Symfony\Component\Routing\Route
   *   The generated route.
   */
  protected function buildManageRoute(EntityTypeInterface $entity_type, string $path): Route {
    $entity_type_id = $entity_type->id();
    $route = new Route($path . '/registrations');
    $route
      ->addDefaults([
        '_controller' => '\Drupal\registration\Controller\RegistrationController::manageRegistrations',
        '_title' => 'Manage Registrations',
      ])
      ->addRequirements([
        '_manage_registrations_access_check' => 'TRUE',
      ])
      ->setOption('_admin_route', TRUE)
      ->setOption('parameters', [
        $entity_type_id => ['type' => 'entity:' . $entity_type_id],
      ]);

    return $route;
  }

  /**
   * Gets the path for an entity type that registration routes will be based on.
   *
   * Returns NULL unless the type has a bundle with a registration field.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return string|null
   *   The base path, if available.
   */
  protected function getBasePath(EntityTypeInterface $entity_type): ?string {
    if (($template = $this->getBaseTemplate($entity_type)) && $this->hasRegistrationField($entity_type)) {
      $path = $entity_type->getLinkTemplate($template);

      // Truncate 'edit' if using the edit-form link template.
      $edit = '/edit';
      if (str_ends_with($path, $edit)) {
        $path = substr($path, 0, strlen($path) - strlen($edit));
      }

      return $path;
    }

    return NULL;
  }

  /**
   * Gets the base template for an entity type.
   *
   * This is typically 'canonical', but falls back to 'edit-form'.
   * Returns NULL if the entity type has neither.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return string|null
   *   The base template name, if available.
   */
  protected function getBaseTemplate(EntityTypeInterface $entity_type): ?string {
    // @todo Allow non-standard template for custom entities via hook or event.
    $base_template = NULL;

    // Find a suitable link template for use in base route construction. Most
    // entity types have a canonical template, but not all. Use canonical if
    // available, otherwise fallback to the edit form link if it exists.
    $templates = [
      'canonical',
      'edit-form',
    ];
    foreach ($templates as $template) {
      if ($entity_type->hasLinkTemplate($template)) {
        $base_template = $template;
        break;
      }
    }

    return $base_template;
  }

  /**
   * Gets the value of a setting from a registration form display.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field
   *   The field definition for a registration field.
   * @param string $key
   *   The setting name, for example "hide_register_tab".
   * @param string $bundle
   *   The bundle name. For entity types without bundles, use entity type ID.
   *
   * @return mixed
   *   The setting value. The data type depends on the key.
   */
  protected function getFormDisplaySetting(EntityTypeInterface $entity_type, FieldDefinitionInterface $field, string $key, string $bundle): mixed {
    $entity_type_id = $entity_type->id();

    // Check default first, then other form modes that exist.
    $form_modes = ['default' => ''];
    $form_modes += $this->entityDisplayRepository->getFormModes($entity_type_id);
    foreach (array_keys($form_modes) as $form_mode) {
      $form_display = $this->entityDisplayRepository->getFormDisplay($entity_type_id, $bundle, $form_mode);
      if ($form_display) {
        $component = $form_display->getComponent($field->getName());
        if (isset($component, $component['settings'], $component['settings'][$key])) {
          return $component['settings'][$key];
        }
      }
    }

    return NULL;
  }

  /**
   * Gets the settings field for a given entity type and bundle.
   *
   * @param string $entity_type_id
   *   The entity type ID, e.g. "node".
   * @param string $bundle
   *   The bundle name. For entity types without bundles, use entity type ID.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface|null
   *   The settings field definition, if available.
   */
  protected static function getSettingsField(string $entity_type_id, string $bundle): ?FieldDefinitionInterface {
    $fields = \Drupal::service('entity_field.manager')->getFieldDefinitions($entity_type_id, $bundle);
    foreach ($fields as $field) {
      if ($field->getType() == 'registration_settings') {
        return $field;
      }
    }
    return NULL;
  }

}
