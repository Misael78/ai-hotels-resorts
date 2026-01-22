<?php

namespace Drupal\commerce_registration_waitlist\EventSubscriber;

use Drupal\Core\Action\ActionManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\commerce_log\LogStorageInterface;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\registration\Entity\RegistrationInterface;
use Drupal\registration\Event\RegistrationEvent;
use Drupal\registration_waitlist\Event\RegistrationWaitListEvents;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides a wait list event subscriber.
 */
class WaitListEventSubscriber implements EventSubscriberInterface {

  /**
   * The action plugin manager.
   *
   * @var \Drupal\Core\Action\ActionManager
   */
  protected ActionManager $actionManager;

  /**
   * The log storage.
   *
   * @var \Drupal\commerce_log\LogStorageInterface
   */
  protected LogStorageInterface $logStorage;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * Constructs a new WaitListEventSubscriber.
   *
   * @param \Drupal\Core\Action\ActionManager $action_manager
   *   The action manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(ActionManager $action_manager, EntityTypeManagerInterface $entity_type_manager, LoggerInterface $logger, ModuleHandlerInterface $module_handler) {
    $this->actionManager = $action_manager;
    $this->logger = $logger;
    $this->moduleHandler = $module_handler;

    if ($this->moduleHandler->moduleExists('commerce_log')) {
      $this->logStorage = $entity_type_manager->getStorage('commerce_log');
    }
  }

  /**
   * Processes the event before autofill saves the registration.
   *
   * @param \Drupal\registration\Event\RegistrationEvent $event
   *   The registration event.
   */
  public function beforeAutoFill(RegistrationEvent $event) {
    $registration = $event->getRegistration();
    // Find the order item containing the registration.
    if ($old_order_item = $this->getOrderItem($registration, 'completed')) {
      // Clone the order item to a new order.
      $new_order = $this->createOrder($registration, $old_order_item);

      // Move the registration to the new order.
      $registration->set('order_id', $new_order->id());
      $old_order_item->set('registration', NULL);
      $old_order_item->save();

      // Log the move to both orders.
      if ($this->moduleHandler->moduleExists('commerce_log')) {
        // New order.
        $this->logStorage
          ->generate($new_order, 'registration_moved_from', [
            'registration' => $registration->id(),
            'old_order' => $old_order_item->getOrder()->getOrderNumber(),
          ])
          ->save();

        // Old order.
        $this->logStorage
          ->generate($old_order_item->getOrder(), 'registration_moved_to', [
            'registration' => $registration->id(),
            'new_order' => $new_order->getOrderNumber(),
          ])
          ->save();
      }
    }
  }

