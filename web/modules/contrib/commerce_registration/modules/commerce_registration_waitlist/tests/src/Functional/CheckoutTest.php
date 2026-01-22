<?php

namespace Drupal\Tests\commerce_registration_waitlist\Functional;

use Drupal\Tests\commerce_registration\Traits\ProductCreationTrait;
use Drupal\Tests\commerce_registration\Traits\ProductVariationCreationTrait;
use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\user\Entity\Role;

/**
 * Tests checkout of registration enabled product variations on a waiting list.
 *
 * @group commerce_registration
 */
class CheckoutTest extends CommerceRegistrationWaitListBrowserTestBase {

  use ProductCreationTrait;
  use ProductVariationCreationTrait;

  /**
   * The product.
   *
   * @var \Drupal\commerce_product\Entity\ProductInterface
   */
  protected ProductInterface $product;

  /**
   * The product variation.
   *
   * @var \Drupal\commerce_product\Entity\ProductVariationInterface
   */
  protected ProductVariationInterface $variation;

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
    $this->variation = $this->createAndSaveVariation($this->product);
    $handler = $this->entityTypeManager->getHandler('commerce_product_variation', 'registration_host_entity');
    $host_entity = $handler->createHostEntity($this->variation);
    $settings = $host_entity->getSettings();
    $settings->set('status', TRUE);
    $settings->set('capacity', 1);
    $settings->save();
  }

  /**
   * Tests the registration process checkout pane with wait listed items.
   */
  public function testRegistrationProcessCheckoutPaneWaitList() {
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
    $this->assertSession()->pageTextContains('List of registrations for My event. 1 of 1 space is filled.');

    // Set up the wait list with autofill enabled and placing into held state.
    $handler = $this->entityTypeManager->getHandler('commerce_product_variation', 'registration_host_entity');
    $host_entity = $handler->createHostEntity($this->variation);

    /** @var \Drupal\registration\RegistrationSettingsStorage $storage */
    $storage = $this->entityTypeManager->getStorage('registration_settings');
    $settings = $storage->loadSettingsForHostEntity($host_entity);
    $settings->set('registration_waitlist_enable', TRUE);
    $settings->set('registration_waitlist_autofill', TRUE);
    $settings->set('registration_waitlist_autofill_state', 'held');
    $settings->save();

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

    // Billing information is required since this is a free order and the manual
    // payment gateway with billing information disabled is not present.
    $this->submitForm([
      'payment_information[billing_information][address][0][address][given_name]' => 'John',
      'payment_information[billing_information][address][0][address][family_name]' => 'Smith',
      'payment_information[billing_information][address][0][address][address_line1]' => '9 Drupal Ave',
      'payment_information[billing_information][address][0][address][postal_code]' => '94043',
      'payment_information[billing_information][address][0][address][locality]' => 'Mountain View',
      'payment_information[billing_information][address][0][address][administrative_area]' => 'CA',
    ], 'Continue to review');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Order summary');

    // Complete checkout.
    $this->submitForm([], 'Pay and complete purchase');
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([], 'Complete checkout');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Your order number is 2. You can view your order on your account page when logged in.');
    $this->assertSession()->pageTextContains('0 items');

    // A registration was created during checkout, but it is not counted towards
    // the total since it is on the waiting list.
    $this->drupalGet('product/' . $this->product->id() . '/registrations');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('List of registrations for My event. 1 of 1 space is filled.');
    $this->assertSession()->pageTextContains('Wait list');

    // Increase capacity. The wait listed registration is autofilled. It counts
    // towards the total since it is now in held state.
    $settings->set('capacity', 2);
    $settings->save();
    $this->drupalGet('product/' . $this->product->id() . '/registrations');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('List of registrations for My event. 2 of 2 spaces are filled.');
    $this->assertSession()->pageTextContains('Held');
    $this->assertSession()->pageTextNotContains('Wait list');

    // Checkout the programmatically generated order.
    $this->drupalGet('checkout/3/order_information');
    $this->assertSession()->pageTextContains('1 item');
    $this->submitForm([], 'Continue to review');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Order summary');
    $this->submitForm([], 'Pay and complete purchase');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Your order number is 3. You can view your order on your account page when logged in.');
    $this->assertSession()->pageTextContains('0 items');
    $this->drupalGet('product/' . $this->product->id() . '/registrations');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('List of registrations for My event. 2 of 2 spaces are filled.');
    $this->assertSession()->pageTextContains('Pending');
    $this->assertSession()->pageTextNotContains('Held');
    $this->assertSession()->pageTextNotContains('Wait list');

    // The registration is complete once payment is received.
    $handler = $this->entityTypeManager->getHandler('commerce_product_variation', 'registration_host_entity');
    $host_entity = $handler->createHostEntity($this->variation);
    $registrations = $host_entity->getRegistrationList();
    $registration = end($registrations);
    $this->assertFalse($registration->isComplete());
    $this->drupalGet('admin/commerce/orders/3/payments/2/operation/receive');
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
   * Tests the registration information checkout pane with wait listed items.
   */
  public function testRegistrationInformationCheckoutPaneWaitList() {
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
    $this->assertSession()->pageTextContains('List of registrations for My event. 1 of 1 space is filled.');

    // Set up the wait list with autofill enabled and placing into held state.
    $handler = $this->entityTypeManager->getHandler('commerce_product_variation', 'registration_host_entity');
    $host_entity = $handler->createHostEntity($this->variation);

    /** @var \Drupal\registration\RegistrationSettingsStorage $storage */
    $storage = $this->entityTypeManager->getStorage('registration_settings');
    $settings = $storage->loadSettingsForHostEntity($host_entity);
    $settings->set('multiple_registrations', TRUE);
    $settings->set('registration_waitlist_enable', TRUE);
    $settings->set('registration_waitlist_autofill', TRUE);
    $settings->set('registration_waitlist_autofill_state', 'held');
    $settings->save();

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

    // Billing information is required since this is a free order and the manual
    // payment gateway with billing information disabled is not present.
    $this->submitForm([
      'contact_information[email]' => 'guest@example.com',
      'contact_information[email_confirm]' => 'guest@example.com',
      'registration_information[variation-1-1][registration][registration][anon_mail][0][value]' => 'guest@example.com',
      'payment_information[billing_information][address][0][address][given_name]' => 'John',
      'payment_information[billing_information][address][0][address][family_name]' => 'Smith',
      'payment_information[billing_information][address][0][address][address_line1]' => '9 Drupal Ave',
      'payment_information[billing_information][address][0][address][postal_code]' => '94043',
      'payment_information[billing_information][address][0][address][locality]' => 'Mountain View',
      'payment_information[billing_information][address][0][address][administrative_area]' => 'CA',
    ], 'Continue to review');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Order summary');

    // Complete checkout.
    $this->submitForm([], 'Pay and complete purchase');
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([], 'Complete checkout');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Your order number is 2. You can view your order on your account page when logged in.');
    $this->assertSession()->pageTextContains('0 items');

    // A registration was created during checkout, but it is not counted towards
    // the total since it is on the waiting list.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('product/' . $this->product->id() . '/registrations');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('List of registrations for My event. 1 of 1 space is filled.');
    $this->assertSession()->pageTextContains('Wait list');

    // Increase capacity. The wait listed registration is autofilled. It counts
    // towards the total since it is now in held state.
    $settings->set('capacity', 2);
    $settings->save();
    $this->drupalGet('product/' . $this->product->id() . '/registrations');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('List of registrations for My event. 2 of 2 spaces are filled.');
    $this->assertSession()->pageTextContains('Held');
    $this->assertSession()->pageTextNotContains('Wait list');
  }

}
