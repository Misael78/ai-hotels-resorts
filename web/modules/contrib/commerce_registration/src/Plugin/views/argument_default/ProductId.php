<?php

namespace Drupal\commerce_registration\Plugin\views\argument_default;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\registration\RegistrationManagerInterface;
use Drupal\views\Plugin\views\argument_default\ArgumentDefaultPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Views argument plugin to extract the product ID from a URL.
 *
 * @ViewsArgumentDefault(
 *   id = "commerce_registration_product_id",
 *   title = @Translation("Product Variations of Product ID from URL")
 * )
 */
class ProductId extends ArgumentDefaultPluginBase implements CacheableDependencyInterface {

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
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): ProductId {
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
      if ($entity instanceof ProductInterface) {
        return implode('+', $entity->getVariationIds());
      }
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
