<?php

namespace Drupal\eca_commerce\Plugin\ECA\Event;

use Drupal\commerce\Event\CommerceEvents;
use Drupal\commerce\Event\FilterConditionsEvent;
use Drupal\commerce\Event\PostMailSendEvent;
use Drupal\commerce\Event\ReferenceablePluginTypesEvent;
use Drupal\commerce_cart\Event\CartEmptyEvent;
use Drupal\commerce_cart\Event\CartEntityAddEvent;
use Drupal\commerce_cart\Event\CartEvents;
use Drupal\commerce_cart\Event\CartOrderItemAddEvent;
use Drupal\commerce_cart\Event\CartOrderItemRemoveEvent;
use Drupal\commerce_cart\Event\CartOrderItemUpdateEvent;
use Drupal\commerce_cart\Event\OrderItemComparisonFieldsEvent;
use Drupal\commerce_checkout\Event\CheckoutCompletionRegisterEvent;
use Drupal\commerce_checkout\Event\CheckoutEvents;
use Drupal\commerce_order\Event\OrderAssignEvent;
use Drupal\commerce_order\Event\OrderEvent;
use Drupal\commerce_order\Event\OrderEvents;
use Drupal\commerce_order\Event\OrderItemEvent;
use Drupal\commerce_order\Event\OrderLabelEvent;
use Drupal\commerce_order\Event\OrderProfilesEvent;
use Drupal\commerce_payment\Event\FilterPaymentGatewaysEvent;
use Drupal\commerce_payment\Event\PaymentEvent;
use Drupal\commerce_payment\Event\PaymentEvents;
use Drupal\commerce_price\Event\NumberFormatDefinitionEvent;
use Drupal\commerce_price\Event\PriceEvents;
use Drupal\commerce_product\Event\FilterVariationsEvent;
use Drupal\commerce_product\Event\ProductAttributeValueEvent;
use Drupal\commerce_product\Event\ProductDefaultVariationEvent;
use Drupal\commerce_product\Event\ProductEvent;
use Drupal\commerce_product\Event\ProductEvents;
use Drupal\commerce_product\Event\ProductVariationAjaxChangeEvent;
use Drupal\commerce_product\Event\ProductVariationEvent;
use Drupal\commerce_promotion\Event\CouponEvent;
use Drupal\commerce_promotion\Event\FilterPromotionsEvent;
use Drupal\commerce_promotion\Event\PromotionEvent;
use Drupal\commerce_promotion\Event\PromotionEvents;
use Drupal\commerce_store\Event\StoreEvent;
use Drupal\commerce_store\Event\StoreEvents;
use Drupal\commerce_tax\Event\BuildZonesEvent;
use Drupal\commerce_tax\Event\CustomerProfileEvent;
use Drupal\commerce_tax\Event\TaxEvents;
use Drupal\eca\Attribute\EcaEvent;
use Drupal\eca\Attribute\Token;
use Drupal\eca\Plugin\ECA\Event\EventBase;

/**
 * Plugin implementation of the ECA Events for eca_commerce.
 */
#[EcaEvent(
  id: 'eca_commerce',
  deriver: 'Drupal\eca_commerce\Plugin\ECA\Event\CommerceEcaEventsDeriver',
  version_introduced: '1.0.0',
)]
class CommerceEcaEvents extends EventBase {

  /**
   * {@inheritdoc}
   */
  public static function definitions(): array {
    $definitions = [];

    self::getCoreDefinitions($definitions);

    if (class_exists(CartEvents::class)) {
      self::getCartDefinitions($definitions);
    }

    if (class_exists(CheckoutEvents::class)) {
      self::getCheckoutDefinitions($definitions);
    }

    if (class_exists(OrderEvents::class)) {
      self::getOrderDefinitions($definitions);
    }

    if (class_exists(PaymentEvents::class)) {
      self::getPaymentDefinitions($definitions);
    }

    if (class_exists(PriceEvents::class)) {
      self::getPriceDefinitions($definitions);
    }

    if (class_exists(ProductEvents::class)) {
      self::getProductDefinitions($definitions);
    }

    if (class_exists(PromotionEvents::class)) {
      self::getPromotionDefinitions($definitions);
    }

    if (class_exists(StoreEvents::class)) {
      self::getStoreDefinitions($definitions);
    }

    if (class_exists(TaxEvents::class)) {
      self::getTaxDefinitions($definitions);
    }

    return $definitions;
  }

