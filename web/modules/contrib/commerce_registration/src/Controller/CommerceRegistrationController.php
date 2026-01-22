<?php

namespace Drupal\commerce_registration\Controller;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\registration\HostEntityInterface;
use Drupal\registration\RegistrationManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for Commerce Registration routes.
 */
class CommerceRegistrationController extends ControllerBase {

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected EntityRepositoryInterface $entityRepository;

  /**
   * The registration manager.
   *
   * @var \Drupal\registration\RegistrationManagerInterface
   */
  protected RegistrationManagerInterface $registrationManager;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected Renderer $renderer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): CommerceRegistrationController {
    $instance = parent::create($container);
    $instance->entityRepository = $container->get('entity.repository');
    $instance->registrationManager = $container->get('registration.manager');
    $instance->renderer = $container->get('renderer');
    return $instance;
  }

  /**
   * Displays the Manage Registrations task for a product.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return array
   *   A render array as expected by drupal_render().
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function manageRegistrations(Request $request): array {
    $build = [];
    $cache_entities = [];
    /** @var \Drupal\commerce_product\Entity\ProductInterface $product */
    if ($product = $this->registrationManager->getEntityFromParameters($request->attributes)) {
      $cache_entities[] = $product;

      $registration_enabled_variation = NULL;
      $registration_enabled_variations = 0;
      $handler = $this->entityTypeManager()->getHandler('commerce_product_variation', 'registration_host_entity');
      foreach ($product->getVariations() as $variation) {
        $cache_entities[] = $variation;
        $host_entity = $handler->createHostEntity($variation);
        $cache_entities[] = $host_entity;
        if ($host_entity->isConfiguredForRegistration()) {
          $registration_enabled_variation = $variation;
          $registration_enabled_variations++;
        }
      }

      // Display the base registration listing directly if there is only one
      // registration enabled product variation for the product. If there are
      // multiple product variations, display a summary listing showing all
      // registrations for the product.
      if ($registration_enabled_variations == 1) {
        // The base registration listing is defined in the registration module.
        // For this use case, it will display registrations for a single
        // commerce product variation.
        $view_name = 'manage_registrations';
        $view_args = [
          $registration_enabled_variation->getEntityTypeId(),
          $registration_enabled_variation->id(),
        ];
      }
      else {
        $view_name = 'manage_commerce_registrations';
        $view_args = [implode('+', $product->getVariationIds())];
      }

      if ($view = $this->entityTypeManager()->getStorage('view')->load($view_name)) {
        $display = 'block_1';
        $cache_entities[] = $view;
        if ($view->getExecutable()->access($display)) {
          $build = [
            '#type' => 'view',
            '#name' => $view_name,
            '#display_id' => $display,
            '#arguments' => $view_args,
          ];
          $build['#attached']['library'][] = 'registration/manage_registrations';
        }
      }

      // Set cache directives so the task rebuilds when needed.
      $this->addCacheableDependencies($build, $cache_entities);
    }

    return $build;
  }

  /**
   * Builds the title for the manage product registrations route.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The title.
   */
  public function manageRegistrationsTitle(RouteMatchInterface $route_match): TranslatableMarkup {
    $product = $route_match->getParameter('commerce_product');
    $product = $this->entityRepository->getTranslationFromContext($product);
    return $this->t('%label registrations', ['%label' => $product->label()]);
  }

  /**
   * Displays the Registration Settings task for a product.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return array
   *   A render array as expected by drupal_render().
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function registrationSettings(Request $request): array {
    $build = [];
    $cache_contexts = [];
    $cache_entities = [];
    /** @var \Drupal\commerce_product\Entity\ProductInterface $product */
    if ($product = $this->registrationManager->getEntityFromParameters($request->attributes)) {
      if ($variation_id = $request->query->get('variation_id')) {
        $cache_contexts = ['url.query_args:variation_id'];
      }
      $cache_entities[] = $product;

      $registration_host = NULL;
      $registration_enabled_variations = 0;
      $handler = $this->entityTypeManager()->getHandler('commerce_product_variation', 'registration_host_entity');
      foreach ($product->getVariations() as $variation) {
        $cache_entities[] = $variation;
        $host_entity = $handler->createHostEntity($variation);
        $cache_entities[] = $host_entity;
        if ($host_entity->isConfiguredForRegistration()) {
          if ($variation_id) {
            if ($variation->id() == $variation_id) {
              $registration_host = $host_entity;
              $registration_enabled_variations++;
            }
          }
          else {
            $registration_host = $host_entity;
            $registration_enabled_variations++;
          }
        }
      }

      // Display the registration form directly if there is only one
      // registration enabled product variation for the product. If there are
      // multiple product variations, display a listing with edit settings
      // buttons that navigate to the registration settings for each variation.
      if ($registration_enabled_variations == 1) {
        $build['fieldset'] = [
          '#type' => 'fieldset',
          '#title' => $this->t('Registration settings for %label', [
            '%label' => $registration_host->label(),
          ]),
        ];
        $build['fieldset']['form'] = $this->entityFormBuilder()->getForm($registration_host->getSettings());
      }
      elseif ($view = $this->entityTypeManager()->getStorage('view')->load('product_registration_settings')) {
        $display = 'block_1';
        $cache_entities[] = $view;
        if ($view->getExecutable()->access($display)) {
          $build = [
            '#type' => 'view',
            '#name' => 'product_registration_settings',
            '#display_id' => $display,
            '#arguments' => [
              $product->id(),
            ],
          ];
        }
      }

      // Set cache contexts so different output is cached per context.
      if (!empty($cache_contexts)) {
        $build['#cache']['contexts'] = $cache_contexts;
      }

      // Set cache directives so the task rebuilds when needed.
      $this->addCacheableDependencies($build, $cache_entities);
    }

    return $build;
  }

  /**
   * Builds the title for the product registration settings route.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The title.
   */
  public function registrationSettingsTitle(RouteMatchInterface $route_match): TranslatableMarkup {
    $product = $route_match->getParameter('commerce_product');
    $product = $this->entityRepository->getTranslationFromContext($product);
    return $this->t('%label registration settings', ['%label' => $product->label()]);
  }

  /**
   * Builds the title for the email product registrants route.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The title.
   */
  public function broadcastTitle(RouteMatchInterface $route_match): TranslatableMarkup {
    $product = $route_match->getParameter('commerce_product');
    $product = $this->entityRepository->getTranslationFromContext($product);
    return $this->t('Email registrants of %label', ['%label' => $product->label()]);
  }

  /**
   * Add cache dependencies to a render array.
   *
   * @param array $build
   *   The render array.
   * @param array $entities
   *   The entities for which dependencies should be added.
   */
  protected function addCacheableDependencies(array &$build, array $entities = []) {
    // Rebuild if entities are updated.
    foreach ($entities as $entity) {
      if (isset($entity)) {
        $this->renderer->addCacheableDependency($build, $entity);

        // Rebuild when registrations are added and deleted for a host entity.
        if ($entity instanceof HostEntityInterface) {
          $tags = $build['#cache']['tags'];
          $build['#cache']['tags'] = Cache::mergeTags($tags, [$entity->getRegistrationListCacheTag()]);
        }
      }
    }

    // Rebuild per user permissions.
    $build['#cache']['contexts'][] = 'user.permissions';
  }

}
