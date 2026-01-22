<?php

namespace Drupal\commerce_registration\Plugin\views\area;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\commerce_registration\CommerceRegistrationManagerInterface;
use Drupal\views\Plugin\views\area\AreaPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines an area used when a product has no registrations to manage.
 *
 * @ingroup views_area_handlers
 *
 * @ViewsArea("manage_commerce_registrations_empty")
 */
class ManageCommerceRegistrationsEmpty extends AreaPluginBase {

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
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): ManageCommerceRegistrationsEmpty {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->commerceRegistrationManager = $container->get('commerce_registration.manager');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function render($empty = FALSE): array {
    if (!$empty || !empty($this->options['empty'])) {
      // If the view is filtered on a variation show data for that variation.
      if (!empty($this->view->exposed_data['variation']) && ($this->view->exposed_data['variation'] != 'All')) {
        $storage = $this->entityTypeManager->getStorage('commerce_product_variation');
        /** @var \Drupal\commerce_product\Entity\ProductVariationInterface $variation */
        if ($variation = $storage->load($this->view->exposed_data['variation'])) {
          return [
            '#markup' => $this->t('There are no registrants for %name', [
              '%name' => $variation->getTitle(),
            ]),
          ];
        }
      }
      elseif (!empty($this->view->args)) {
        // The view is not filtered, show a message for the product.
        // The view argument is assumed to be a list of product variation IDs.
        if ($product_id = $this->commerceRegistrationManager->getProductIdFromArgument($this->view->args[0])) {
          $storage = $this->entityTypeManager->getStorage('commerce_product');
          if ($product = $storage->load($product_id)) {
            /** @var \Drupal\commerce_product\Entity\ProductInterface $product */
            return [
              '#markup' => $this->t('There are no registrants for %name', [
                '%name' => $product->getTitle(),
              ]),
            ];
          }
        }
      }
    }
    return [];
  }

}
