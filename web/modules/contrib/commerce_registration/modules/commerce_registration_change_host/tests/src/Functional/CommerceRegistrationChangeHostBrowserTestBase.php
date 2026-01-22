<?php

namespace Drupal\Tests\commerce_registration_change_host\Functional;

use Drupal\Tests\commerce_registration\Functional\CommerceRegistrationBrowserTestBase;
use Drupal\Tests\commerce_registration\Traits\ProductCreationTrait;
use Drupal\Tests\commerce_registration\Traits\ProductVariationCreationTrait;
use Drupal\Tests\commerce_registration\Traits\RegistrationCreationTrait;
use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\registration\Entity\RegistrationInterface;

/**
 * Defines the base class for commerce registration change host test cases.
 */
abstract class CommerceRegistrationChangeHostBrowserTestBase extends CommerceRegistrationBrowserTestBase {

  use ProductCreationTrait;
  use ProductVariationCreationTrait;
  use RegistrationCreationTrait;

  /**
   * The product.
   */
  protected ProductInterface $product;

  /**
   * Product variation 1.
   */
  protected ProductVariationInterface $variation1;

  /**
   * Product variation 2.
   */
  protected ProductVariationInterface $variation2;

  /**
   * The registration.
   */
  protected RegistrationInterface $registration;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'registration_change_host',
    'commerce_registration_change_host',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Set up a product with two registration enabled variations.
    $this->product = $this->createAndSaveProduct();
    $this->variation1 = $this->createAndSaveVariation($this->product);
    $this->variation1->setTitle('Variation1');
    $this->variation1->save();
    $this->variation2 = $this->createAndSaveVariation($this->product);
    $this->variation2->setTitle('Variation2');
    $this->variation2->save();
    $handler = $this->entityTypeManager->getHandler('commerce_product_variation', 'registration_host_entity');
    $host_entity = $handler->createHostEntity($this->variation1);
    $settings = $host_entity->getSettings();
    $settings->set('status', TRUE);
    $settings->set('capacity', 1);
    $settings->save();
    $host_entity = $handler->createHostEntity($this->variation2);
    $settings = $host_entity->getSettings();
    $settings->set('status', TRUE);
    $settings->set('capacity', 1);
    $settings->save();

    // Create a registration for the first variation.
    $this->registration = $this->createAndSaveRegistration($this->variation1);
    $this->registration->set('user_uid', $this->adminUser->id());
    $this->registration->save();
  }

  /**
   * Gets the permissions for the admin user.
   *
   * @return string[]
   *   The permissions.
   */
  protected function getAdministratorPermissions(): array {
    return [
      'access administration pages',
      'access user profiles',
      'administer registration',
      'change host any registration',
      'view the administration theme',
    ];
  }

}
