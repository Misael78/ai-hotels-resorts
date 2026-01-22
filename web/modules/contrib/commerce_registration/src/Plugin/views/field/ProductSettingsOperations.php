<?php

namespace Drupal\commerce_registration\Plugin\views\field;

use Drupal\Core\Url;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\registration\Entity\RegistrationSettings;
use Drupal\registration\Plugin\views\field\SettingsOperations;
use Drupal\views\ResultRow;

/**
 * Renders product registration settings operations links.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("product_registration_settings_operations")
 */
class ProductSettingsOperations extends SettingsOperations {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $build = [];
    $operations = [];

    if ($settings_entity = $this->getSettingsEntity($values)) {
      $entity_id = $settings_entity->getHostEntityId();
      $entity_type_id = $settings_entity->getHostEntityTypeId();

      // Only display the operations if the host entity is configured
      // with its registration type field set.
      $storage = $this->entityTypeManager->getStorage($entity_type_id);
      if ($entity = $storage->load($entity_id)) {
        /** @var \Drupal\registration\HostEntityInterface $host_entity */
        $host_entity = $this->entityTypeManager
          ->getHandler('commerce_product_variation', 'registration_host_entity')
          ->createHostEntity($entity);
        if ($host_entity->getRegistrationTypeBundle()) {
          $access_result = $settings_entity->access('update', $this->currentUser, TRUE);
          if ($access_result->isAllowed()) {
            /** @var \Drupal\commerce_product\Entity\ProductVariationInterface $entity */
            $url = Url::fromRoute('entity.commerce_product.commerce_registration.registration_settings', [
              'commerce_product' => $entity->getProduct()->id(),
            ]);
            $query_args = [
              'variation_id' => $entity_id,
            ];
            $url->setOptions(['query' => $query_args]);
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
   * Get the settings entity for the current row.
   *
   * @param \Drupal\views\ResultRow $values
   *   The row values.
   *
   * @return \Drupal\registration\Entity\RegistrationSettings|null
   *   The settings entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function getSettingsEntity(ResultRow $values): ?RegistrationSettings {
    $settings = $this->getEntity($values);
    if (!$settings) {
      // Settings are not available, perhaps because the relationship is not
      // required. See if settings can be fetched from a different entity in
      // the row.
      $variation = NULL;
      if ($values->_entity instanceof ProductVariationInterface) {
        $variation = $values->_entity;
      }
      else {
        foreach ($values->_relationship_entities as $entity) {
          if ($entity instanceof ProductVariationInterface) {
            $variation = $entity;
          }
        }
      }

      if ($variation) {
        $host_entity = $this->entityTypeManager
          ->getHandler('commerce_product_variation', 'registration_host_entity')
          ->createHostEntity($variation);
        $settings = $host_entity->getSettings();
      }
    }

    return $settings;
  }

}
