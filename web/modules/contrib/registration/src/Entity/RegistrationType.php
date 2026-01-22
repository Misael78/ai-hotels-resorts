<?php

namespace Drupal\registration\Entity;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\workflows\StateInterface;
use Drupal\workflows\WorkflowInterface;

/**
 * Defines the registration type entity class.
 *
 * @ConfigEntityType(
 *   id = "registration_type",
 *   label = @Translation("Registration type"),
 *   label_collection = @Translation("Registration types"),
 *   label_singular = @Translation("registration type"),
 *   label_plural = @Translation("registration types"),
 *   label_count = @PluralTranslation(
 *     singular = "@count registration type",
 *     plural = "@count registration types",
 *   ),
 *   handlers = {
 *     "access" = "Drupal\Core\Entity\EntityAccessControlHandler",
 *     "list_builder" = "Drupal\registration\RegistrationTypeListBuilder",
 *     "form" = {
 *       "add" = "Drupal\registration\Form\RegistrationTypeForm",
 *       "edit" = "Drupal\registration\Form\RegistrationTypeForm",
 *       "delete" = "Drupal\registration\Form\RegistrationTypeDeleteConfirm"
 *     },
 *     "route_provider" = {
 *       "default" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "type",
 *   admin_permission = "administer registration types",
 *   bundle_of = "registration",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "workflow",
 *     "defaultState",
 *     "heldExpireTime",
 *     "heldExpireState"
 *   },
 *   links = {
 *     "add-form" = "/admin/structure/registration-types/add",
 *     "edit-form" = "/admin/structure/registration-types/{registration_type}/edit",
 *     "delete-form" = "/admin/structure/registration-types/{registration_type}/delete",
 *     "collection" = "/admin/structure/registration-types"
 *   }
 * )
 */
class RegistrationType extends ConfigEntityBundleBase implements RegistrationTypeInterface {

  /**
   * The registration type workflow ID.
   *
   * @var string
   */
  protected string $workflow = 'registration';

  /**
   * The default registration state.
   *
   * @var string
   */
  protected string $defaultState = 'pending';

  /**
   * How long a registration can be held (in hours) before it expires.
   *
   * Defaults to one hour. Zero means the hold never expires.
   *
   * @var int
   */
  protected int $heldExpireTime = 1;

  /**
   * The state a registration will be put into when its hold expires.
   *
   * @var string
   */
  protected string $heldExpireState = 'canceled';

  /**
   * {@inheritdoc}
   */
  public function access($operation, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    if ($operation == 'view label') {
      // Allow site visitors to see the labels of registration types.
      $access_result = AccessResult::allowed();

      return $return_as_object ? $access_result : $access_result->isAllowed();
    }

    return parent::access($operation, $account, $return_as_object);
  }

  /**
   * {@inheritdoc}
   */
  public function getActiveStates(): array {
    $states = [];

    if ($workflow = $this->getWorkflow()) {
      $all_states = $workflow->getTypePlugin()->getStates();
      foreach ($all_states as $id => $state) {
        /** @var \Drupal\registration\RegistrationState $state */
        if ($state->isActive()) {
          $states[$id] = $state;
        }
      }
    }

    return $states;
  }

  /**
   * {@inheritdoc}
   */
  public function getActiveOrHeldStates(): array {
    $states = [];

    if ($workflow = $this->getWorkflow()) {
      $all_states = $workflow->getTypePlugin()->getStates();
      foreach ($all_states as $id => $state) {
        /** @var \Drupal\registration\RegistrationState $state */
        if ($state->isActive() || $state->isHeld()) {
          $states[$id] = $state;
        }
      }
    }

    return $states;
  }

  /**
   * {@inheritdoc}
   */
  public function getHeldStates(): array {
    $states = [];

    if ($workflow = $this->getWorkflow()) {
      $all_states = $workflow->getTypePlugin()->getStates();
      foreach ($all_states as $id => $state) {
        /** @var \Drupal\registration\RegistrationState $state */
        if ($state->isHeld()) {
          $states[$id] = $state;
        }
      }
    }

    return $states;
  }