  /**
   * Get the core commerce event definitions.
   *
   * @param array $definitions
   *   Current Definitions.
   */
  private static function getCoreDefinitions(array &$definitions): void {
    $definitions['filter_conditions'] = [
      'label' => 'Core: Filter conditions',
      'event_name' => CommerceEvents::FILTER_CONDITIONS,
      'event_class' => FilterConditionsEvent::class,
    ];
    $definitions['post_mail_send'] = [
      'label' => 'Core: Post Mail Send',
      'event_name' => CommerceEvents::POST_MAIL_SEND,
      'event_class' => PostMailSendEvent::class,
    ];
    $definitions['referenceable_plugin_types'] = [
      'label' => 'Core: Referenceable Plugin Types',
      'event_name' => CommerceEvents::REFERENCEABLE_PLUGIN_TYPES,
      'event_class' => ReferenceablePluginTypesEvent::class,
    ];
  }

  /**
   * Get the commerce cart event definitions.
   *
   * @param array $definitions
   *   Current Definitions.
   */
  private static function getCartDefinitions(array &$definitions): void {
    $definitions['cart_empty'] = [
      'label' => 'Cart: The cart was emptied',
      'event_name' => CartEvents::CART_EMPTY,
      'event_class' => CartEmptyEvent::class,
    ];
    $definitions['cart_entity_add'] = [
      'label' => 'Cart: An entity was added to the cart',
      'event_name' => CartEvents::CART_ENTITY_ADD,
      'event_class' => CartEntityAddEvent::class,
    ];
    $definitions['cart_order_item_add'] = [
      'label' => 'Cart: Add order item to cart',
      'event_name' => CartEvents::CART_ORDER_ITEM_ADD,
      'event_class' => CartOrderItemAddEvent::class,
    ];
    $definitions['cart_order_item_update'] = [
      'label' => 'Cart: Update order item on cart',
      'event_name' => CartEvents::CART_ORDER_ITEM_UPDATE,
      'event_class' => CartOrderItemUpdateEvent::class,
    ];
    $definitions['cart_order_item_remove'] = [
      'label' => 'Cart: Remove order item from cart',
      'event_name' => CartEvents::CART_ORDER_ITEM_REMOVE,
      'event_class' => CartOrderItemRemoveEvent::class,
    ];
    $definitions['cart_order_item_comparison_fields'] = [
      'label' => 'Cart: Order item comparison fields',
      'event_name' => CartEvents::ORDER_ITEM_COMPARISON_FIELDS,
      'event_class' => OrderItemComparisonFieldsEvent::class,
    ];
  }

  /**
   * Get the checkout event definitions.
   *
   * @param array $definitions
   *   Current Definitions.
   */
  private static function getCheckoutDefinitions(array &$definitions): void {
    $definitions['checkout_completion'] = [
      'label' => 'Checkout: Completion',
      'event_name' => CheckoutEvents::COMPLETION,
      'event_class' => OrderEvent::class,
    ];
    $definitions['checkout_completion_register'] = [
      'label' => 'Checkout: Completion register',
      'event_name' => CheckoutEvents::COMPLETION_REGISTER,
      'event_class' => CheckoutCompletionRegisterEvent::class,
    ];
  }

