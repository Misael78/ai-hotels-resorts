<?php

namespace Drupal\commerce_registration_change_host\EventSubscriber;

use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\registration_change_host\Event\RegistrationChangeHostEvents;
use Drupal\registration_change_host\Event\RegistrationChangeHostPossibleHostsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Adds product variations as possible hosts.
 *
 * This adds every variation of a product as a possible host for other
 * variations of the same product, provided that the variation's label is
 * accessible and the variation is configured for registration.
 *
 * The variation does not need to be actually available as a possible host.
 * For example, if the variation has passed its close date or has filled its
 * capacity, it is still added as a possible host and will be displayed to the
 * user with a message explaining what prevents it from being available.
 */
class ChangeHostSubscriber implements EventSubscriberInterface {

  /**
   * Add possible hosts.
   *
   * @param \Drupal\registration_change_host\Event\RegistrationChangeHostPossibleHostsEvent $event
   *   The registration change host event.
   */
  public function getPossibleHosts(RegistrationChangeHostPossibleHostsEvent $event) {
    $registration = $event->getRegistration();
    $set = $event->getPossibleHostsSet();
    $entity = $registration->getHostEntity()->getEntity();
    if ($entity && $entity instanceof ProductVariationInterface) {
      $product = $entity->getProduct();
      if ($product) {
        foreach ($product->getVariations() as $variation) {
          $access = $variation->access('view label', NULL, TRUE);
          if ($variation->id() != $entity->id() && $access->isAllowed()) {
            $possible_host = $set->buildNewPossibleHost($variation);
            if ($possible_host->getHostEntity()->isConfiguredForRegistration()) {
              $set->addHost($possible_host);
            }
          }
          // A variation could become a possible host if it became accessible
          // or configured for registration.
          $set->addCacheableDependency($access);
          $set->addCacheableDependency($variation);
        }
        // Any new variations added to the product could be possible hosts.
        $set->addCacheableDependency($product);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      RegistrationChangeHostEvents::REGISTRATION_CHANGE_HOST_POSSIBLE_HOSTS => 'getPossibleHosts',
    ];
  }

}
