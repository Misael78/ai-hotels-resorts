<?php

namespace Drupal\registration\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\registration\RegistrationManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides local tasks for entity types with attached registration fields.
 */
class RegistrationLocalTask extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * The registration manager.
   *
   * @var \Drupal\registration\RegistrationManagerInterface
   */
  protected RegistrationManagerInterface $registrationManager;

  /**
   * Creates a RegistrationLocalTask object.
   *
   * @param \Drupal\registration\RegistrationManagerInterface $registration_manager
   *   The registration manager.
   */
  public function __construct(RegistrationManagerInterface $registration_manager) {
    $this->registrationManager = $registration_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id): RegistrationLocalTask {
    return new static(
      $container->get('registration.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    $this->derivatives = [];

    foreach ($this->registrationManager->getRegistrationEnabledEntityTypes() as $entity_type_id => $entity_type) {
      if ($this->registrationManager->getRoute($entity_type, 'manage')) {
        $this->derivatives["$entity_type_id.registration.manage_registrations"] = [
          'route_name' => "entity.$entity_type_id.registration.manage_registrations",
          'title' => $this->t('Manage Registrations'),
          'base_route' => $this->registrationManager->getBaseRouteName($entity_type),
          'weight' => 50,
        ];
        $this->derivatives["$entity_type_id.registration.manage_registrations_sub"] = [
          'route_name' => "entity.$entity_type_id.registration.manage_registrations",
          'title' => $this->t('Registrations'),
          'parent_id' => "registration.entities:$entity_type_id.registration.manage_registrations",
        ];
        $this->derivatives["$entity_type_id.registration.registration_settings"] = [
          'route_name' => "entity.$entity_type_id.registration.registration_settings",
          'title' => $this->t('Settings'),
          'parent_id' => "registration.entities:$entity_type_id.registration.manage_registrations",
          'weight' => 10,
        ];
        $this->derivatives["$entity_type_id.registration.broadcast"] = [
          'route_name' => "entity.$entity_type_id.registration.broadcast",
          'title' => $this->t('Email registrants'),
          'parent_id' => "registration.entities:$entity_type_id.registration.manage_registrations",
          'weight' => 20,
        ];
      }
      if ($this->registrationManager->getRoute($entity_type, 'register')) {
        if (!$this->registrationManager->getFieldConfigSetting($entity_type, 'hide_register_tab')) {
          $this->derivatives["$entity_type_id.registration.register"] = [
            'route_name' => "entity.$entity_type_id.registration.register",
            'title' => $this->t('Register'),
            'base_route' => $this->registrationManager->getBaseRouteName($entity_type),
            'weight' => 50,
          ];
        }
      }
    }

    foreach ($this->derivatives as &$entry) {
      $entry += $base_plugin_definition;
    }

    return $this->derivatives;
  }

}
