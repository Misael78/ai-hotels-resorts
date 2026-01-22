<?php

namespace Drupal\registration_test_block\ContextProvider;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextProviderInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\UserInterface;

/**
 * Provides a route context for user routes.
 */
class UserRouteContext implements ContextProviderInterface {

  use StringTranslationTrait;

  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructs a UserRouteContext.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   */
  public function __construct(RouteMatchInterface $route_match) {
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public function getRuntimeContexts(array $context_ids): array {
    $context_value = NULL;
    $context_result = [];
    $context_definition = EntityContextDefinition::create('user')->setRequired(FALSE);

    if ($route_object = $this->routeMatch->getRouteObject()) {
      if (($route_contexts = $route_object->getOption('parameters')) && isset($route_contexts['user'])) {
        $user = $this->routeMatch->getParameter('user');

        if ($user instanceof UserInterface) {
          $context_value = $user;
        }
      }
    }

    $context = new Context($context_definition, $context_value);

    $cacheability = new CacheableMetadata();
    $cacheability->setCacheContexts(['route']);
    $context->addCacheableDependency($cacheability);

    $context_result['user'] = $context;
    return $context_result;
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableContexts(): array {
    $context = EntityContext::fromEntityTypeId('user', $this->t('User from URL'));
    return ['user' => $context];
  }

}
