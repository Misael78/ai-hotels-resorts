<?php

namespace Drupal\registration\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\registration\RegistrationManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides registration status block definitions for each enabled entity type.
 */
class RegistrationStatusBlock extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * The registration manager.
   *
   * @var \Drupal\registration\RegistrationManagerInterface
   */
  protected RegistrationManagerInterface $registrationManager;

  /**
   * Creates a new RegistrationStatusBlock.
   *
   * @param \Drupal\registration\RegistrationManagerInterface $registration_manager
   *   The registration manager service.
   */
  public function __construct(RegistrationManagerInterface $registration_manager) {
    $this->registrationManager = $registration_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('registration.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    foreach ($this->registrationManager->getRegistrationEnabledEntityTypes() as $entity_type_id => $entity_type) {
      $this->derivatives[$entity_type_id] = $base_plugin_definition;
      $this->derivatives[$entity_type_id]['admin_label'] = $this->t('Registration status (@label)', ['@label' => $entity_type->getLabel()]);
      $this->derivatives[$entity_type_id]['context_definitions'] = [
        'entity' => new EntityContextDefinition('entity:' . $entity_type_id),
      ];
    }
    return $this->derivatives;
  }

}
