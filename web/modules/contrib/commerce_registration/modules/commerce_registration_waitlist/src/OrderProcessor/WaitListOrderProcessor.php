<?php

namespace Drupal\commerce_registration_waitlist\OrderProcessor;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\commerce\Context;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\OrderProcessorInterface;
use Drupal\commerce_price\Resolver\ChainPriceResolverInterface;
use Drupal\commerce_registration_waitlist\Event\CommerceRegistrationWaitListEvent;
use Drupal\commerce_registration_waitlist\Event\CommerceRegistrationWaitListEvents;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Provides an order processor for wait listed registrations.
 */
class WaitListOrderProcessor implements OrderProcessorInterface {

  use MessengerTrait;
  use StringTranslationTrait;

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected AccountProxy $currentUser;

  /**
   * The chain price resolver.
   *
   * @var \Drupal\commerce_price\Resolver\ChainPriceResolverInterface
   */
  protected ChainPriceResolverInterface $chainPriceResolver;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Contracts\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a new WaitListOrderProcessor object.
   *
   * @param \Drupal\commerce_price\Resolver\ChainPriceResolverInterface $chain_price_resolver
   *   The chain price resolver.
   * @param \Drupal\Core\Session\AccountProxy $current_user
   *   The current user.
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(ChainPriceResolverInterface $chain_price_resolver, AccountProxy $current_user, EventDispatcherInterface $event_dispatcher, EntityTypeManagerInterface $entity_type_manager) {
    $this->chainPriceResolver = $chain_price_resolver;
    $this->currentUser = $current_user;
    $this->eventDispatcher = $event_dispatcher;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function process(OrderInterface $order) {
    $added_to_wait_list = FALSE;
    $removed_from_wait_list = FALSE;

    foreach ($order->getItems() as $item) {
      // Process items unless they have been flagged to skip.
      if (!$item->getData('commerce_registration_waitlist_skip_processing')) {
        // Track if the wait list state changes.
        $wait_list_original = $item->getData('commerce_registration_waitlist');

        // Recalculate the wait list flag.
        $item->unsetData('commerce_registration_waitlist');
        if (!$item->get('registration')->isEmpty()) {
          $referenced_entities = $item->get('registration')->referencedEntities();
          foreach ($referenced_entities as $registration) {
            if ($registration->getState()->id() == 'waitlist') {
              $item->setData('commerce_registration_waitlist', TRUE);
              break;
            }
          }
        }
        elseif ($variation = $item->getPurchasedEntity()) {
          $handler = $this->entityTypeManager->getHandler('commerce_product_variation', 'registration_host_entity');
          $host_entity = $handler->createHostEntity($variation);
          if (!$host_entity->isConfiguredForRegistration()) {
            // Skip processing for items not configured for registration.
            continue;
          }
          if ($host_entity->isAvailableForRegistration() && $host_entity->shouldAddToWaitList()) {
            $item->setData('commerce_registration_waitlist', TRUE);
          }
        }

        $wait_list = $item->getData('commerce_registration_waitlist');
        if ($wait_list !== $wait_list_original) {
          // Setup messaging.
          if ($wait_list === TRUE) {
            $added_to_wait_list = TRUE;
          }
          elseif ($wait_list_original === TRUE) {
            $removed_from_wait_list = TRUE;
          }
        }

        // Recalculate the item's unit price with the order item in the context.
        // This ensures a proper price calculation based on any registrations
        // attached to the order item.
        $purchased_entity = $item->getPurchasedEntity();
        if ($purchased_entity) {
          if (!$item->isUnitPriceOverridden()) {
            $time = $order->getCalculationDate()->format('U');
            $context = new Context($order->getCustomer(), $order->getStore(), $time, [
              'order_item' => $item,
            ]);
            $unit_price = $this->chainPriceResolver->resolve($purchased_entity, $item->getQuantity(), $context);
            $unit_price ? $item->setUnitPrice($unit_price) : $item->set('unit_price', NULL);
          }
        }
      }
    }

    // Message if any changes to the current user's cart.
    if ($added_to_wait_list) {
      $event = new CommerceRegistrationWaitListEvent($order);
      $this->eventDispatcher->dispatch($event, CommerceRegistrationWaitListEvents::COMMERCE_REGISTRATION_WAITLIST_ADD);
      if (!$event->wasHandled() && ($order->getCustomerId() == $this->currentUser->id())) {
        $this->messenger()->addWarning($this->t('An item in your cart has been placed on the waiting list.'));
      }
    }

    if ($removed_from_wait_list) {
      $event = new CommerceRegistrationWaitListEvent($order);
      $this->eventDispatcher->dispatch($event, CommerceRegistrationWaitListEvents::COMMERCE_REGISTRATION_WAITLIST_REMOVE);
      if (!$event->wasHandled() && ($order->getCustomerId() == $this->currentUser->id())) {
        $this->messenger()->addStatus($this->t('Good news, one or more of your items has been moved off the waiting list. Your order total may have changed as a result.'));
      }
    }
  }

}
