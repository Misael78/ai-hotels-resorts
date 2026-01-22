<?php

namespace Drupal\Tests\commerce_registration\Functional;

use Drupal\Tests\commerce_registration\Traits\ProductCreationTrait;
use Drupal\Tests\commerce_registration\Traits\ProductVariationCreationTrait;
use Drupal\Tests\commerce_registration\Traits\RegistrationCreationTrait;

/**
 * Tests the email host entity registrants page.
 *
 * @group commerce_registration
 */
class EmailRegistrantsTest extends CommerceRegistrationBrowserTestBase {

  use ProductCreationTrait;
  use ProductVariationCreationTrait;
  use RegistrationCreationTrait;

  /**
   * Tests send.
   */
  public function testSend() {
    $product = $this->createAndSaveProduct();
    $variation = $this->createAndSaveVariation($product);
    $registration = $this->createAndSaveRegistration($variation);

    $this->drupalGet('product/' . $product->id() . '/registrations/broadcast');
    $edit = [
      'subject' => 'This is a test subject',
      'message[value]' => 'This is a test message.',
    ];
    $this->submitForm($edit, 'Send');
    $this->assertSession()->pageTextContains('Registration broadcast sent to 1 recipient.');
  }

  /**
   * Tests preview.
   */
  public function testPreview() {
    $product = $this->createAndSaveProduct();
    $variation = $this->createAndSaveVariation($product);
    $registration = $this->createAndSaveRegistration($variation);

    $this->drupalGet('product/' . $product->id() . '/registrations/broadcast');
    $edit = [
      'subject' => 'This is a test subject',
      'message[value]' => 'This is a test message.',
    ];
    $this->submitForm($edit, 'Preview');
    $this->assertSession()->pageTextContains('This is a test subject');
    $this->assertSession()->pageTextContains('This is a test message.');
    $this->assertSession()->hiddenFieldExists('subject');
    $this->assertSession()->hiddenFieldValueEquals('subject', 'This is a test subject');
    $this->assertSession()->hiddenFieldExists('message');
    $this->getSession()->getPage()->pressButton('Edit message');

    $this->assertSession()->addressEquals('product/' . $product->id() . '/registrations/broadcast');
    $this->getSession()->getPage()->pressButton('Send');
    $this->assertSession()->pageTextContains('Registration broadcast sent to 1 recipient.');
  }

}
