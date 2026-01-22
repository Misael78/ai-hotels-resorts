<?php

namespace Drupal\Tests\registration\Kernel;

use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\Tests\registration\Traits\RegistrationCreationTrait;
use Drupal\registration\Notify\RegistrationMailerInterface;

/**
 * Tests the RegistrationManager class.
 *
 * @coversDefaultClass \Drupal\registration\Notify\RegistrationMailer
 *
 * @group registration
 */
class RegistrationMailerTest extends RegistrationKernelTestBase {

  use NodeCreationTrait;
  use RegistrationCreationTrait;

  /**
   * The registration mailer.
   *
   * @var \Drupal\registration\Notify\RegistrationMailerInterface
   */
  protected RegistrationMailerInterface $registrationMailer;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $admin_user = $this->createUser();
    $this->setCurrentUser($admin_user);

    $this->registrationMailer = $this->container->get('registration.notifier');
  }

  /**
   * @covers ::getRecipientList
   * @covers ::notify
   */
  public function testRegistrationMailer() {
    $node = $this->createAndSaveNode();
    $registration = $this->createRegistration($node);
    $registration->set('user_uid', 1);
    $registration->save();
    $host_entity = $registration->getHostEntity();

    $user = $this->createUser();
    $registration = $this->createRegistration($node);
    $registration->set('user_uid', $user->id());
    $registration->save();

    $user = $this->createUser();
    $registration = $this->createRegistration($node);
    $registration->set('user_uid', $user->id());
    $registration->save();

    $user = $this->createUser();
    $registration = $this->createRegistration($node);
    $registration->set('user_uid', $user->id());
    $registration->save();

    $recipient_list = $this->registrationMailer->getRecipientList($host_entity);
    $this->assertCount(4, $recipient_list);

    $data = [
      'subject' => 'This is a test',
      'message' => [
        'value' => 'This is a test message',
        'format' => 'plain_text',
      ],
    ];

    $results = $this->registrationMailer->notify($host_entity, $data);
    $this->assertEquals(4, $results);

    // When users have multiple registrations for the same host entity,
    // they receive separate emails for each registration, but they only
    // appear once in the recipient list, and their entry in that list
    // has an array with the registrations.
    $settings = $host_entity->getSettings();
    $settings->set('multiple_registrations', TRUE);
    $settings->save();
    $registration = $this->createRegistration($node);
    $registration->set('user_uid', $user->id());
    $registration->save();
    $recipient_list = $this->registrationMailer->getRecipientList($host_entity);
    // Note that the recipient list count is still only 4.
    $this->assertCount(4, $recipient_list);
    $results = $this->registrationMailer->notify($host_entity, $data);
    $this->assertEquals(5, $results);
  }

}
