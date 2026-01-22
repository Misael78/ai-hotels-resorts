<?php

namespace Drupal\Tests\commerce_registration\Functional;

use Drupal\Tests\commerce_registration\Traits\ProductCreationTrait;
use Drupal\Tests\commerce_registration\Traits\ProductVariationCreationTrait;
use Drupal\Tests\commerce_registration\Traits\RegistrationCreationTrait;

/**
 * Tests the product registration settings page.
 *
 * @group commerce_registration
 */
class ProductRegistrationSettingsTest extends CommerceRegistrationBrowserTestBase {

  use ProductCreationTrait;
  use ProductVariationCreationTrait;
  use RegistrationCreationTrait;

  /**
   * Tests the single variation case.
   */
  public function testProductRegistrationSettingsSingleVariation() {
    $product = $this->createAndSaveProduct();
    $variation = $this->createAndSaveVariation($product);

    // Load the settings page. This is a form as there is only one variation.
    $this->drupalGet('product/' . $product->id() . '/registrations/settings');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Registration Settings for My event');
    $this->assertSession()->pageTextContains('Check to enable registrations.');
    $this->assertSession()->pageTextContains('The maximum number of registrants. Leave at 0 for no limit.');

    // Change the capacity to 50 and set a from address.
    $edit = [
      'capacity[0][value]' => 50,
      'from_address[0][value]' => 'example.com',
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('The settings have been saved.');
    $handler = $this->entityTypeManager->getHandler('commerce_product_variation', 'registration_host_entity');
    $host_entity = $handler->createHostEntity($variation);
    $this->assertEquals(50, $host_entity->getSetting('capacity'));
    $this->assertEquals('example.com', $host_entity->getSetting('from_address'));

    // Host entity is not configured for registration.
    $variation->set('event_registration', NULL);
    $variation->save();
    $this->drupalGet('product/' . $product->id() . '/registrations/settings');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests the multiple variation case.
   */
  public function testProductRegistrationSettingsMultipleVariation() {
    $product = $this->createAndSaveProduct();
    $handler = $this->entityTypeManager->getHandler('commerce_product_variation', 'registration_host_entity');

    $settings_ids = [];
    for ($i = 1; $i <= 3; $i++) {
      $variation = $this->createAndSaveVariation($product);
      $variation->setTitle('My variation ' . $i);
      $variation->save();

      // Initialize capacity to 10, 20 etc.
      $host_entity = $handler->createHostEntity($variation);
      $settings = $host_entity->getSettings();
      $settings->set('capacity', $i * 10);
      $settings->save();
      $settings_ids[] = $settings->id();
    }

    // Load the settings page. This is a listing of variations with settings
    // edit buttons.
    $this->drupalGet('product/' . $product->id() . '/registrations/settings');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains('Registration Settings for My event');
    $this->assertSession()->pageTextContains('Edit settings');

    // Edit the settings for each variation in the list.
    for ($i = 1; $i <= 3; $i++) {
      $z = $i - 1;
      $this->clickLink('Edit settings', $z);
      $this->assertSession()->statusCodeEquals(200);
      $this->assertSession()->pageTextContains('Registration Settings for My variation ' . $i);
      $this->assertEquals($i * 10, $this->getSession()->getPage()->findField('capacity[0][value]')->getValue());

      // Change the capacity to 50 and set a from address.
      $edit = [
        'capacity[0][value]' => 50,
        'from_address[0][value]' => 'example.com',
      ];
      $this->submitForm($edit, 'Save');
      $this->assertSession()->statusCodeEquals(200);
      $this->assertSession()->pageTextContains('The settings have been saved.');
      $settings = $this->entityTypeManager->getStorage('registration_settings')->load($settings_ids[$z]);
      $this->assertEquals(50, $settings->getSetting('capacity'));
      $this->assertEquals('example.com', $settings->getSetting('from_address'));

      // Confirm return to the listing.
      $this->assertSession()->addressEquals('product/' . $product->id() . '/registrations/settings');
    }
  }

}