  /**
   * Get the commerce order event definitions.
   *
   * @param array $definitions
   *   Current Definitions.
   */
  private static function getOrderDefinitions(array &$definitions): void {
    $definitions['order_assign'] = [
      'label' => 'Order: Assign order',
      'event_name' => OrderEvents::ORDER_ASSIGN,
      'event_class' => OrderAssignEvent::class,
    ];
    $definitions['order_label'] = [
      'label' => 'Order: After alter label',
      'event_name' => OrderEvents::ORDER_LABEL,
      'event_class' => OrderLabelEvent::class,
    ];
    $definitions['order_paid'] = [
      'label' => 'Order: Paid',
      'event_name' => OrderEvents::ORDER_PAID,
      'event_class' => OrderEvent::class,
    ];
    $definitions['order_profiles'] = [
      'label' => 'Order: Profile load',
      'event_name' => OrderEvents::ORDER_PROFILES,
      'event_class' => OrderProfilesEvent::class,
    ];
    $definitions['order_load'] = [
      'label' => 'Order: Load',
      'event_name' => OrderEvents::ORDER_LOAD,
      'event_class' => OrderEvent::class,
    ];
    $definitions['order_create'] = [
      'label' => 'Order: Create',
      'event_name' => OrderEvents::ORDER_CREATE,
      'event_class' => OrderEvent::class,
    ];
    $definitions['order_presave'] = [
      'label' => 'Order: Presave',
      'event_name' => OrderEvents::ORDER_PRESAVE,
      'event_class' => OrderEvent::class,
    ];
    $definitions['order_insert'] = [
      'label' => 'Order: Insert',
      'event_name' => OrderEvents::ORDER_INSERT,
      'event_class' => OrderEvent::class,
    ];
    $definitions['order_update'] = [
      'label' => 'Order: Update',
      'event_name' => OrderEvents::ORDER_UPDATE,
      'event_class' => OrderEvent::class,
    ];
    $definitions['order_predelete'] = [
      'label' => 'Order: Predelete',
      'event_name' => OrderEvents::ORDER_PREDELETE,
      'event_class' => OrderEvent::class,
    ];
    $definitions['order_delete'] = [
      'label' => 'Order: Delete',
      'event_name' => OrderEvents::ORDER_DELETE,
      'event_class' => OrderEvent::class,
    ];
    $definitions['order_item_load'] = [
      'label' => 'Order: Item load',
      'event_name' => OrderEvents::ORDER_ITEM_LOAD,
      'event_class' => OrderItemEvent::class,
    ];
    $definitions['order_item_create'] = [
      'label' => 'Order: Item create',
      'event_name' => OrderEvents::ORDER_ITEM_CREATE,
      'event_class' => OrderItemEvent::class,
    ];
    $definitions['order_item_presave'] = [
      'label' => 'Order: Item presave',
      'event_name' => OrderEvents::ORDER_ITEM_PRESAVE,
      'event_class' => OrderItemEvent::class,
    ];
    $definitions['order_item_insert'] = [
      'label' => 'Order: Item insert',
      'event_name' => OrderEvents::ORDER_ITEM_INSERT,
      'event_class' => OrderItemEvent::class,
    ];
    $definitions['order_item_update'] = [
      'label' => 'Order: Item update',
      'event_name' => OrderEvents::ORDER_ITEM_UPDATE,
      'event_class' => OrderItemEvent::class,
    ];
    $definitions['order_item_predelete'] = [
      'label' => 'Order: Item predelete',
      'event_name' => OrderEvents::ORDER_ITEM_PREDELETE,
      'event_class' => OrderItemEvent::class,
    ];
    $definitions['order_item_delete'] = [
      'label' => 'Order: Item delete',
      'event_name' => OrderEvents::ORDER_ITEM_DELETE,
      'event_class' => OrderItemEvent::class,
    ];
  }

  /**
   * Get the payment event definitions.
   *
   * @param array $definitions
   *   Current Definitions.
   */
  private static function getPaymentDefinitions(array &$definitions): void {
    $definitions['payment_filter_payment_gateways'] = [
      'label' => 'Payment: Filter Payment Gateways',
      'event_name' => PaymentEvents::FILTER_PAYMENT_GATEWAYS,
      'event_class' => FilterPaymentGatewaysEvent::class,
    ];
    $definitions['payment_load'] = [
      'label' => 'Payment: Load',
      'event_name' => PaymentEvents::PAYMENT_LOAD,
      'event_class' => PaymentEvent::class,
    ];
    $definitions['payment_create'] = [
      'label' => 'Payment: Create',
      'event_name' => PaymentEvents::PAYMENT_CREATE,
      'event_class' => PaymentEvent::class,
    ];
    $definitions['payment_presave'] = [
      'label' => 'Payment: Presave',
      'event_name' => PaymentEvents::PAYMENT_PRESAVE,
      'event_class' => PaymentEvent::class,
    ];
    $definitions['payment_insert'] = [
      'label' => 'Payment: Insert',
      'event_name' => PaymentEvents::PAYMENT_INSERT,
      'event_class' => PaymentEvent::class,
    ];
    $definitions['payment_update'] = [
      'label' => 'Payment: Update',
      'event_name' => PaymentEvents::PAYMENT_UPDATE,
      'event_class' => PaymentEvent::class,
    ];
    $definitions['payment_predelete'] = [
      'label' => 'Payment: Predelete',
      'event_name' => PaymentEvents::PAYMENT_PREDELETE,
      'event_class' => PaymentEvent::class,
    ];
    $definitions['payment_delete'] = [
      'label' => 'Payment: Delete',
      'event_name' => PaymentEvents::PAYMENT_DELETE,
      'event_class' => PaymentEvent::class,
    ];
  }

