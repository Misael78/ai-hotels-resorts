<?php

namespace Drupal\Tests\registration\Kernel\Plugin\Field\Formatter;

use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\Tests\registration\Traits\RegistrationCreationTrait;

/**
 * Tests the registration_id formatter.
 *
 * @coversDefaultClass \Drupal\registration\Plugin\Field\FieldFormatter\RegistrationIdFormatter
 *
 * @group registration
 */
class RegistrationIdFormatterTest extends FormatterTestBase {

  use NodeCreationTrait;
  use RegistrationCreationTrait;

  /**
   * @covers ::render
   */
  public function testRegistrationIdFormatter() {
    // The user must be able to view the registration in order to view its ID.
    $account = $this->createUser(['view any registration']);
    $this->setCurrentUser($account);

    $node = $this->createAndSaveNode();
    $registration = $this->createAndSaveRegistration($node);
    $build = $registration->get('registration_id')->view([
      'type' => 'registration_id',
      'label' => 'hidden',
    ]);
    $output = $this->renderElement($build);
    $this->assertEquals('<a href="/registration/1" hreflang="en">1</a>', $output);
  }

}
