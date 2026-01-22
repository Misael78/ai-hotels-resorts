<?php

namespace Drupal\Tests\commerce_registration_waitlist\Functional;

use Drupal\Tests\commerce_registration\Traits\ProductCreationTrait;
use Drupal\Tests\commerce_registration\Traits\ProductVariationCreationTrait;
use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\user\Entity\Role;

/**
 * Tests cart functionality for registration enabled product variations.
 *
 * @group commerce_registration
 */
class AddToCartFormTest extends CommerceRegistrationWaitListBrowserTestBase {

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
   * Tests the add to cart form with wait list support.
   */
  public function testAddToCartFormWaitList() {
    // Allow anonymous users to register themselves.
    if ($anonymous_role = Role::load('anonymous')) {
      $anonymous_role->grantPermission('create conference registration self');
      $anonymous_role->save();
    }

    // Enable the payment and registration process panes. Note that the
    // registration process pane must come before the payment process pane.
    $this->drupalGet('admin/commerce/config/checkout-flows/manage/default');
    $edit = [
      'configuration[panes][registration_information][step_id]' => 'order_information',
      'configuration[panes][payment_process][step_id]' => 'payment',
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Saved the Default checkout flow.');

    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository */
    $display_repository = \Drupal::service('entity_display.repository');
    $entity_view_display = $display_repository->getViewDisplay('commerce_product_variation', 'event', 'default');
    // Use the calculated price with wait list support.
    $entity_view_display->setComponent('price', [
      'label' => 'hidden',
      'type' => 'commerce_price_calculated_waitlist',
    ])->save();

    // Add to cart is available.
    $this->drupalLogout();
    $this->drupalGet($this->product->toUrl());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('My event');
    $this->assertSession()->pageTextContains('75.00');
    $this->assertSession()->buttonExists('Add to cart');

    // Add to cart.
    $this->submitForm([], 'Add to cart');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('1 item');

    // There was room, so no warning is output.
    $this->assertSession()->statusMessageNotContains('An item in your cart has been placed on the waiting list.', 'warning');

    // The carted item is not free since it was not wait listed.
    $cart_link = $this->getSession()->getPage()->findLink('your cart');
    $cart_link->click();
    $this->assertSession()->pageTextContains('75.00');

    // Checkout.
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

    // Add to cart is no longer available since capacity was reached.
    $this->drupalGet($this->product->toUrl());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('My event');
    $this->assertSession()->buttonNotExists('Add to cart');

    // Set up the wait list and retry.
    $handler = $this->entityTypeManager->getHandler('commerce_product_variation', 'registration_host_entity');
    $host_entity = $handler->createHostEntity($this->variation);

    /** @var \Drupal\registration\RegistrationSettingsStorage $storage */
    $storage = $this->entityTypeManager->getStorage('registration_settings');
    $settings = $storage->loadSettingsForHostEntity($host_entity);
    $settings->set('registration_waitlist_enable', TRUE);
    $settings->save();
    $this->drupalGet($this->product->toUrl());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('My event');
    $this->assertSession()->buttonExists('Add to cart');
    // Wait listed items are free until space becomes available.
    $this->assertSession()->pageTextContains('0.00');

    // Items placed on a waiting list generate a warning.
    $this->submitForm([], 'Add to cart');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->statusMessageContains('An item in your cart has been placed on the waiting list.', 'warning');

    // The carted item should be free.
    $cart_link = $this->getSession()->getPage()->findLink('your cart');
    $cart_link->click();
    $this->assertSession()->pageTextContains('0.00');
  }

}
