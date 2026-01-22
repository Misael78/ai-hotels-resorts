<?php

namespace Drupal\Tests\commerce_registration\Traits;

use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\registration\Entity\Registration;
use Drupal\registration\Entity\RegistrationInterface;

/**
 * Defines a trait for creating a test registration and saving it.
 */
trait RegistrationCreationTrait {

  /**
   * Creates a registration for a given product variation host entity.
   *
   * @param \Drupal\commerce_product\Entity\ProductVariationInterface $variation
   *   The product variation.
   *
   * @return \Drupal\registration\Entity\RegistrationInterface
   *   The created (unsaved) registration.
   */
  protected function createRegistration(ProductVariationInterface $variation): RegistrationInterface {
    return Registration::create([
      'workflow' => 'registration',
      'state' => 'pending',
      'type' => 'conference',
      'entity_type_id' => 'commerce_product_variation',
      'entity_id' => $variation->id(),
    ]);
  }

  /**
   * Creates a registration for a given product variation and saves it.
   *
   * @param \Drupal\commerce_product\Entity\ProductVariationInterface $variation
   *   The product variation.
   *
   * @return \Drupal\registration\Entity\RegistrationInterface
   *   The created and saved registration.
   */
  protected function createAndSaveRegistration(ProductVariationInterface $variation): RegistrationInterface {
    $registration = $this->createRegistration($variation);
    $registration->save();
    return $registration;
  }

}
