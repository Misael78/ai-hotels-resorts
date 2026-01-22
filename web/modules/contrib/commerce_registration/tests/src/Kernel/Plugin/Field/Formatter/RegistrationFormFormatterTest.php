<?php

namespace Drupal\Tests\commerce_registration\Kernel\Plugin\Field\Formatter;

use Drupal\Tests\commerce_registration\Traits\ProductCreationTrait;
use Drupal\Tests\commerce_registration\Traits\ProductVariationCreationTrait;

/**
 * Tests the registration_form formatter.
 *
 * @coversDefaultClass \Drupal\commerce_registration\Plugin\Field\FieldFormatter\RegistrationFormFormatter
 *
 * @group commerce_registration
 */
class RegistrationFormFormatterTest extends FormatterTestBase {

  use ProductCreationTrait;
  use ProductVariationCreationTrait;

  /**
   * @covers ::viewElements
   */
  public function testRegistrationFormFormatter() {
    $product = $this->createAndSaveProduct();
    $variation = $this->createAndSaveVariation($product);
    $build = $variation->get('event_registration')->view([
      'type' => 'registration_form',
      'label' => 'hidden',
    ]);
    $output = $this->renderField($build);
    $this->assertStringContainsString('<form class="registration-conference-register-form', $output);

    // Disable registration.
    $handler = $this->entityTypeManager->getHandler('commerce_product_variation', 'registration_host_entity');
    $host_entity = $handler->createHostEntity($variation);
    $settings = $host_entity->getSettings();
    $settings->set('open', '2220-01-01T00:00:00');
    $settings->save();

    $build = $variation->get('event_registration')->view([
      'type' => 'registration_form',
      'label' => 'hidden',
    ]);
    $output = $this->renderField($build);
    $this->assertEmpty($output);

    $build = $variation->get('event_registration')->view([
      'type' => 'registration_form',
      'label' => 'hidden',
      'settings' => [
        'show_reason' => TRUE,
      ],
    ]);
    $output = $this->renderField($build);
    $this->assertEquals('Registration is not available: Not open yet.', $output);
  }

}