  /**
   * Processes the event after autofill moves a registration off the wait list.
   *
   * @param \Drupal\registration\Event\RegistrationEvent $event
   *   The registration event.
   */
  public function onAutoFill(RegistrationEvent $event) {
    $registration = $event->getRegistration();
    // Find the order item containing the registration.
    if ($order_item = $this->getOrderItem($registration, 'draft')) {
      // Save the order so pricing recalculates.
      $order = $order_item->getOrder();
      $order->unsetData('commerce_registration_waitlist_synthetic');
      $order->setRefreshState(OrderInterface::REFRESH_ON_SAVE);
      $order->save();

      // Send a confirmation email for registrations moved off the waiting list
      // if this is enabled for the registration type.
      $registration_type = $registration->getType();
      if ($registration_type->getThirdPartySetting('commerce_registration_waitlist', 'confirmation_email')) {
        $configuration['recipient'] = $registration->getEmail();
        $configuration['subject'] = $registration_type->getThirdPartySetting('commerce_registration_waitlist', 'confirmation_email_subject');
        $configuration['message'] = $registration_type->getThirdPartySetting('commerce_registration_waitlist', 'confirmation_email_message');
        $configuration['log_message'] = FALSE;
        $action = $this->actionManager->createInstance('registration_send_email_action');
        $action->setConfiguration($configuration);
        if ($action->execute($registration)) {
          $this->logger->info('Sent space available confirmation email to %recipient', [
            '%recipient' => $configuration['recipient'],
          ]);

          // Log the email.
          if ($this->moduleHandler->moduleExists('commerce_log')) {
            $this->logStorage
              ->generate($order, 'mail_space_available', [
                'to_email' => $registration->getEmail(),
              ])
              ->save();
          }
        }
      }

      // Log autofill completion.
      if ($this->moduleHandler->moduleExists('commerce_log')) {
        $this->logStorage
          ->generate($order, 'registration_autofill_completed')
          ->save();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      RegistrationWaitListEvents::REGISTRATION_WAITLIST_AUTOFILL => 'onAutoFill',
      RegistrationWaitListEvents::REGISTRATION_WAITLIST_PREAUTOFILL => 'beforeAutoFill',
    ];
  }

  /**
   * Creates a new order from a given registration and order item.
   *
   * @param \Drupal\registration\Entity\RegistrationInterface $registration
   *   The registration.
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $order_item
   *   The order item.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface
   *   The new order.
   */
  protected function createOrder(RegistrationInterface $registration, OrderItemInterface $order_item): OrderInterface {
    $order = $order_item->getOrder();

    $new_order = Order::create([
      'type' => $order->bundle(),
      'store_id' => $order->getStore()->id(),
      'uid' => $order->getCustomerId(),
      'mail' => $order->getEmail(),
      'state' => 'draft',
      'cart' => TRUE,
    ]);
    $new_order->setData('commerce_registration_waitlist_synthetic', TRUE);
    $new_order->save();

    // Log new order creation.
    if ($this->moduleHandler->moduleExists('commerce_log')) {
      $this->logStorage
        ->generate($new_order, 'registration_autofill_started')
        ->save();
      $this->logStorage
        ->generate($new_order, 'order_cloned', [
          'old_order' => $order->getOrderNumber(),
        ])
        ->save();
    }

    $new_order_item = clone $order_item;
    $new_order_item->enforceIsNew();
    $new_order_item->set('order_item_id', NULL);
    $new_order_item->set('uuid', \Drupal::service('uuid')->generate());
    $new_order_item->set('order_id', $new_order->id());
    $new_order_item->set('registration', $registration->id());
    $new_order_item->save();

    $new_order->addItem($new_order_item);
    $new_order->save();
    return $new_order;
  }

  /**
   * Gets the order item that references a given registration.
   *
   * @param \Drupal\registration\Entity\RegistrationInterface $registration
   *   The registration.
   * @param string $state_id
   *   The order state that is checked.
   *
   * @return \Drupal\commerce_order\Entity\OrderItemInterface|null
   *   The order item, if available.
   */
  protected function getOrderItem(RegistrationInterface $registration, string $state_id): ?OrderItemInterface {
    if (!$registration->get('order_id')->isEmpty()) {
      $referenced_entities = $registration->get('order_id')->referencedEntities();
      if ($order = reset($referenced_entities)) {
        $synthetic = $order->getData('commerce_registration_waitlist_synthetic');
        if ($order->getState()->getId() == $state_id) {
          foreach ($order->getItems() as $order_item) {
            if (!$order_item->get('registration')->isEmpty()) {
              $referenced_entities = $order_item->get('registration')->referencedEntities();
              if ($order_item_registration = reset($referenced_entities)) {
                if ($order_item_registration->id() == $registration->id()) {
                  // Match a completed order submitted by a customer.
                  if (($state_id == 'completed') && !$synthetic) {
                    return $order_item;
                  }
                  // Match a draft order created programmatically.
                  if (($state_id == 'draft') && $synthetic) {
                    return $order_item;
                  }
                }
              }
            }
          }
        }
      }
    }
    return NULL;
  }

}