  /**
   * Get the price event definitions.
   *
   * @param array $definitions
   *   Current Definitions.
   */
  private static function getPriceDefinitions(array &$definitions): void {
    $definitions['price_number_format'] = [
      'label' => 'Price: Number format',
      'event_name' => PriceEvents::NUMBER_FORMAT,
      'event_class' => NumberFormatDefinitionEvent::class,
    ];
  }

  /**
   * Get the product event definitions.
   *
   * @param array $definitions
   *   Current Definitions.
   */
  private static function getProductDefinitions(array &$definitions): void {
    $definitions['product_load'] = [
      'label' => 'Product: Load',
      'event_name' => ProductEvents::PRODUCT_LOAD,
      'event_class' => ProductEvent::class,
    ];
    $definitions['product_create'] = [
      'label' => 'Product: Create',
      'event_name' => ProductEvents::PRODUCT_CREATE,
      'event_class' => ProductEvent::class,
    ];
    $definitions['product_presave'] = [
      'label' => 'Product: Presave',
      'event_name' => ProductEvents::PRODUCT_PRESAVE,
      'event_class' => ProductEvent::class,
    ];
    $definitions['product_insert'] = [
      'label' => 'Product: Insert',
      'event_name' => ProductEvents::PRODUCT_INSERT,
      'event_class' => ProductEvent::class,
    ];
    $definitions['product_update'] = [
      'label' => 'Product: Update',
      'event_name' => ProductEvents::PRODUCT_UPDATE,
      'event_class' => ProductEvent::class,
    ];
    $definitions['product_predelete'] = [
      'label' => 'Product: Predelete',
      'event_name' => ProductEvents::PRODUCT_PREDELETE,
      'event_class' => ProductEvent::class,
    ];
    $definitions['product_delete'] = [
      'label' => 'Product: Delete',
      'event_name' => ProductEvents::PRODUCT_DELETE,
      'event_class' => ProductEvent::class,
    ];
    $definitions['product_translation_insert'] = [
      'label' => 'Product: Translation insert',
      'event_name' => ProductEvents::PRODUCT_TRANSLATION_INSERT,
      'event_class' => ProductEvent::class,
    ];
    $definitions['product_translation_delete'] = [
      'label' => 'Product: Translation delete',
      'event_name' => ProductEvents::PRODUCT_TRANSLATION_DELETE,
      'event_class' => ProductEvent::class,
    ];
    $definitions['product_default_variation'] = [
      'label' => 'Product: Default variation',
      'event_name' => ProductEvents::PRODUCT_DEFAULT_VARIATION,
      'event_class' => ProductDefaultVariationEvent::class,
    ];
    $definitions['product_variation_ajax_change'] = [
      'label' => 'Product: Variation Ajax Change',
      'event_name' => ProductEvents::PRODUCT_VARIATION_AJAX_CHANGE,
      'event_class' => ProductVariationAjaxChangeEvent::class,
    ];
    $definitions['product_variation_load'] = [
      'label' => 'Product: Variation load',
      'event_name' => ProductEvents::PRODUCT_VARIATION_LOAD,
      'event_class' => ProductVariationEvent::class,
    ];
    $definitions['product_variation_create'] = [
      'label' => 'Product: Variation create',
      'event_name' => ProductEvents::PRODUCT_VARIATION_CREATE,
      'event_class' => ProductVariationEvent::class,
    ];
    $definitions['product_variation_presave'] = [
      'label' => 'Product: Variation presave',
      'event_name' => ProductEvents::PRODUCT_VARIATION_PRESAVE,
      'event_class' => ProductVariationEvent::class,
    ];
    $definitions['product_variation_insert'] = [
      'label' => 'Product: Variation insert',
      'event_name' => ProductEvents::PRODUCT_VARIATION_INSERT,
      'event_class' => ProductVariationEvent::class,
    ];
    $definitions['product_variation_update'] = [
      'label' => 'Product: Variation update',
      'event_name' => ProductEvents::PRODUCT_VARIATION_UPDATE,
      'event_class' => ProductVariationEvent::class,
    ];
    $definitions['product_variation_predelete'] = [
      'label' => 'Product: Variation predelete',
      'event_name' => ProductEvents::PRODUCT_VARIATION_PREDELETE,
      'event_class' => ProductVariationEvent::class,
    ];
    $definitions['product_variation_delete'] = [
      'label' => 'Product: Variation delete',
      'event_name' => ProductEvents::PRODUCT_VARIATION_DELETE,
      'event_class' => ProductVariationEvent::class,
    ];
    $definitions['product_variation_translation_insert'] = [
      'label' => 'Product: Variation translation insert',
      'event_name' => ProductEvents::PRODUCT_VARIATION_TRANSLATION_INSERT,
      'event_class' => ProductVariationEvent::class,
    ];
    $definitions['product_variation_translation_delete'] = [
      'label' => 'Product: Variation translation delete',
      'event_name' => ProductEvents::PRODUCT_VARIATION_TRANSLATION_DELETE,
      'event_class' => ProductVariationEvent::class,
    ];
    $definitions['product_filter_variations'] = [
      'label' => 'Product: Filter Variations',
      'event_name' => ProductEvents::FILTER_VARIATIONS,
      'event_class' => FilterVariationsEvent::class,
    ];
  }

