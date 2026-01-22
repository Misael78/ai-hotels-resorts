<?php

namespace Drupal\commerce_registration\OrderProcessor;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\OrderProcessorInterface;

/**
 * Provides an order processor for registrations.
 */
class RegistrationOrderProcessor implements OrderProcessorInterface {

  use MessengerTrait;
  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a new RegistrationOrderProcessor object.
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
  public function process(OrderInterface $order) {
    $handler = $this->entityTypeManager->getHandler('commerce_product_variation', 'registration_host_entity');
    foreach ($order->getItems() as $item) {
      $remove_item = FALSE;
      $message = '';

      /** @var \Drupal\commerce_product\Entity\ProductVariationInterface $variation */
      if ($variation = $item->getPurchasedEntity()) {
        $host_entity = $handler->createHostEntity($variation);
        // Validate registrations attached to the item.
        if (!$item->get('registration')->isEmpty()) {
          if (!$host_entity->isConfiguredForRegistration()) {
            // Registration was disabled after an item was placed in the cart.
            $remove_item = TRUE;
            $message = $this->t('Sorry, <strong>@item_name</strong> is no longer available for registration. It has been removed from your cart.', [
              '@item_name' => $item->getTitle(),
            ]);
          }
          else {
            $registrations = $item->get('registration')->referencedEntities();
            foreach ($registrations as $registration) {
              if ($registration->isCanceled()) {
                // An existing registration expired because checkout did not
                // complete in time, or it was canceled by an administrator for
                // other reasons.
                $remove_item = TRUE;
                $message = $this->t('Sorry, <strong>@item_name</strong> has one or more expired or canceled registrations. It has been removed from your cart.', [
                  '@item_name' => $item->getTitle(),
                ]);
                break;
              }
            }
          }
        }
      }
      else {
        $remove_item = TRUE;
        $message = $this->t('Sorry, <strong>@item_name</strong> is no longer available for registration. It has been removed from your cart.', [
          '@item_name' => $item->getTitle(),
        ]);
      }

      // Process removal if needed.
      if ($remove_item) {
        $order->removeItem($item);
        try {
          if (!$item->get('registration')->isEmpty()) {
            $registrations = $item->get('registration')->referencedEntities();
            foreach ($registrations as $registration) {
              $registration->delete();
            }
          }
          $item->delete();
        }
        catch (\Exception $e) {
          // If the item could not be deleted due to a storage exception,
          // it is still out of the cart. So simply proceed.
        }
        $this->messenger()->addWarning($message);
      }
    }
  }

}
