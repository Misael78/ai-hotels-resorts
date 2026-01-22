<?php

namespace Drupal\Tests\commerce_registration\Functional;

use Drupal\Tests\commerce_registration\Traits\ProductCreationTrait;
use Drupal\Tests\commerce_registration\Traits\ProductVariationCreationTrait;
use Drupal\Tests\commerce_registration\Traits\RegistrationCreationTrait;

/**
 * Tests the manage commerce registrations page.
 *
 * @group commerce_registration
 */
class ManageCommerceRegistrationsTest extends CommerceRegistrationBrowserTestBase {

  use ProductCreationTrait;
  use ProductVariationCreationTrait;
  use RegistrationCreationTrait;

  /**
   * Tests manage commerce registrations.
   */
  public function testManageCommerceRegistrations() {
    $product = $this->createAndSaveProduct();
    $variation = $this->createAndSaveVariation($product);

    // No registrants.
    $this->drupalGet('product/' . $product->id() . '/registrations');
    $this->assertSession()->pageTextContains('There are no registrants for My event');

    $registration1 = $this->createRegistration($variation);
    $registration1->set('count', 2);
    $registration1->save();

    $this->drupalGet('product/' . $product->id() . '/registrations');
    $this->assertSession()->pageTextContains('List of registrations for My event. 2 spaces are filled.');

    $registration2 = $this->createAndSaveRegistration($variation);
    $this->drupalGet('product/' . $product->id() . '/registrations');
    $this->assertSession()->pageTextContains('List of registrations for My event. 3 spaces are filled.');

    // Canceled registrations do not count towards the total.
    $registration3 = $this->createRegistration($variation);
    $registration3->set('state', 'canceled');
    $registration3->save();
    $this->drupalGet('product/' . $product->id() . '/registrations');
    $this->assertSession()->pageTextContains('List of registrations for My event. 3 spaces are filled.');

    // Held registrations count towards the total.
    $registration4 = $this->createRegistration($variation);
    $registration4->set('state', 'held');
    $registration4->save();
    $this->drupalGet('product/' . $product->id() . '/registrations');
    $this->assertSession()->pageTextContains('List of registrations for My event. 4 spaces are filled.');

    // Include capacity when configured.
    $host_entity = $registration1->getHostEntity();
    $settings = $host_entity->getSettings();
    $settings->set('capacity', 10);
    $settings->save();
    $this->drupalGet('product/' . $product->id() . '/registrations');
    $this->assertSession()->pageTextContains('List of registrations for My event. 4 of 10 spaces are filled.');

    // Reflect deletions.
    $registration1->delete();
    $registration2->delete();
    $settings->set('capacity', 0);
    $settings->save();
    $this->drupalGet('product/' . $product->id() . '/registrations');
    $this->assertSession()->pageTextContains('List of registrations for My event. 1 space is filled.');

    // Host entity is not configured for registration.
    $variation->set('event_registration', NULL);
    $variation->save();
    $this->drupalGet('product/' . $product->id() . '/registrations');
    $this->assertSession()->statusCodeEquals(403);
  }

}