  /**
   * {@inheritdoc}
   */
  public function getStatesToShowOnForm(?StateInterface $current_state = NULL, bool $check_transitions = FALSE): array {
    $states = [];

    if ($workflow = $this->getWorkflow()) {
      $all_states = $workflow->getTypePlugin()->getStates();
      foreach ($all_states as $id => $state) {
        /** @var \Drupal\registration\RegistrationState $state */
        if ($state->isShownOnForm()) {
          // If transitions should be checked, then ensure the current state,
          // if set, can transition to the new state.
          if (!$current_state
            || !$check_transitions
            || ($current_state->id() == $state->id())
            || $current_state->canTransitionTo($state->id())) {
            $states[$id] = $state;
          }
        }
      }
    }

    // Ensure the default state is included, if set.
    if ($current_state) {
      $states[$current_state->id()] = $current_state;
    }

    return $states;
  }

  /**
   * {@inheritdoc}
   */
  public function getWorkflowId(): string {
    return $this->workflow;
  }

  /**
   * {@inheritdoc}
   */
  public function getWorkflow(): WorkflowInterface|NULL {
    $storage = \Drupal::entityTypeManager()->getStorage('workflow');
    return $storage->load($this->getWorkflowId());
  }

  /**
   * {@inheritdoc}
   */
  public function setWorkflowId($workflow_id): static {
    $this->workflow = $workflow_id;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultState(): string {
    $workflow = $this->getWorkflow();
    if ($this->isNew()) {
      // Default new registration types to the global default for the workflow.
      $configuration = $workflow->getTypePlugin()->getConfiguration();
      return $configuration['default_registration_state'];
    }
    else {
      // Ensure the default still exists.
      try {
        $state = $workflow->getTypePlugin()->getState($this->defaultState);
      }
      catch (\Exception) {
        // The default no longer exists, take the first configured state.
        $states = $workflow->getTypePlugin()->getConfiguration()['states'];
        $this->defaultState = array_key_first($states);
      }
      return $this->defaultState;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setDefaultState($state): RegistrationTypeInterface {
    $this->defaultState = $state;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getHeldExpirationTime(): int {
    return $this->heldExpireTime;
  }

  /**
   * {@inheritdoc}
   */
  public function setHeldExpirationTime($time): RegistrationTypeInterface {
    $this->heldExpireTime = $time;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getHeldExpirationState(): string {
    return $this->heldExpireState;
  }

  /**
   * {@inheritdoc}
   */
  public function setHeldExpirationState($state): RegistrationTypeInterface {
    $this->heldExpireState = $state;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    $cache_contexts = parent::getCacheContexts();

    // If this type has a workflow, it should be included in cacheability.
    if ($workflow = $this->getWorkflow()) {
      $cache_contexts = Cache::mergeContexts($cache_contexts, $workflow->getCacheContexts());
    }

    return $cache_contexts;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge(): int {
    $cache_max_age = parent::getCacheMaxAge();

    // If this type has a workflow, it should be included in cacheability.
    if ($workflow = $this->getWorkflow()) {
      $cache_max_age = Cache::mergeMaxAges($cache_max_age, $workflow->getCacheMaxAge());
    }

    return $cache_max_age;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    $cache_tags = parent::getCacheTags();

    // If this type has a workflow, it should be included in cacheability.
    if ($workflow = $this->getWorkflow()) {
      $cache_tags = Cache::mergeTags($cache_tags, $workflow->getCacheTags());
    }

    return $cache_tags;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): RegistrationTypeInterface {
    parent::calculateDependencies();

    // The registration type depends on a workflow.
    $workflow = $this->getWorkflow();
    $this->addDependency('config', $workflow->getConfigDependencyName());

    // The registration type depends on the module that provides the workflow.
    $workflow_plugin = $workflow->getTypePlugin();
    $this->calculatePluginDependencies($workflow_plugin);

    return $this;
  }

}
