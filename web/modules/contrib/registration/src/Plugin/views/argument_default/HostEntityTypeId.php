<?php

namespace Drupal\registration\Plugin\views\argument_default;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\registration\RegistrationManagerInterface;
use Drupal\views\Plugin\views\argument_default\ArgumentDefaultPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Views argument plugin to extract the host entity from a URL.
 *
 * @ViewsArgumentDefault(
 *   id = "registration_host_entity_type_id",
 *   title = @Translation("Host entity type ID from URL")
 * )
 */
class HostEntityTypeId extends ArgumentDefaultPluginBase implements CacheableDependencyInterface {

  /**
   * The registration manager.
   *
   * @var \Drupal\registration\RegistrationManagerInterface
   */
  protected RegistrationManagerInterface $registrationManager;

  /**
   * The route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected RouteMatchInterface $routeMatch;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): HostEntityTypeId {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->registrationManager = $container->get('registration.manager');
    $instance->routeMatch = $container->get('current_route_match');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getArgument() {
    $parameters = $this->routeMatch->getParameters();
    if ($entity = $this->registrationManager->getEntityFromParameters($parameters)) {
      return $entity->getEntityTypeId();
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge(): int {
    return Cache::PERMANENT;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return ['url'];
  }

}
