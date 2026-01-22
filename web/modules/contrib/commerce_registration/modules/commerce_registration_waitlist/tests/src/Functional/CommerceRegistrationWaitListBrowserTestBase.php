<?php

namespace Drupal\Tests\commerce_registration_waitlist\Functional;

use Drupal\Tests\commerce_registration\Functional\CommerceRegistrationBrowserTestBase;
use Drupal\registration\Entity\RegistrationType;

/**
 * Defines the base class for commerce registration wait list test cases.
 */
abstract class CommerceRegistrationWaitListBrowserTestBase extends CommerceRegistrationBrowserTestBase {

  /**
   * Modules to enable.
   *
   * Note that when a child class declares its own $modules list, that list
   * doesn't override this one, it just extends it.
   *
   * @var array
   */
  protected static $modules = [
    'registration_waitlist',
    'commerce_registration_waitlist',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $registration_type = RegistrationType::load('conference');
    $registration_type->setThirdPartySetting('commerce_registration_waitlist', 'confirmation_email', TRUE);
    $registration_type->setThirdPartySetting('commerce_registration_waitlist', 'confirmation_email_subject', 'Test message');
    $registration_type->setThirdPartySetting('commerce_registration_waitlist', 'confirmation_email_message', [
      'value' => 'This is a test message about a registration moved off the wait list',
      'format' => 'plain_text',
    ]);
    $registration_type->save();
  }

}
