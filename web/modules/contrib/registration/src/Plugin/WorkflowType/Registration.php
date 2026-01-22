<?php

namespace Drupal\registration\Plugin\WorkflowType;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\registration\RegistrationState;
use Drupal\workflows\Plugin\WorkflowTypeBase;
use Drupal\workflows\StateInterface;
use Drupal\workflows\WorkflowInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Attaches workflows to registrations.
 *
 * @WorkflowType(
 *   id = "registration",
 *   label = @Translation("Registration"),
 *   forms = {
 *     "configure" = "\Drupal\registration\Form\RegistrationWorkflowForm",
 *     "state" = "\Drupal\registration\Form\RegistrationStateForm"
 *   },
 * )
 */
class Registration extends WorkflowTypeBase implements ContainerFactoryPluginInterface, RegistrationWorkflowTypeInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a Registration object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): Registration {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getState($state_id): RegistrationState {
    $state = parent::getState($state_id);
    $properties = [
      'description',
      'active',
      'canceled',
      'held',
      'show_on_form',
    ];
    $use_properties = TRUE;
    foreach ($properties as $property) {
      if (!isset($this->configuration['states'][$state->id()][$property])) {
        // Special handling for canceled which was added later.
        if ($property == 'canceled') {
          $this->configuration['states'][$state->id()][$property] = ($state->id() == 'canceled');
        }
        else {
          $use_properties = FALSE;
          break;
        }
      }
    }
    if ($use_properties) {
      $state = new RegistrationState($state, $this->configuration['states'][$state->id()]['description'], $this->configuration['states'][$state->id()]['active'], $this->configuration['states'][$state->id()]['canceled'], $this->configuration['states'][$state->id()]['held'], $this->configuration['states'][$state->id()]['show_on_form']);
    }
    else {
      $state = new RegistrationState($state);
    }
    return $state;
  }

  /**
   * {@inheritdoc}
   */
  public function getStateOptions(): array {
    $options = [];
    $states = $this->getStates();
    foreach ($states as $id => $state) {
      $options[$id] = $state->label();
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function workflowHasData(WorkflowInterface $workflow): bool {
    $has_data = (bool) $this->entityTypeManager
      ->getStorage('registration')
      ->getQuery()
      ->condition('workflow', $workflow->id())
      ->count()
      ->accessCheck(FALSE)
      ->range(0, 1)
      ->execute();

    // If no registrations are using the workflow, check for registration types
    // using it.
    if (!$has_data) {
      $has_data = (bool) $this->entityTypeManager
        ->getStorage('registration_type')
        ->getQuery()
        ->condition('workflow', $workflow->id())
        ->count()
        ->accessCheck(FALSE)
        ->range(0, 1)
        ->execute();
    }

    return $has_data;
  }

  /**
   * {@inheritdoc}
   */
  public function workflowStateHasData(WorkflowInterface $workflow, StateInterface $state): bool {
    $has_data = (bool) $this->entityTypeManager
      ->getStorage('registration')
      ->getQuery()
      ->condition('workflow', $workflow->id())
      ->condition('state', $state->id())
      ->count()
      ->accessCheck(FALSE)
      ->range(0, 1)
      ->execute();

    // If no registrations are using the state, check for registration types
    // using it.
    if (!$has_data) {
      $query = $this->entityTypeManager
        ->getStorage('registration_type')
        ->getQuery()
        ->condition('workflow', $workflow->id());
      $orGroup = $query->orConditionGroup()
        ->condition('defaultState', $state->id())
        ->condition('heldExpireState', $state->id());
      $has_data = (bool) $query
        ->condition($orGroup)
        ->count()
        ->accessCheck(FALSE)
        ->range(0, 1)
        ->execute();
    }

    return $has_data;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'states' => [
        'pending' => [
          'label' => 'Pending',
          'description' => 'Registration is pending.',
          'active' => TRUE,
          'canceled' => FALSE,
          'held' => FALSE,
          'show_on_form' => FALSE,
          'weight' => 0,
        ],
        'held' => [
          'label' => 'Held',
          'description' => 'Registration is held.',
          'active' => FALSE,
          'canceled' => FALSE,
          'held' => TRUE,
          'show_on_form' => FALSE,
          'weight' => 1,
        ],
        'complete' => [
          'label' => 'Complete',
          'description' => 'Registration has been completed.',
          'active' => TRUE,
          'canceled' => FALSE,
          'held' => FALSE,
          'show_on_form' => FALSE,
          'weight' => 2,
        ],
        'canceled' => [
          'label' => 'Canceled',
          'description' => 'Registration has been canceled.',
          'active' => FALSE,
          'canceled' => TRUE,
          'held' => FALSE,
          'show_on_form' => FALSE,
          'weight' => 3,
        ],
      ],
      'transitions' => [
        'complete' => [
          'label' => 'Complete',
          'to' => 'complete',
          'weight' => 0,
          'from' => [
            'pending',
            'held',
          ],
        ],
        'hold' => [
          'label' => 'Hold',
          'to' => 'held',
          'weight' => 1,
          'from' => [
            'pending',
          ],
        ],
        'cancel' => [
          'label' => 'Cancel',
          'to' => 'canceled',
          'weight' => 2,
          'from' => [
            'complete',
            'pending',
            'held',
          ],
        ],
      ],
      'default_registration_state' => 'pending',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration(): array {
    $configuration = parent::getConfiguration();
    // Ensure that states are ordered consistently.
    ksort($configuration['states']);
    return $configuration;
  }

}
