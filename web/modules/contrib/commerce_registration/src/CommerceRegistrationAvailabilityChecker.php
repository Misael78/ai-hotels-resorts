<?php

namespace Drupal\commerce_registration;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\commerce\Context;
use Drupal\commerce_order\AvailabilityCheckerInterface;
use Drupal\commerce_order\AvailabilityResult;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_product\Entity\ProductVariationInterface;

/**
 * Availability checker.
 *
 * This is a tagged service automatically called by Commerce core to ensure
 * that products in the cart are still available throughout the checkout
 * process. This class is referenced in the services file for this module.
 */
class CommerceRegistrationAvailabilityChecker implements AvailabilityCheckerInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a new CommerceRegistrationAvailabilityChecker object.
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
  public function applies(OrderItemInterface $order_item): bool {
    // This check only applies to product variations with a set registration
    // field and an order item with no registrations yet. An order processor
    // handles checking of items if they already have registrations attached.
    // @see \Drupal\commerce_registration\OrderProcessor\RegistrationOrderProcessor
    $handler = $this->entityTypeManager->getHandler('commerce_product_variation', 'registration_host_entity');
    $purchased_entity = $order_item->getPurchasedEntity();
    if ($purchased_entity instanceof ProductVariationInterface) {
      $host_entity = $handler->createHostEntity($purchased_entity);
      return $host_entity->isConfiguredForRegistration() && $order_item->get('registration')->isEmpty();
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function check(OrderItemInterface $order_item, Context $context): AvailabilityResult {
    $variation = $order_item->getPurchasedEntity();
    $handler = $this->entityTypeManager->getHandler('commerce_product_variation', 'registration_host_entity');
    $host_entity = $handler->createHostEntity($variation);
    $quantity = (int) $order_item->getQuantity();
    // Ensure the number of spaces requested are still available.
    if ($quantity) {
      $validation_result = $host_entity->hasRoomForRegistration($quantity, TRUE);
      if (!$validation_result->isValid()) {
        // Workaround for Commerce core not displaying unavailability messages
        // in all cases.
        $error = NULL;
        $messenger = \Drupal::messenger();
        $messenger->deleteByType(MessengerInterface::TYPE_STATUS);
        foreach ($validation_result->getViolations() as $violation) {
          if (!$error) {
            $error = $violation->getMessage();
          }
          $messenger->addError($violation->getMessage());
        }

        // Return unavailable.
        return AvailabilityResult::unavailable($error);
      }
    }
    // Still available.
    return AvailabilityResult::neutral();
  }

}
