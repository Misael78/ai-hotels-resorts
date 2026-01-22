<?php

namespace Drupal\Tests\commerce_registration\Functional;

use Drupal\Tests\commerce_registration\Traits\ProductCreationTrait;
use Drupal\Tests\commerce_registration\Traits\ProductVariationCreationTrait;
use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\user\Entity\Role;

/**
 * Tests checkout of registration enabled product variations.
 *
 * @group commerce_registration
 */
class CheckoutTest extends CommerceRegistrationBrowserTestBase {

  use ProductCreationTrait;
  use ProductVariationCreationTrait;

  /**
   * The product.
   *
   * @var \Drupal\commerce_product\Entity\ProductInterface
   */
  protected ProductInterface $product;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->placeBlock('commerce_cart');

    $config = \Drupal::configFactory()->getEditable('commerce_checkout.commerce_checkout_flow.default');
    $config->set('configuration.display_checkout_progress_breadcrumb_links', TRUE);
    $config->save();

    // Create a manual payment gateway.
    $payment_gateway = $this->entityTypeManager
      ->getStorage('commerce_payment_gateway')
      ->create([
        'id' => 'manual',
        'label' => 'Manual',
        'plugin' => 'manual',
      ]);
    $payment_gateway->setPluginConfiguration([
      'collect_billing_information' => FALSE,
    ]);
    $payment_gateway->save();

