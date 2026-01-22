<?php

namespace Drupal\Tests\registration\Kernel\Plugin\Validation\Constraint;

use Drupal\Tests\registration\Kernel\RegistrationKernelTestBase;
use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\Tests\registration\Traits\RegistrationCreationTrait;

/**
 * Tests the Redirect constraint.
 *
 * @coversDefaultClass \Drupal\registration\Plugin\Validation\Constraint\RedirectConstraint
 *
 * @group registration
 */
class RedirectConstraintTest extends RegistrationKernelTestBase {

  use NodeCreationTrait;
  use RegistrationCreationTrait;

  /**
   * @covers ::validate
   */
  public function testRedirectConstraint() {
    $node = $this->createAndSaveNode();
    $registration = $this->createAndSaveRegistration($node);
    $host_entity = $registration->getHostEntity();
    $settings = $host_entity->getSettings();

    // Allow a null redirect.
    $settings->set('confirmation_redirect', NULL);
    $violations = $settings->validate();
    $this->assertEquals(0, $violations->count());

    // Allow a valid external URL.
    $settings->set('confirmation_redirect', 'https://example.org');
    $violations = $settings->validate();
    $this->assertEquals(0, $violations->count());

    // Allow a valid internal URL (starts with a forward slash).
    $settings->set('confirmation_redirect', '/example');
    $violations = $settings->validate();
    $this->assertEquals(0, $violations->count());

    // Prevent an invalid external URL.
    $settings->set('confirmation_redirect', 'https');
    $violations = $settings->validate();
    $this->assertEquals(1, $violations->count());

    // Prevent an invalid internal URL.
    $settings->set('confirmation_redirect', 'example');
    $violations = $settings->validate();
    $this->assertEquals(1, $violations->count());
  }

}
