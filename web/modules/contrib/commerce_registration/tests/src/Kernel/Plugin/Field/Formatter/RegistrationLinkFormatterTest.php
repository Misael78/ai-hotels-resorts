<?php

namespace Drupal\Tests\commerce_registration\Kernel\Plugin\Field\Formatter;

use Drupal\Tests\commerce_registration\Traits\ProductCreationTrait;
use Drupal\Tests\commerce_registration\Traits\ProductVariationCreationTrait;

/**
 * Tests the registration_link formatter.
 *
 * @coversDefaultClass \Drupal\commerce_registration\Plugin\Field\FieldFormatter\RegistrationLinkFormatter
 *
 * @group commerce_registration
 */
class RegistrationLinkFormatterTest extends FormatterTestBase {

  use ProductCreationTrait;
  use ProductVariationCreationTrait;

  /**
   * @covers ::viewElements
   */
  public function testRegistrationLinkFormatter() {
    $product = $this->createAndSaveProduct();
    $variation = $this->createAndSaveVariation($product);

    // Default settings.
    $build = $variation->get('event_registration')->view([
      'type' => 'registration_link',
      'label' => 'hidden',
    ]);
    $output = $this->renderField($build);
    $this->assertEquals('<a href="/product/1/variations/1/register">Conference</a>', $output);

    // Custom link label.
    $build = $variation->get('event_registration')->view([
      'type' => 'registration_link',
      'label' => 'hidden',
      'settings' => [
        'label' => 'Register now',
      ],
    ]);
    $output = $this->renderField($build);
    $this->assertEquals('<a href="/product/1/variations/1/register">Register now</a>', $output);

    // Disable registration.
    $handler = $this->entityTypeManager->getHandler('commerce_product_variation', 'registration_host_entity');
    $host_entity = $handler->createHostEntity($variation);
    $settings = $host_entity->getSettings();
    $settings->set('open', '2220-01-01T00:00:00');
    $settings->save();

    $build = $variation->get('event_registration')->view([
      'type' => 'registration_link',
      'label' => 'hidden',
      'settings' => [
        'label' => 'Register now',
      ],
    ]);
    $output = $this->renderField($build);
    $this->assertEmpty($output);

    $build = $variation->get('event_registration')->view([
      'type' => 'registration_link',
      'label' => 'hidden',
      'settings' => [
        'label' => 'Register now',
        'show_reason' => TRUE,
      ],
    ]);
    $output = $this->renderField($build);
    $this->assertEquals('Registration is not available: Not open yet.', $output);
  }

}
