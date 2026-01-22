<?php

namespace Drupal\registration\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\ConfigurableActionBase;
use Drupal\Core\Entity\DependencyTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\registration\RegistrationManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an action to set the registration state.
 *
 * @Action(
 *   id = "registration_views_set_state_action",
 *   label = @Translation("Set the Registration State"),
 *   type = "registration"
 * )
 */
class RegistrationSetStateAction extends ConfigurableActionBase implements ContainerFactoryPluginInterface {

  use DependencyTrait;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The registration manager.
   */
  protected RegistrationManagerInterface $registrationManager;

  /**
   * Constructs an RegistrationSetStateAction object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\registration\RegistrationManagerInterface $registration_manager
   *   The registration manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, RegistrationManagerInterface $registration_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->registrationManager = $registration_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('registration.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'registration_state' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['registration_state'] = [
      '#type' => 'select',
      '#title' => $this->t('Status'),
      '#options' => $this->registrationManager->getWorkflowStateOptions(TRUE),
      '#default_value' => $this->configuration['registration_state'],
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['registration_state'] = $form_state->getValue('registration_state');
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): array {
    $module_name = $this->entityTypeManager
      ->getDefinition($this->getPluginDefinition()['type'])
      ->getProvider();
    return ['module' => [$module_name]];
  }

  /**
   * {@inheritdoc}
   */
  public function execute($object = NULL) {
    $registration_state = $this->configuration['registration_state'];

    /** @var \Drupal\registration\Entity\RegistrationInterface $object */
    if ($object->getState()->id() != $registration_state) {
      if ($object->getState()->canTransitionTo($registration_state)) {
        $object->set('state', $registration_state);
        $object->save();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = AccessResult::allowed();

    /** @var \Drupal\registration\Entity\RegistrationInterface $object */
    $registration_state = $this->configuration['registration_state'];
    if ($object->getState()->id() != $registration_state) {
      // There must be a valid transition to the new state.
      if (!$object->getState()->canTransitionTo($registration_state)) {
        $result = AccessResult::forbidden("The registration cannot transition to the selected state.");
      }
      $result->addCacheableDependency($object->getWorkflow());
    }

    // The user must have permission to set state.
    $account = $this->prepareUser($account);
    $result = $result->andIf($object->access('edit state', $account, TRUE));

    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * Loads the current account object, if it does not exist yet.
   *
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The account interface instance, if available.
   *
   * @return \Drupal\Core\Session\AccountInterface
   *   Returns the current account object.
   */
  protected function prepareUser(?AccountInterface $account = NULL): AccountInterface {
    if (!$account) {
      $account = \Drupal::currentUser();
    }
    return $account;
  }

}
