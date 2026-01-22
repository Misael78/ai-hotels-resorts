<?php

namespace Drupal\commerce_registration_waitlist\TwigExtension;

use Drupal\commerce_order\Entity\OrderItemInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Provides the Commerce Registration Wait List extensions.
 */
class WaitListTwigExtension extends AbstractExtension {

  /**
   * {@inheritdoc}
   */
  public function getFilters(): array {
    return [
      // phpcs:ignore
      new TwigFilter('order_item_waitlist_indicator', [$this, 'orderItemWaitListIndicator']),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return 'commerce_registration_waitlist.twig_extension';
  }

  /**
   * Renders an indicator if an order item has a wait listed registration.
   *
   * Example: {{ order_item|order_item_waitlist_indicator }}
   *
   * @param mixed $order_item
   *   The order item.
   *
   * @return array
   *   A renderable array with a waiting list indicator, or an empty array
   *   if the indicator is not applicable.
   *
   * @throws \InvalidArgumentException
   */
  public static function orderItemWaitListIndicator(mixed $order_item): array {
    if (empty($order_item)) {
      // Nothing to render.
      return [];
    }
    if (!($order_item instanceof OrderItemInterface)) {
      throw new \InvalidArgumentException('The "order_item_waitlist_indicator" filter must be given an order item.');
    }

    // Check the wait list flag.
    // @see commerce_registration_waitlist_order_item_presave()
    if ($order_item->getData('commerce_registration_waitlist')) {
      return [
        '#theme' => 'order_item_waitlist_indicator',
      ];
    }
    return [];
  }

}
