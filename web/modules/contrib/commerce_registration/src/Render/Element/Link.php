<?php

namespace Drupal\commerce_registration\Render\Element;

use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Url;

/**
 * Provides a trusted callback for links.
 */
class Link implements TrustedCallbackInterface {

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['preRender'];
  }

  /**
   * Pre-renders the link element.
   *
   * @param mixed $element
   *   The element.
   *
   * @return mixed
   *   The modified element.
   */
  public static function preRender(mixed $element): mixed {
    if (is_array($element) && isset($element['#url']) && ($element['#url'] instanceof Url) && $element['#url']->isRouted()) {
      if (str_contains($element['#url']->getRouteName(), 'commerce_product_variation')) {
        $parameters = $element['#url']->getRouteParameters();
        if (!empty($parameters['commerce_product_variation']) && empty($parameters['commerce_product'])) {
          $variation = \Drupal::entityTypeManager()->getStorage('commerce_product_variation')->load($parameters['commerce_product_variation']);

          /** @var \Drupal\commerce_product\Entity\ProductVariationInterface $variation */
          if ($variation) {
            $parameters['commerce_product'] = $variation->getProduct()->id();
            $element['#url']->setRouteParameters($parameters);
          }
        }
      }
    }
    return $element;
  }

}
