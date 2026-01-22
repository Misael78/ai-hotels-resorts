<?php

namespace Drupal\registration\Form;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\registration\HostEntityInterface;
use Drupal\registration\RegistrationManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the registration settings form.
 */
abstract class RegistrationFormBase extends FormBase {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The registration manager.
   *
   * @var \Drupal\registration\RegistrationManagerInterface
   */
  protected RegistrationManagerInterface $registrationManager;

  /**
   * Creates a Registration Form object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\registration\RegistrationManagerInterface $registration_manager
   *   The registration manager.
   */
  public function __construct(ModuleHandlerInterface $module_handler, RegistrationManagerInterface $registration_manager) {
    $this->moduleHandler = $module_handler;
    $this->registrationManager = $registration_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('module_handler'),
      $container->get('registration.manager')
    );
  }

  /**
   * Gets the host entity.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\registration\HostEntityInterface|null
   *   The host entity.
   */
  protected function getHostEntity(FormStateInterface $form_state): ?HostEntityInterface {
    $host_entity = $form_state->get('host_entity');
    if (!$host_entity) {
      $route_match = $this->getRouteMatch();
      $host_entity = $this->registrationManager->getEntityFromParameters($route_match->getParameters(), TRUE);
      $form_state->set('host_entity', $host_entity);
    }
    return $host_entity;
  }

}
