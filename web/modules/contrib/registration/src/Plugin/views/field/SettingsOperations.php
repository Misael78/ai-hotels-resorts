<?php

namespace Drupal\registration\Plugin\views\field;

use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Routing\RedirectDestinationTrait;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Url;
use Drupal\views\Plugin\views\field\EntityOperations;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders registration settings operations links.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("registration_settings_operations")
 */
class SettingsOperations extends EntityOperations {

  use RedirectDestinationTrait;

  /**
   * The access manager.
   *
   * @var \Drupal\Core\Access\AccessManagerInterface
   */
  protected AccessManagerInterface $accessManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected AccountProxy $currentUser;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): SettingsOperations {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->accessManager = $container->get('access_manager');
    $instance->currentUser = $container->get('current_user');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $build = [];
    $operations = [];

    /** @var \Drupal\registration\Entity\RegistrationSettings $settings_entity */
    if ($settings_entity = $this->getEntity($values)) {
      $entity_id = $settings_entity->getHostEntityId();
      $entity_type_id = $settings_entity->getHostEntityTypeId();

      $storage = $this->entityTypeManager->getStorage($entity_type_id);
      if ($entity = $storage->load($entity_id)) {
        /** @var \Drupal\registration\HostEntityInterface $host_entity */
        $host_entity = $this->entityTypeManager
          ->getHandler($entity->getEntityTypeId(), 'registration_host_entity')
          ->createHostEntity($entity);
        if ($host_entity->getRegistrationTypeBundle()) {
          $route_name = "entity.$entity_type_id.registration.registration_settings";
          $access_result = $this->accessManager->checkNamedRoute($route_name, [
            $entity_type_id => $entity_id,
          ], $this->currentUser, TRUE);
          if ($access_result->isAllowed()) {
            $url = Url::fromRoute($route_name, [
              $entity_type_id => $entity_id,
            ]);
            $operations['edit'] = [
              'title' => $this->t('Edit settings'),
              'url' => $this->ensureDestination($url),
            ];
          }
        }
      }
    }

    if (!empty($operations)) {
      $build = [
        '#type' => 'operations',
        '#links' => $operations,
      ];
    }

    return $build;
  }

  /**
   * Ensures that a destination is present on the given URL.
   *
   * @param \Drupal\Core\Url $url
   *   The URL object to which the destination should be added.
   *
   * @return \Drupal\Core\Url
   *   The updated URL object.
   */
  protected function ensureDestination(Url $url): Url {
    return $url->mergeOptions(['query' => $this->getRedirectDestination()->getAsArray()]);
  }

}