    // Set up a product with a registration enabled variation.
    $this->product = $this->createAndSaveProduct();
    $variation = $this->createAndSaveVariation($this->product);
    $handler = $this->entityTypeManager->getHandler('commerce_product_variation', 'registration_host_entity');
    $host_entity = $handler->createHostEntity($variation);
    $settings = $host_entity->getSettings();
    $settings->set('status', TRUE);
    $settings->save();
  }

  /**
   * Tests the registration process checkout pane.
   */
  public function testRegistrationProcessCheckoutPane() {
    // Enable the payment and registration process panes. Note that the
    // registration process pane must come before the payment process pane.
    $this->drupalGet('admin/commerce/config/checkout-flows/manage/default');
    $edit = [
      'configuration[panes][registration_process][step_id]' => 'payment',
      'configuration[panes][registration_process][weight]' => 0,
      'configuration[panes][payment_process][step_id]' => 'payment',
      'configuration[panes][payment_process][weight]' => 10,
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Saved the Default checkout flow.');

    // No registrants yet.
    $this->drupalGet('product/' . $this->product->id() . '/registrations');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('There are no registrants for My event');

    // Load the product detail page.
    $this->drupalGet($this->product->toUrl());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('My event');

    // Add to cart.
    $this->submitForm([], 'Add to cart');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('1 item');

    // Checkout.
    $cart_link = $this->getSession()->getPage()->findLink('your cart');
    $cart_link->click();
    $this->submitForm([], 'Checkout');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Order information');

    // Review.
    $this->submitForm([], 'Continue to review');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Order summary');

    // Complete checkout.
    $this->submitForm([], 'Pay and complete purchase');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Your order number is 1. You can view your order on your account page when logged in.');
    $this->assertSession()->pageTextContains('0 items');

    // A registration was created during checkout.
    $this->drupalGet('product/' . $this->product->id() . '/registrations');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('List of registrations for My event. 1 space is filled.');
  }

  /**
   * Tests the registration process checkout pane.
   *
   * Handles the not configured for registration case.
   */
  public function testRegistrationProcessCheckoutPaneNotConfigured() {
    // Enable the payment and registration process panes. Note that the
    // registration process pane must come before the payment process pane.
    $this->drupalGet('admin/commerce/config/checkout-flows/manage/default');
    $edit = [
      'configuration[panes][registration_process][step_id]' => 'payment',
      'configuration[panes][registration_process][weight]' => 0,
      'configuration[panes][payment_process][step_id]' => 'payment',
      'configuration[panes][payment_process][weight]' => 10,
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Saved the Default checkout flow.');

    // No registrants yet.
    $this->drupalGet('product/' . $this->product->id() . '/registrations');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('There are no registrants for My event');

    // Remove configuration for registration.
    $variations = $this->product->getVariations();
    $variation = reset($variations);
    $variation->set('event_registration', NULL);
    $variation->save();

    // Load the product detail page.
    $this->drupalGet($this->product->toUrl());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('My event');

    // Add to cart.
    $this->submitForm([], 'Add to cart');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('1 item');

    // Checkout.
    $cart_link = $this->getSession()->getPage()->findLink('your cart');
    $cart_link->click();
    $this->submitForm([], 'Checkout');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Order information');

    // Review.
    $this->submitForm([], 'Continue to review');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Order summary');

    // Complete checkout.
    $this->submitForm([], 'Pay and complete purchase');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Your order number is 1. You can view your order on your account page when logged in.');
    $this->assertSession()->pageTextContains('0 items');

    // Set configuration for registration so the manage registrations page
    // comes up.
    $variation->set('event_registration', 'conference');
    $variation->save();

    // A registration was NOT created during checkout.
    $this->drupalGet('product/' . $this->product->id() . '/registrations');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('There are no registrants for My event');
  }

  /**
   * Tests the registration information checkout pane.
   */
  public function testRegistrationInformationCheckoutPane() {
    // Allow anonymous users to register themselves.
    if ($anonymous_role = Role::load('anonymous')) {
      $anonymous_role->grantPermission('create conference registration self');
      $anonymous_role->save();
    }

    // Enable the payment and registration information panes.
    $this->drupalGet('admin/commerce/config/checkout-flows/manage/default');
    $edit = [
      'configuration[panes][registration_information][step_id]' => 'order_information',
      'configuration[panes][payment_process][step_id]' => 'payment',
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Saved the Default checkout flow.');

    // No registrants yet.
    $this->drupalGet('product/' . $this->product->id() . '/registrations');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('There are no registrants for My event');

    // Load the product detail page as anonymous.
    $this->drupalLogout();
    $this->drupalGet($this->product->toUrl());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('My event');

    // Add to cart.
    $this->submitForm([], 'Add to cart');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('1 item');

    // Checkout.
    $cart_link = $this->getSession()->getPage()->findLink('your cart');
    $cart_link->click();
    $this->submitForm([], 'Checkout');
    $this->assertSession()->statusCodeEquals(200);

    // Continue as guest to order information.
    $this->submitForm([], 'Continue as Guest');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Order information');

    // Review.
    $this->submitForm([
      'contact_information[email]' => 'guest@example.com',
      'contact_information[email_confirm]' => 'guest@example.com',
      'registration_information[variation-1-1][registration][registration][anon_mail][0][value]' => 'guest@example.com',
    ], 'Continue to review');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Order summary');

    // Complete checkout.
    $this->submitForm([], 'Pay and complete purchase');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Your order number is 1. You can view your order on your account page when logged in.');
    $this->assertSession()->pageTextContains('0 items');

    // A registration was created during checkout.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('product/' . $this->product->id() . '/registrations');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('List of registrations for My event. 1 space is filled.');
  }

  /**
   * Tests the registration information checkout pane.
   *
   * Handles the case when an extra required field is added to registrations.
   */
  public function testRegistrationInformationCheckoutPaneExtraField() {
    // Allow anonymous users to register themselves.
    if ($anonymous_role = Role::load('anonymous')) {
      $anonymous_role->grantPermission('create conference registration self');
      $anonymous_role->save();
    }

    // Enable the payment and registration information panes.
    $this->drupalGet('admin/commerce/config/checkout-flows/manage/default');
    $edit = [
      'configuration[panes][registration_information][step_id]' => 'order_information',
      'configuration[panes][payment_process][step_id]' => 'payment',
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Saved the Default checkout flow.');

    // No registrants yet.
    $this->drupalGet('product/' . $this->product->id() . '/registrations');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('There are no registrants for My event');

    // Add an extra text field to the Registration entity type. This should
    // then appear on the checkout form.
    $field_storage_values = [
      'field_name' => 'extra_text',
      'entity_type' => 'registration',
      'type' => 'string',
      'translatable' => TRUE,
    ];
    $this->entityTypeManager
      ->getStorage('field_storage_config')
      ->create($field_storage_values)
      ->save();
    $field_values = [
      'field_name' => 'extra_text',
      'entity_type' => 'registration',
      'bundle' => 'conference',
      'label' => 'Extra text',
      'translatable' => FALSE,
      'required' => TRUE,
    ];
    $this->entityTypeManager
      ->getStorage('field_config')
      ->create($field_values)
      ->save();

    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository */
    $display_repository = \Drupal::service('entity_display.repository');
    $entity_form_display = $display_repository->getFormDisplay('registration', 'conference', 'default');
    $entity_form_display->setComponent('extra_text', [
      'label' => 'hidden',
      'type' => 'string_textfield',
    ])->save();

    // Load the product detail page as anonymous.
    $this->drupalLogout();
    $this->drupalGet($this->product->toUrl());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('My event');

    // Add to cart.
    $this->submitForm([], 'Add to cart');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('1 item');

    // Checkout.
    $cart_link = $this->getSession()->getPage()->findLink('your cart');
    $cart_link->click();
    $this->submitForm([], 'Checkout');
    $this->assertSession()->statusCodeEquals(200);

    // Continue as guest to order information.
    $this->submitForm([], 'Continue as Guest');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Order information');

    // Required extra text field not provided.
    $this->submitForm([
      'contact_information[email]' => 'guest@example.com',
      'contact_information[email_confirm]' => 'guest@example.com',
      'registration_information[variation-1-1][registration][registration][anon_mail][0][value]' => 'guest@example.com',
    ], 'Continue to review');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Order information');
    $this->assertSession()->pageTextContains('Extra text field is required.');

    // Review.
    $this->submitForm([
      'contact_information[email]' => 'guest@example.com',
      'contact_information[email_confirm]' => 'guest@example.com',
      'registration_information[variation-1-1][registration][registration][anon_mail][0][value]' => 'guest@example.com',
      'registration_information[variation-1-1][registration][registration][extra_text][0][value]' => 'Extra text field value',
    ], 'Continue to review');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Order summary');

    // Complete checkout.
    $this->submitForm([], 'Pay and complete purchase');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Your order number is 1. You can view your order on your account page when logged in.');
    $this->assertSession()->pageTextContains('0 items');

    // A registration was created during checkout.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('product/' . $this->product->id() . '/registrations');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('List of registrations for My event. 1 space is filled.');

    // The registration has the extra field value provided during checkout.
    $variations = $this->product->getVariations();
    $variation = reset($variations);
    $handler = $this->entityTypeManager->getHandler('commerce_product_variation', 'registration_host_entity');
    $host_entity = $handler->createHostEntity($variation);
    $registrations = $host_entity->getRegistrationList();
    $registration = reset($registrations);
    $this->assertEquals('Extra text field value', $registration->get('extra_text')->getValue()[0]['value']);

    // The registration is not complete until payment is received.
    $this->assertFalse($registration->isComplete());
    $this->drupalGet('admin/commerce/orders/1/payments/1/operation/receive');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Receive payment');
    $this->submitForm([
      'payment[amount][number]' => '75.00',
    ], 'Receive');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Payment received.');
    $this->entityTypeManager->getStorage('registration')->resetCache([$registration->id()]);
    $registration = $this->entityTypeManager->getStorage('registration')->load($registration->id());
    $this->assertTrue($registration->isComplete());
  }

  /**
   * Tests registration complete status when a Held registration is paid.
   */
  public function testRegistrationProcessCheckoutPaneHeldStatus() {
    // Enable the payment and registration process panes. Note that the
    // registration process pane must come before the payment process pane.
    $this->drupalGet('admin/commerce/config/checkout-flows/manage/default');
    $edit = [
      'configuration[panes][registration_process][step_id]' => 'payment',
      'configuration[panes][registration_process][weight]' => 0,
      'configuration[panes][payment_process][step_id]' => 'payment',
      'configuration[panes][payment_process][weight]' => 10,
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Saved the Default checkout flow.');

    // No registrants yet.
    $this->drupalGet('product/' . $this->product->id() . '/registrations');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('There are no registrants for My event');

    // Load the product detail page.
    $this->drupalGet($this->product->toUrl());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('My event');

    // Add to cart.
    $this->submitForm([], 'Add to cart');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('1 item');

    // Checkout.
    $cart_link = $this->getSession()->getPage()->findLink('your cart');
    $cart_link->click();
    $this->submitForm([], 'Checkout');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Order information');

    // Review.
    $this->submitForm([], 'Continue to review');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Order summary');

    // Complete checkout.
    $this->submitForm([], 'Pay and complete purchase');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Your order number is 1. You can view your order on your account page when logged in.');
    $this->assertSession()->pageTextContains('0 items');

    // A registration was created during checkout.
    $this->drupalGet('product/' . $this->product->id() . '/registrations');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('List of registrations for My event. 1 space is filled.');

    // The registration is not complete because it has not been paid for yet.
    $variations = $this->product->getVariations();
    $variation = reset($variations);
    $handler = $this->entityTypeManager->getHandler('commerce_product_variation', 'registration_host_entity');
    $host_entity = $handler->createHostEntity($variation);
    $registrations = $host_entity->getRegistrationList();
    $registration = reset($registrations);
    $this->assertFalse($registration->isComplete());

    // A held registration is marked complete after payment is received.
    $registration->set('state', 'held');
    $registration->save();
    $this->drupalGet('admin/commerce/orders/1/payments/1/operation/receive');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Receive payment');
    $this->submitForm([
      'payment[amount][number]' => '75.00',
    ], 'Receive');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Payment received.');
    $this->entityTypeManager->getStorage('registration')->resetCache([$registration->id()]);
    $registration = $this->entityTypeManager->getStorage('registration')->load($registration->id());
    $this->assertTrue($registration->isComplete());
  }

  /**
   * Tests that registrations are canceled when carts are canceled.
   */
  public function testRegistrationOnOrderCartCancel() {
    // Allow anonymous users to register themselves.
    if ($anonymous_role = Role::load('anonymous')) {
      $anonymous_role->grantPermission('create conference registration self');
      $anonymous_role->save();
    }

    // Enable the payment and registration information panes.
    $this->drupalGet('admin/commerce/config/checkout-flows/manage/default');
    $edit = [
      'configuration[panes][registration_information][step_id]' => 'order_information',
      'configuration[panes][payment_process][step_id]' => 'payment',
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Saved the Default checkout flow.');

    // No registrants yet.
    $this->drupalGet('product/' . $this->product->id() . '/registrations');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('There are no registrants for My event');

    // Load the product detail page as anonymous.
    $this->drupalLogout();
    $this->drupalGet($this->product->toUrl());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('My event');

    // Add to cart.
    $this->submitForm([], 'Add to cart');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('1 item');

    // Checkout.
    $cart_link = $this->getSession()->getPage()->findLink('your cart');
    $cart_link->click();
    $this->submitForm([], 'Checkout');
    $this->assertSession()->statusCodeEquals(200);

    // Continue as guest to order information.
    $this->submitForm([], 'Continue as Guest');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Order information');

    // Review.
    $this->submitForm([
      'contact_information[email]' => 'guest@example.com',
      'contact_information[email_confirm]' => 'guest@example.com',
      'registration_information[variation-1-1][registration][registration][anon_mail][0][value]' => 'guest@example.com',
    ], 'Continue to review');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Order summary');

    // The registration is pending.
    $variations = $this->product->getVariations();
    $variation = reset($variations);
    $handler = $this->entityTypeManager->getHandler('commerce_product_variation', 'registration_host_entity');
    $host_entity = $handler->createHostEntity($variation);
    $registrations = $host_entity->getRegistrationList();
    $registration = reset($registrations);
    $this->assertEquals($registration->getState()->id(), 'pending');

    // Cancel the order. The registration is canceled.
    $order = $this->entityTypeManager->getStorage('commerce_order')->load(1);
    $order->set('state', 'canceled');
    $order->save();
    $this->entityTypeManager->getStorage('registration')->resetCache([$registration->id()]);
    $registration = $this->entityTypeManager->getStorage('registration')->load($registration->id());
    $this->assertTrue($registration->isCanceled());
  }

  /**
   * Tests registrations are not canceled when non-cart orders are canceled.
   */
  public function testRegistrationOnOrderNoCartCancel() {
    // Allow anonymous users to register themselves.
    if ($anonymous_role = Role::load('anonymous')) {
      $anonymous_role->grantPermission('create conference registration self');
      $anonymous_role->save();
    }

    // Enable the payment and registration information panes.
    $this->drupalGet('admin/commerce/config/checkout-flows/manage/default');
    $edit = [
      'configuration[panes][registration_information][step_id]' => 'order_information',
      'configuration[panes][payment_process][step_id]' => 'payment',
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Saved the Default checkout flow.');

    // No registrants yet.
    $this->drupalGet('product/' . $this->product->id() . '/registrations');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('There are no registrants for My event');

    // Load the product detail page as anonymous.
    $this->drupalLogout();
    $this->drupalGet($this->product->toUrl());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('My event');

    // Add to cart.
    $this->submitForm([], 'Add to cart');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('1 item');

    // Checkout.
    $cart_link = $this->getSession()->getPage()->findLink('your cart');
    $cart_link->click();
    $this->submitForm([], 'Checkout');
    $this->assertSession()->statusCodeEquals(200);

    // Continue as guest to order information.
    $this->submitForm([], 'Continue as Guest');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Order information');

    // Review.
    $this->submitForm([
      'contact_information[email]' => 'guest@example.com',
      'contact_information[email_confirm]' => 'guest@example.com',
      'registration_information[variation-1-1][registration][registration][anon_mail][0][value]' => 'guest@example.com',
    ], 'Continue to review');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Order summary');

    // The registration is pending.
    $variations = $this->product->getVariations();
    $variation = reset($variations);
    $handler = $this->entityTypeManager->getHandler('commerce_product_variation', 'registration_host_entity');
    $host_entity = $handler->createHostEntity($variation);
    $registrations = $host_entity->getRegistrationList();
    $registration = reset($registrations);
    $this->assertEquals($registration->getState()->id(), 'pending');

    // Cancel the order but disable cart. The registration is not canceled.
    $order = $this->entityTypeManager->getStorage('commerce_order')->load(1);
    $order->set('cart', FALSE);
    $order->set('state', 'canceled');
    $order->save();
    $this->entityTypeManager->getStorage('registration')->resetCache([$registration->id()]);
    $registration = $this->entityTypeManager->getStorage('registration')->load($registration->id());
    $this->assertFalse($registration->isCanceled());
  }

  /**
   * Tests registrations are not canceled when completed orders are canceled.
   */
  public function testRegistrationOnCompletedOrderCancel() {
    // Allow anonymous users to register themselves.
    if ($anonymous_role = Role::load('anonymous')) {
      $anonymous_role->grantPermission('create conference registration self');
      $anonymous_role->save();
    }

    // Enable the payment and registration information panes.
    $this->drupalGet('admin/commerce/config/checkout-flows/manage/default');
    $edit = [
      'configuration[panes][registration_information][step_id]' => 'order_information',
      'configuration[panes][payment_process][step_id]' => 'payment',
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Saved the Default checkout flow.');

    // No registrants yet.
    $this->drupalGet('product/' . $this->product->id() . '/registrations');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('There are no registrants for My event');

    // Load the product detail page as anonymous.
    $this->drupalLogout();
    $this->drupalGet($this->product->toUrl());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('My event');

    // Add to cart.
    $this->submitForm([], 'Add to cart');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('1 item');

    // Checkout.
    $cart_link = $this->getSession()->getPage()->findLink('your cart');
    $cart_link->click();
    $this->submitForm([], 'Checkout');
    $this->assertSession()->statusCodeEquals(200);

    // Continue as guest to order information.
    $this->submitForm([], 'Continue as Guest');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Order information');

    // Review.
    $this->submitForm([
      'contact_information[email]' => 'guest@example.com',
      'contact_information[email_confirm]' => 'guest@example.com',
      'registration_information[variation-1-1][registration][registration][anon_mail][0][value]' => 'guest@example.com',
    ], 'Continue to review');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Order summary');

    // Complete checkout.
    $this->submitForm([], 'Pay and complete purchase');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Your order number is 1. You can view your order on your account page when logged in.');
    $this->assertSession()->pageTextContains('0 items');

    // The registration is pending.
    $variations = $this->product->getVariations();
    $variation = reset($variations);
    $handler = $this->entityTypeManager->getHandler('commerce_product_variation', 'registration_host_entity');
    $host_entity = $handler->createHostEntity($variation);
    $registrations = $host_entity->getRegistrationList();
    $registration = reset($registrations);
    $this->assertEquals($registration->getState()->id(), 'pending');

    // Pay and complete.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/commerce/orders/1/payments/1/operation/receive');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Receive payment');
    $this->submitForm([
      'payment[amount][number]' => '75.00',
    ], 'Receive');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Payment received.');
    $this->entityTypeManager->getStorage('registration')->resetCache([$registration->id()]);
    $registration = $this->entityTypeManager->getStorage('registration')->load($registration->id());
    $this->assertTrue($registration->isComplete());

    // Cancel the completed order. The registration is not canceled.
    $order = $this->entityTypeManager->getStorage('commerce_order')->load(1);
    $order->set('state', 'canceled');
    $order->save();
    $this->entityTypeManager->getStorage('registration')->resetCache([$registration->id()]);
    $registration = $this->entityTypeManager->getStorage('registration')->load($registration->id());
    $this->assertFalse($registration->isCanceled());
  }

}