  /**
   * Get the promotion event definitions.
   *
   * @param array $definitions
   *   Current Definitions.
   */
  private static function getPromotionDefinitions(array &$definitions): void {
    $definitions['promotion_filter'] = [
      'label' => 'Promotion: Filter',
      'event_name' => PromotionEvents::FILTER_PROMOTIONS,
      'event_class' => FilterPromotionsEvent::class,
    ];
    $definitions['promotion_load'] = [
      'label' => 'Promotion: Load',
      'event_name' => PromotionEvents::PROMOTION_LOAD,
      'event_class' => PromotionEvent::class,
    ];
    $definitions['promotion_create'] = [
      'label' => 'Promotion: Create',
      'event_name' => PromotionEvents::PROMOTION_CREATE,
      'event_class' => PromotionEvent::class,
    ];
    $definitions['promotion_presave'] = [
      'label' => 'Promotion: Presave',
      'event_name' => PromotionEvents::PROMOTION_PRESAVE,
      'event_class' => PromotionEvent::class,
    ];
    $definitions['promotion_insert'] = [
      'label' => 'Promotion: Insert',
      'event_name' => PromotionEvents::PROMOTION_INSERT,
      'event_class' => PromotionEvent::class,
    ];
    $definitions['promotion_update'] = [
      'label' => 'Promotion: Update',
      'event_name' => PromotionEvents::PROMOTION_UPDATE,
      'event_class' => PromotionEvent::class,
    ];
    $definitions['promotion_predelete'] = [
      'label' => 'Promotion: Predelete',
      'event_name' => PromotionEvents::PROMOTION_PREDELETE,
      'event_class' => PromotionEvent::class,
    ];
    $definitions['promotion_delete'] = [
      'label' => 'Promotion: Delete',
      'event_name' => PromotionEvents::PROMOTION_DELETE,
      'event_class' => PromotionEvent::class,
    ];
    $definitions['promotion_translation_insert'] = [
      'label' => 'Promotion: Translate Insert',
      'event_name' => PromotionEvents::PROMOTION_TRANSLATION_INSERT,
      'event_class' => PromotionEvent::class,
    ];
    $definitions['promotion_translation_delete'] = [
      'label' => 'Promotion: Translate Delete',
      'event_name' => PromotionEvents::PROMOTION_TRANSLATION_DELETE,
      'event_class' => PromotionEvent::class,
    ];
    $definitions['promotion_coupon_load'] = [
      'label' => 'Promotion: Coupon load',
      'event_name' => PromotionEvents::COUPON_LOAD,
      'event_class' => CouponEvent::class,
    ];
    $definitions['promotion_coupon_create'] = [
      'label' => 'Promotion: Coupon create',
      'event_name' => PromotionEvents::COUPON_CREATE,
      'event_class' => CouponEvent::class,
    ];
    $definitions['promotion_coupon_presave'] = [
      'label' => 'Promotion: Coupon presave',
      'event_name' => PromotionEvents::COUPON_PRESAVE,
      'event_class' => CouponEvent::class,
    ];
    $definitions['promotion_coupon_insert'] = [
      'label' => 'Promotion: Coupon insert',
      'event_name' => PromotionEvents::COUPON_INSERT,
      'event_class' => CouponEvent::class,
    ];
    $definitions['promotion_coupon_update'] = [
      'label' => 'Promotion: Coupon update',
      'event_name' => PromotionEvents::COUPON_UPDATE,
      'event_class' => CouponEvent::class,
    ];
    $definitions['promotion_coupon_predelete'] = [
      'label' => 'Promotion: Coupon predelete',
      'event_name' => PromotionEvents::COUPON_PREDELETE,
      'event_class' => CouponEvent::class,
    ];
    $definitions['promotion_coupon_delete'] = [
      'label' => 'Promotion: Coupon delete',
      'event_name' => PromotionEvents::COUPON_DELETE,
      'event_class' => CouponEvent::class,
    ];
  }

