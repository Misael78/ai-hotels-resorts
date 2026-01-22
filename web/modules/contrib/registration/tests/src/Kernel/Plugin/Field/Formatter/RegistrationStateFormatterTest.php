<?php

namespace Drupal\Tests\registration\Kernel\Plugin\Field\Formatter;

use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\Tests\registration\Traits\RegistrationCreationTrait;

/**
 * Tests the registration_state formatter.
 *
 * @coversDefaultClass \Drupal\registration\Plugin\Field\FieldFormatter\RegistrationStateFormatter
 *
 * @group registration
 */
class RegistrationStateFormatterTest extends FormatterTestBase {

  use NodeCreationTrait;
  use RegistrationCreationTrait;

  /**
   * @covers ::render
   */
  public function testRegistrationStateFormatter() {
    $node = $this->createAndSaveNode();
    $registration = $this->createAndSaveRegistration($node);
    $build = $registration->get('state')->view([
      'type' => 'registration_state',
      'label' => 'hidden',
    ]);
    $output = $this->renderElement($build);
    $this->assertEquals('Pending', $output);
  }

}
