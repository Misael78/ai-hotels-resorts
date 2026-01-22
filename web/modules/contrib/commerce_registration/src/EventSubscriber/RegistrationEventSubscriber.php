<?php

namespace Drupal\commerce_registration\EventSubscriber;

use Drupal\registration\Event\RegistrationDataAlterEvent;
use Drupal\registration\Event\RegistrationEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides a registration event subscriber.
 */
class RegistrationEventSubscriber implements EventSubscriberInterface {

  /**
   * Processes a mail alter event.
   *
   * @param \Drupal\registration\Event\RegistrationDataAlterEvent $event
   *   The registration data alter event.
   */
  public function alterMail(RegistrationDataAlterEvent $event) {
    $params = $event->getData();
    if (isset($params['token_entities'])) {
      // Support product tokens if product variation tokens are supported.
      if (isset($params['token_entities']['commerce_product_variation']) && !isset($params['token_entities']['commerce_product'])) {
        $params['token_entities']['commerce_product'] = $params['token_entities']['commerce_product_variation']->getProduct();
        $event->setData($params);
      }
      // Support order tokens if the registration is associated with an order.
      if (isset($params['token_entities']['registration']) && !isset($params['token_entities']['commerce_order'])) {
        if (!$params['token_entities']['registration']->get('order_id')->isEmpty()) {
          if ($order = $params['token_entities']['registration']->get('order_id')->entity) {
            $params['token_entities']['commerce_order'] = $order;
            $event->setData($params);
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      RegistrationEvents::REGISTRATION_ALTER_MAIL => 'alterMail',
    ];
  }

}