  /**
   * Get the store event definitions.
   *
   * @param array $definitions
   *   Current Definitions.
   */
  private static function getStoreDefinitions(array &$definitions): void {
    $definitions['store_load'] = [
      'label' => 'Store: Load',
      'event_name' => StoreEvents::STORE_LOAD,
      'event_class' => StoreEvent::class,
    ];
    $definitions['store_create'] = [
      'label' => 'Store: Create',
      'event_name' => StoreEvents::STORE_CREATE,
      'event_class' => StoreEvent::class,
    ];
    $definitions['store_presave'] = [
      'label' => 'Store: Presave',
      'event_name' => StoreEvents::STORE_PRESAVE,
      'event_class' => StoreEvent::class,
    ];
    $definitions['store_insert'] = [
      'label' => 'Store: Insert',
      'event_name' => StoreEvents::STORE_INSERT,
      'event_class' => StoreEvent::class,
    ];
    $definitions['store_update'] = [
      'label' => 'Store: Update',
      'event_name' => StoreEvents::STORE_UPDATE,
      'event_class' => StoreEvent::class,
    ];
    $definitions['store_predelete'] = [
      'label' => 'Store: Predelete',
      'event_name' => StoreEvents::STORE_PREDELETE,
      'event_class' => StoreEvent::class,
    ];
    $definitions['store_delete'] = [
      'label' => 'Store: Delete',
      'event_name' => StoreEvents::STORE_DELETE,
      'event_class' => StoreEvent::class,
    ];
    $definitions['store_translation_insert'] = [
      'label' => 'Store: Translation insert',
      'event_name' => StoreEvents::STORE_TRANSLATION_INSERT,
      'event_class' => StoreEvent::class,
    ];
    $definitions['store_translation_delete'] = [
      'label' => 'Store: Translation delete',
      'event_name' => StoreEvents::STORE_TRANSLATION_DELETE,
      'event_class' => StoreEvent::class,
    ];
  }

  /**
   * Get the tax event definitions.
   *
   * @param array $definitions
   *   Current Definitions.
   */
  private static function getTaxDefinitions(array &$definitions): void {
    $definitions['tax_build'] = [
      'label' => 'Tax: Build',
      'event_name' => TaxEvents::BUILD_ZONES,
      'event_class' => BuildZonesEvent::class,
    ];
    $definitions['tax_customer_profile'] = [
      'label' => 'Tax: Customer profile',
      'event_name' => TaxEvents::CUSTOMER_PROFILE,
      'event_class' => CustomerProfileEvent::class,
    ];
  }

