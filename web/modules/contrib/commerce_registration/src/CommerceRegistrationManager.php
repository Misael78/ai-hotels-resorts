<?php

namespace Drupal\commerce_registration;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Defines the class for the commerce registration manager service.
 */
class CommerceRegistrationManager implements CommerceRegistrationManagerInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Creates a CommerceRegistrationManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getProductIdFromArgument(string $argument): ?int {
    $product_id = NULL;
    if ($argument) {
      $variation_ids = explode('+', $argument);
      /* @phpstan-ignore-next-line */
      if (!empty($variation_ids)) {
        $storage = $this->entityTypeManager->getStorage('commerce_product_variation');
        if ($variation = $storage->load($variation_ids[0])) {
          $product_id = $variation->getProductId();
        }
      }
    }
    return $product_id;
  }

}
