<?php

namespace Drupal\commerce_registration\Plugin\EntityReferenceSelection;

use Drupal\Component\Utility\Html;
use Drupal\commerce_product\Plugin\EntityReferenceSelection\ProductVariationSelection as BaseProductVariationSelection;

/**
 * Product variation selection by title or SKU, optionally filtered by product.
 *
 * When using the product filter, only variations with a non-empty registration
 * field are included in the available variations list.
 *
 * @EntityReferenceSelection(
 *   id = "commerce_registration_variation",
 *   label = @Translation("Product variation selection for commerce registration"),
 *   entity_types = {"commerce_product_variation"},
 *   group = "commerce_registration_variation",
 *   weight = 10
 * )
 */
class ProductVariationSelection extends BaseProductVariationSelection {

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    $query = parent::buildEntityQuery($match, $match_operator);

    $configuration = $this->getConfiguration();
    if (!empty($configuration['product_id'])) {
      $query->condition('product_id', (array) $configuration['product_id'], 'IN');
    }

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function getReferenceableEntities($match = NULL, $match_operator = 'CONTAINS', $limit = 0): array {
    $configuration = $this->getConfiguration();
    if (!empty($configuration['product_id'])) {
      $options = [];
      if ($product = $this->entityTypeManager->getStorage('commerce_product')->load($configuration['product_id'])) {
        $handler = $this->entityTypeManager->getHandler('commerce_product_variation', 'registration_host_entity');
        foreach ($product->getVariations() as $entity) {
          $host_entity = $handler->createHostEntity($entity);
          // Only include variations that are configured for registration.
          if ($host_entity->isConfiguredForRegistration()) {
            $sku = $entity->getSku();
            $bundle = $entity->bundle();
            $entity_id = $entity->id();
            $options[$bundle][$entity_id] = Html::escape($sku . ': ' . $this->entityRepository->getTranslationFromContext($entity)->label());
          }
        }
      }
    }
    else {
      // Use the standard selection from the commerce product module when the
      // product ID filter is not used.
      $options = parent::getReferenceableEntities($match, $match_operator, $limit);
    }

    return $options;
  }

}
