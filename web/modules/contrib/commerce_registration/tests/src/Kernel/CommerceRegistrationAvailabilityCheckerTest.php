<?php

namespace Drupal\Tests\commerce_registration\Kernel;

use Drupal\Tests\commerce_registration\Traits\OrderItemCreationTrait;
use Drupal\Tests\commerce_registration\Traits\ProductCreationTrait;
use Drupal\Tests\commerce_registration\Traits\ProductVariationCreationTrait;
use Drupal\Tests\commerce_registration\Traits\RegistrationCreationTrait;
use Drupal\commerce\Context;
use Drupal\commerce_registration\CommerceRegistrationAvailabilityChecker;

/**
 * Tests the commerce registration availability checker.
 *
 * @coversDefaultClass \Drupal\commerce_registration\CommerceRegistrationAvailabilityChecker
 *
 * @group commerce_registration
 */
class CommerceRegistrationAvailabilityCheckerTest extends CommerceRegistrationKernelTestBase {

  use OrderItemCreationTrait;
  use ProductCreationTrait;
  use ProductVariationCreationTrait;
  use RegistrationCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $admin_user = $this->createUser();
    $this->setCurrentUser($admin_user);
  }

  /**
   * @covers ::applies
   * @covers ::check
   */
  public function testCommerceRegistrationAvailabilityChecker() {
    $account = $this->container->get('current_user')->getAccount();
    $context = new Context($account, $this->store);
    $checker = new CommerceRegistrationAvailabilityChecker($this->entityTypeManager);

    $product = $this->createAndSaveProduct();
    $variation = $this->createAndSaveVariation($product);
    $item = $this->createAndSaveOrderItem($variation);

    // The item is available.
    $this->assertTrue($checker->applies($item));
    $this->assertTrue($checker->check($item, $context)->isNeutral());

    // The checker does not apply to items with a registration assigned.
    $registration = $this->createAndSaveRegistration($variation);
    $item->set('registration', $registration->id());
    $item->save();
    $this->assertFalse($checker->applies($item));

    // Reach capacity. The item is not available.
    $host_entity = $registration->getHostEntity();
    $settings = $host_entity->getSettings();
    $this->assertEquals(2, $settings->getSetting('capacity'));
    $registration = $this->createAndSaveRegistration($variation);
    $item = $this->createAndSaveOrderItem($variation);
    $this->assertTrue($checker->applies($item));
    $this->assertFalse($checker->check($item, $context)->isNeutral());

    // The item is available since there is one spot left.
    $variation = $this->createAndSaveVariation($product);
    $item = $this->createAndSaveOrderItem($variation);
    $registration = $this->createAndSaveRegistration($variation);
    $this->assertTrue($checker->applies($item));
    $this->assertTrue($checker->check($item, $context)->isNeutral());

    // Reduce capacity. The same item is no longer available.
    $host_entity = $registration->getHostEntity();
    $settings = $host_entity->getSettings();
    $settings->set('capacity', 1);
    $settings->save();
    $this->assertTrue($checker->applies($item));
    $this->assertFalse($checker->check($item, $context)->isNeutral());
  }

}