  /**
   * {@inheritdoc}
   */
  #[Token(
    name: 'cart',
    description: 'The cart entity.',
    classes: [
      CartEmptyEvent::class,
      CartEntityAddEvent::class,
      CartOrderItemAddEvent::class,
      CartOrderItemRemoveEvent::class,
      CartOrderItemUpdateEvent::class,
    ]
  )]
  #[Token(
    name: 'commerce_coupon',
    description: 'The coupon entity.',
    classes: [
      CouponEvent::class,
    ]
  )]
  #[Token(
    name: 'commerce_customer',
    description: 'The current customer, this is a Drupal User.',
    classes: [
      OrderAssignEvent::class,
    ]
  )]
  #[Token(
    name: 'commerce_definition',
    description: 'This is the number format definition.',
    classes: [
      NumberFormatDefinitionEvent::class,
    ]
  )]
  #[Token(
    name: 'commerce_order',
    description: 'The order entity.',
    classes: [
      CheckoutCompletionRegisterEvent::class,
      OrderAssignEvent::class,
      OrderEvent::class,
      OrderLabelEvent::class,
      OrderProfilesEvent::class,
      FilterPaymentGatewaysEvent::class,
      FilterPromotionsEvent::class,
    ]
  )]
  #[Token(
    name: 'commerce_order_item',
    description: 'The order item entity.',
    classes: [
      CartEntityAddEvent::class,
      CartOrderItemAddEvent::class,
      CartOrderItemRemoveEvent::class,
      CartOrderItemUpdateEvent::class,
      OrderItemComparisonFieldsEvent::class,
      OrderItemEvent::class,
      CustomerProfileEvent::class,
    ]
  )]
  #[Token(
    name: 'commerce_order_items',
    description: 'An array of order item entities.',
    classes: [
      CartEmptyEvent::class,
    ]
  )]
  #[Token(
    name: 'commerce_order_item_comparison_fields',
    description: 'The fields to compare.',
    classes: [
      OrderItemComparisonFieldsEvent::class,
    ]
  )]
  #[Token(
    name: 'commerce_order_item_original',
    description: 'The original item data before the update.',
    classes: [
      CartOrderItemUpdateEvent::class,
    ]
  )]
  #[Token(
    name: 'commerce_order_label',
    description: 'The order label.',
    classes: [
      OrderLabelEvent::class,
    ]
  )]
  #[Token(
    name: 'commerce_order_payment_gateways',
    description: 'The payment gateway configuration.',
    classes: [
      FilterPaymentGatewaysEvent::class,
    ]
  )]
  #[Token(
    name: 'commerce_payment',
    description: 'The payment entity.',
    classes: [
      PaymentEvent::class,
    ]
  )]
  #[Token(
    name: 'commerce_product',
    description: 'The product entity. This is not the variation.',
    classes: [
      FilterVariationsEvent::class,
      ProductDefaultVariationEvent::class,
      ProductEvent::class,
    ]
  )]
  #[Token(
    name: 'commerce_product_attribute_value',
    description: 'The product attribute value entity.',
    classes: [
      ProductAttributeValueEvent::class,
    ]
  )]
  #[Token(
    name: 'commerce_product_variation',
    description: 'The product variation entity. This is not the product entity.',
    classes: [
      ProductVariationAjaxChangeEvent::class,
      ProductVariationEvent::class,
    ]
  )]
  #[Token(
    name: 'commerce_product_variations',
    description: 'The product variation entities. This is not the product entities.',
    classes: [
      FilterVariationsEvent::class,
    ]
  )]
  #[Token(
    name: 'commerce_product_variation_default',
    description: 'This is the default product variation.',
    classes: [
      ProductDefaultVariationEvent::class,
    ]
  )]
  #[Token(
    name: 'commerce_product_variation_response',
    description: 'The response when changing product variations via ajax.',
    classes: [
      ProductVariationAjaxChangeEvent::class,
    ]
  )]
  #[Token(
    name: 'commerce_product_variation_view_mode',
    description: 'The view mode for the product variation.',
    classes: [
      ProductVariationAjaxChangeEvent::class,
    ]
  )]
  #[Token(
    name: 'commerce_promotion',
    description: 'The promotion entity.',
    classes: [
      PromotionEvent::class,
    ]
  )]
  #[Token(
    name: 'commerce_promotions',
    description: 'An array of promotion entities.',
    classes: [
      FilterPromotionsEvent::class,
    ]
  )]
  #[Token(
    name: 'commerce_store',
    description: 'The store entity.',
    classes: [
      StoreEvent::class,
    ]
  )]
  #[Token(
    name: 'commerce_tax_plugin',
    description: 'The local tax type plugin.',
    classes: [
      BuildZonesEvent::class,
    ]
  )]
  #[Token(
    name: 'commerce_tax_zones',
    description: 'An array of tax zones.',
    classes: [
      BuildZonesEvent::class,
    ]
  )]
  #[Token(
    name: 'entity',
    description: 'This is a purchasable entity.',
    classes: [
      CartEntityAddEvent::class,
    ]
  )]
  #[Token(
    name: 'customer_profile',
    description: 'A customer profile.',
    classes: [
      CustomerProfileEvent::class,
    ]
  )]
  #[Token(
    name: 'profiles',
    description: 'An array of customer profiles.',
    classes: [
      OrderProfilesEvent::class,
    ]
  )]
  #[Token(
    name: 'quantity',
    description: 'A float of the quantity.',
    classes: [
      CartEntityAddEvent::class,
      CartOrderItemAddEvent::class,
    ]
  )]
  public function getData(string $key): mixed {
    $event = $this->event;
    switch ($key) {
      case 'cart':
        if ($event instanceof CartEmptyEvent
          || $event instanceof CartEntityAddEvent
          || $event instanceof CartOrderItemAddEvent
          || $event instanceof CartOrderItemRemoveEvent
          || $event instanceof CartOrderItemUpdateEvent
        ) {
          return $event->getCart();
        }
        break;

      case 'commerce_coupon':
        if ($event instanceof CouponEvent) {
          return $event->getCoupon();
        }
        break;

      case 'commerce_customer':
        if ($event instanceof OrderAssignEvent) {
          return $event->getCustomer();
        }
        break;

      case 'commerce_definition':
        if ($event instanceof NumberFormatDefinitionEvent) {
          return $event->getDefinition();
        }
        break;

      case 'commerce_order':
        if ($event instanceof CheckoutCompletionRegisterEvent
          || $event instanceof OrderAssignEvent
          || $event instanceof OrderEvent
          || $event instanceof OrderLabelEvent
          || $event instanceof OrderProfilesEvent
          || $event instanceof FilterPaymentGatewaysEvent
          || $event instanceof FilterPromotionsEvent
        ) {
          return $event->getOrder();
        }
        break;

      case 'commerce_order_item':
        if ($event instanceof CartEntityAddEvent
          || $event instanceof CartOrderItemAddEvent
          || $event instanceof CartOrderItemRemoveEvent
          || $event instanceof CartOrderItemUpdateEvent
          || $event instanceof OrderItemComparisonFieldsEvent
          || $event instanceof OrderItemEvent
          || $event instanceof CustomerProfileEvent
        ) {
          return $event->getOrderItem();
        }
        break;

      case 'commerce_order_items':
        if ($event instanceof CartEmptyEvent) {
          return $event->getOrderItems();
        }
        break;

      case 'commerce_order_item_comparison_fields':
        if ($event instanceof OrderItemComparisonFieldsEvent) {
          return $event->getComparisonFields();
        }
        break;

      case 'commerce_order_item_original':
        if ($event instanceof CartOrderItemUpdateEvent) {
          return $event->getOriginalOrderItem();
        }
        break;

      case 'commerce_order_label':
        if ($event instanceof OrderLabelEvent) {
          return $event->getLabel();
        }
        break;

      case 'commerce_order_payment_gateways':
        if ($event instanceof FilterPaymentGatewaysEvent) {
          return $event->getPaymentGateways();
        }
        break;

      case 'commerce_payment':
        if ($event instanceof PaymentEvent) {
          return $event->getPayment();
        }
        break;

      case 'commerce_product':
        if ($event instanceof FilterVariationsEvent
          || $event instanceof ProductDefaultVariationEvent
          || $event instanceof ProductEvent
        ) {
          return $event->getProduct();
        }
        break;

      case 'commerce_product_attribute_value':
        if ($event instanceof ProductAttributeValueEvent) {
          return $event->getAttributeValue();
        }
        break;

      case 'commerce_product_variation':
        if ($event instanceof ProductVariationAjaxChangeEvent
          || $event instanceof ProductVariationEvent
        ) {
          return $event->getProductVariation();
        }
        break;

      case 'commerce_product_variations':
        if ($event instanceof FilterVariationsEvent) {
          return $event->getVariations();
        }
        break;

      case 'commerce_product_variation_default':
        if ($event instanceof ProductDefaultVariationEvent) {
          return $event->getDefaultVariation();
        }
        break;

      case 'commerce_product_variation_response':
        if ($event instanceof ProductVariationAjaxChangeEvent) {
          return $event->getResponse();
        }
        break;

      case 'commerce_product_variation_view_mode':
        if ($event instanceof ProductVariationAjaxChangeEvent) {
          return $event->getViewMode();
        }
        break;

      case 'commerce_promotion':
        if ($event instanceof PromotionEvent) {
          return $event->getPromotion();
        }
        break;

      case 'commerce_promotions':
        if ($event instanceof FilterPromotionsEvent) {
          return $event->getPromotions();
        }
        break;

      case 'commerce_store':
        if ($event instanceof StoreEvent) {
          return $event->getStore();
        }
        break;

      case 'commerce_tax_plugin':
        if ($event instanceof BuildZonesEvent) {
          return $event->getPlugin();
        }
        break;

      case 'commerce_tax_zones':
        if ($event instanceof BuildZonesEvent) {
          return $event->getZones();
        }
        break;

      case 'entity':
        if ($event instanceof CartEntityAddEvent) {
          return $event->getEntity();
        }
        break;

      case 'customer_profile':
        if ($event instanceof CustomerProfileEvent) {
          return $event->getCustomerProfile();
        }
        break;

      case 'profiles':
        if ($event instanceof OrderProfilesEvent) {
          return $event->getProfiles();
        }
        break;

      case 'quantity':
        if ($event instanceof CartEntityAddEvent
          || $event instanceof CartOrderItemAddEvent
        ) {
          return $event->getQuantity();
        }
        break;

    }

    return parent::getData($key);
  }

}
