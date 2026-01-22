<?php

namespace Drupal\Tests\registration\Kernel\Plugin\Field\Formatter;

use Drupal\Tests\registration\Traits\NodeCreationTrait;

/**
 * Tests the registration_link formatter.
 *
 * @coversDefaultClass \Drupal\registration\Plugin\Field\FieldFormatter\RegistrationLinkFormatter
 *
 * @group registration
 */
class RegistrationLinkFormatterTest extends FormatterTestBase {

  use NodeCreationTrait;

  /**
   * @covers ::render
   */
  public function testRegistrationLinkFormatter() {
    // Cannot render a link for a new host entity.
    $node = $this->createNode();
    $build = $node->get('event_registration')->view([
      'type' => 'registration_link',
      'label' => 'hidden',
    ]);
    $output = $this->renderElement($build);
    $this->assertEquals('', $output);

    // Default settings for an existing node.
    $node->save();
    $build = $node->get('event_registration')->view([
      'type' => 'registration_link',
      'label' => 'hidden',
    ]);
    $output = $this->renderElement($build);
    $this->assertEquals('<a href="/node/1/register">Conference</a>', $output);

    // Custom link label.
    $build = $node->get('event_registration')->view([
      'type' => 'registration_link',
      'label' => 'hidden',
      'settings' => [
        'label' => 'Register now',
      ],
    ]);
    $output = $this->renderElement($build);
    $this->assertEquals('<a href="/node/1/register">Register now</a>', $output);

    // Custom CSS classes.
    $build = $node->get('event_registration')->view([
      'type' => 'registration_link',
      'label' => 'hidden',
      'settings' => [
        'css_classes' => 'example-class-1 example-class-2',
      ],
    ]);
    $output = $this->renderElement($build);
    $this->assertEquals('<a href="/node/1/register" class="example-class-1 example-class-2">Conference</a>', $output);

    // Disable registration.
    $handler = $this->entityTypeManager->getHandler('node', 'registration_host_entity');
    $host_entity = $handler->createHostEntity($node);
    $settings = $host_entity->getSettings();
    $settings->set('open', '2220-01-01T00:00:00');
    $settings->save();

    $build = $node->get('event_registration')->view([
      'type' => 'registration_link',
      'label' => 'hidden',
      'settings' => [
        'label' => 'Register now',
      ],
    ]);
    $output = $this->renderElement($build);
    $this->assertEmpty($output);

    $build = $node->get('event_registration')->view([
      'type' => 'registration_link',
      'label' => 'hidden',
      'settings' => [
        'label' => 'Register now',
        'show_reason' => TRUE,
      ],
    ]);
    $output = $this->renderElement($build);
    $this->assertEquals('Registration is not available: Not open yet.', $output);
  }

}
