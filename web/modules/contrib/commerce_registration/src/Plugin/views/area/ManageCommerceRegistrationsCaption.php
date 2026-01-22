<?php

namespace Drupal\commerce_registration\Plugin\views\area;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\commerce_registration\CommerceRegistrationManagerInterface;
use Drupal\views\Plugin\views\area\AreaPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a caption used for commerce product registrations.
 *
 * @ingroup views_area_handlers
 *
 * @ViewsArea("manage_commerce_registrations_caption")
 */
class ManageCommerceRegistrationsCaption extends AreaPluginBase {

  /**
   * The commerce registration manager.
   *
   * @var \Drupal\commerce_registration\CommerceRegistrationManagerInterface
   */
  protected CommerceRegistrationManagerInterface $commerceRegistrationManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): ManageCommerceRegistrationsCaption {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->commerceRegistrationManager = $container->get('commerce_registration.manager');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function render($empty = FALSE): array {
    $build = [];
    if (!$empty || !empty($this->options['empty'])) {
      // If the view is filtered on a variation show data for that variation.
      if (!empty($this->view->exposed_data['variation']) && ($this->view->exposed_data['variation'] != 'All')) {
        $storage = $this->entityTypeManager->getStorage('commerce_product_variation');
        /** @var \Drupal\commerce_product\Entity\ProductVariationInterface $variation */
        if ($variation = $storage->load($this->view->exposed_data['variation'])) {
          $handler = $this->entityTypeManager->getHandler('commerce_product_variation', 'registration_host_entity');
          $host_entity = $handler->createHostEntity($variation);
          if ($host_entity->isConfiguredForRegistration()) {
            $settings = $host_entity->getSettings();
            $capacity = $settings->getSetting('capacity');
            $spaces = $host_entity->getActiveSpacesReserved();
            if ($capacity) {
              $caption = $this->formatPlural($capacity,
               'List of registrations for %label. @spaces of 1 space is filled.',
               'List of registrations for %label. @spaces of @count spaces are filled.', [
                 '%label' => $host_entity->label(),
                 '@capacity' => $capacity,
                 '@spaces' => $spaces,
               ]);
            }
            else {
              $caption = $this->formatPlural($spaces,
               'List of registrations for %label. 1 space is filled.',
               'List of registrations for %label. @count spaces are filled.', [
                 '%label' => $host_entity->label(),
               ]);
            }
            $build = [
              '#markup' => $caption,
            ];
          }

          // Set cache directives so the area rebuilds when needed.
          $cacheability = CacheableMetadata::createFromObject($host_entity);
          $cacheability->applyTo($build);
        }
      }
      elseif (!empty($this->view->args)) {
        // The view is not filtered, show general information for the product.
        // The view argument is assumed to be a list of product variation IDs.
        if ($product_id = $this->commerceRegistrationManager->getProductIdFromArgument($this->view->args[0])) {
          $cacheability = new CacheableMetadata();
          $storage = $this->entityTypeManager->getStorage('commerce_product');
          /** @var \Drupal\commerce_product\Entity\ProductInterface $product */
          if ($product = $storage->load($product_id)) {
            $spaces = 0;
            $handler = $this->entityTypeManager->getHandler('commerce_product_variation', 'registration_host_entity');
            foreach ($product->getVariations() as $variation) {
              $host_entity = $handler->createHostEntity($variation);
              $spaces += $host_entity->getActiveSpacesReserved();

              // Accumulate cacheability across all host entities.
              $cacheability->addCacheableDependency($host_entity);
            }
            $caption = $this->formatPlural($spaces,
             'List of registrations for %label. 1 space is filled.',
             'List of registrations for %label. @count spaces are filled.', [
               '%label' => $product->label(),
             ]);
            $build = [
              '#markup' => $caption,
            ];
          }

          // Set cache directives so the area rebuilds when needed.
          $cacheability->applyTo($build);
        }
      }
    }
    return $build;
  }

}
