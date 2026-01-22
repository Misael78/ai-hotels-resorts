<?php

namespace Drupal\registration;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\registration\Entity\RegistrationInterface;
use Drupal\registration\Entity\RegistrationSettings;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\Routing\Route;

/**
 * Defines the interface for the registration manager service.
 */
interface RegistrationManagerInterface {

  /**
   * Gets the base route name for an entity type.
   *
   * This is typically a canonical route, or an edit-form route as a fallback.
   * For example, 'entity.node.canonical'.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return string|null
   *   The base route name, if available.
   */
  public function getBaseRouteName(EntityTypeInterface $entity_type): ?string;

  /**
   * Gets the first upcasted entity object from a parameter bag.
   *
   * This function should typically be used for requests with a single object.
   *
   * @param \Symfony\Component\HttpFoundation\ParameterBag $parameters
   *   The parameter bag from a request object.
   * @param bool $return_host_entity
   *   (optional) Whether to return the entity or a wrapped host entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface|\Drupal\registration\HostEntityInterface|null
   *   Either the entity, a wrapped host entity, or NULL if not found.
   *
   * @see https://www.drupal.org/docs/8/api/routing-system/parameters-in-routes/using-parameters-in-routes
   */
  public function getEntityFromParameters(ParameterBag $parameters, bool $return_host_entity = FALSE): EntityInterface|HostEntityInterface|NULL;

  /**
   * Gets a setting from registration fields associated with an entity type.
   *
   * Retrieved from the form display containing the registration field widget.
   * For BC with the Drupal 7 version of the module, if the entity type
   * has bundles, and multiple bundles have registration fields, then the
   * setting from the last bundle field is returned. This function is called
   * when local tasks (tabs) are being derived for registration related routes.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   * @param string $key
   *   The setting name, for example "hide_register_tab".
   *
   * @return mixed
   *   The setting value. The data type depends on the key.
   */
  public function getFieldConfigSetting(EntityTypeInterface $entity_type, string $key): mixed;

  /**
   * Determines who can register when a registration is added or edited.
   *
   * @param \Drupal\registration\Entity\RegistrationInterface $registration
   *   The registration.
   * @param \Drupal\registration\Entity\RegistrationSettings $settings
   *   The registration settings entity.
   *
   * @return array
   *   An array keyed by registrant constants.
   *
   * @see \Drupal\registration\Entity\RegistrationInterface
   */
  public function getRegistrantOptions(RegistrationInterface $registration, RegistrationSettings $settings): array;

  /**
   * Gets the entity types that have bundles with registration fields.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface[]
   *   An array of entity type definitions indexed by machine name.
   */
  public function getRegistrationEnabledEntityTypes(): array;

  /**
   * Gets a list of installed registration fields.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface[]
   *   An array of field definitions.
   */
  public function getRegistrationFieldDefinitions(): array;

  /**
   * Gets a registration related route for an entity type.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   * @param string $route_id
   *   The route id: one of 'broadcast', 'manage', 'register' or 'settings'.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  public function getRoute(EntityTypeInterface $entity_type, string $route_id): ?Route;

  /**
   * Gets the available state options across all registration workflows.
   *
   * The options are suitable for use in a form select element.
   *
   * If more than one registration workflow is defined, and some states in
   * those workflows have the same machine name but different display names,
   * which display name is used is indeterminate.
   *
   * To get the available state options for a single workflow, use the
   * getStateOptions() method available on the workflow type plugin.
   *
   * @param bool $show_on_form_only
   *   Whether only states designated as "show on form" should be included.
   *   Defaults to FALSE, which includes all states.
   *
   * @return array
   *   The states as an options array of labels keyed by ID.
   *
   * @see \Drupal\registration\Plugin\WorkflowType\Registration::getStateOptions
   */
  public function getWorkflowStateOptions(bool $show_on_form_only = FALSE): array;

  /**
   * Determines if an entity type has a bundle with a registration field.
   *
   * If a bundle name is also passed, then the specific bundle is checked.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type, for example "node".
   * @param string|null $bundle
   *   (optional) A bundle machine name, for example "event".
   *
   * @return bool
   *   TRUE if the entity type has a bundle with a registration field,
   *   FALSE otherwise. If a bundle name is also provided, then TRUE
   *   is only returned if the specific bundle has a registration field.
   */
  public function hasRegistrationField(EntityTypeInterface $entity_type, ?string $bundle = NULL): bool;

  /**
   * Determines if a user has any registrations.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user entity.
   *
   * @return bool
   *   TRUE if the user has registrations (of any type), FALSE otherwise.
   */
  public function userHasRegistrations(UserInterface $user): bool;

}
