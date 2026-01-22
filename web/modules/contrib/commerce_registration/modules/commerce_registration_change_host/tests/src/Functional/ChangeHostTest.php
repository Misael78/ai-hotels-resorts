<?php

namespace Drupal\Tests\commerce_registration_change_host\Functional;

/**
 * Tests change host functionality for registration enabled product variations.
 *
 * @group commerce_registration
 */
class ChangeHostTest extends CommerceRegistrationChangeHostBrowserTestBase {

  /**
   * Tests the change host page when changing the host is possible.
   */
  public function testChangeHostPossible() {
    $url = $this->registration->toUrl()->toString();
    $this->drupalGet($url . '/host');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Variation1');
    $this->assertSession()->pageTextContains('Currently registered');
    $link = $this->getSession()->getPage()->findLink('Variation2');
    $link->click();
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('The registration will be changed from Variation1 to Variation2.');
    $edit = [
      'who_is_registering' => 'registration_registrant_type_me',
    ];
    $this->submitForm($edit, 'Save and confirm');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Registration has been saved.');
    $this->assertSession()->linkExists('Variation2');
  }

  /**
   * Tests the change host page when changing the host is not possible.
   */
  public function testChangeHostNotPossible() {
    $handler = $this->entityTypeManager->getHandler('commerce_product_variation', 'registration_host_entity');
    $host_entity = $handler->createHostEntity($this->variation2);
    $settings = $host_entity->getSettings();
    $settings->set('status', FALSE);
    $settings->save();

    $url = $this->registration->toUrl()->toString();
    $this->drupalGet($url . '/host');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('There is nothing available to change to.');
  }

}
