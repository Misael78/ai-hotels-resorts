<?php

namespace Drupal\Tests\registration\Kernel;

use Drupal\Core\Action\ActionManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\Tests\registration\Traits\RegistrationCreationTrait;

/**
 * Tests email headers.
 *
 * @group registration
 */
class RegistrationHeaderTest extends RegistrationKernelTestBase {

  use NodeCreationTrait;
  use RegistrationCreationTrait;

  /**
   * The action manager.
   */
  protected ActionManager $actionManager;

  /**
   * The configuration factory.
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'registration_test_email',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig('filter');

    $admin_user = $this->createUser();
    $this->setCurrentUser($admin_user);

    $this->actionManager = $this->container->get('plugin.manager.action');
    $this->configFactory = $this->container->get('config.factory');
  }

  /**
   * Tests handling of email headers.
   */
  public function testEmailHeaders() {
    $action = $this->actionManager->createInstance('registration_send_email_action');
    $action->setConfiguration([
      'subject' => 'My test email',
      'message' => [
        'value' => 'This is a test email message.',
        'format' => 'plain_text',
      ],
    ]);

    $node = $this->createAndSaveNode();
    $registration = $this->createRegistration($node);
    $registration->set('user_uid', 1);
    $registration->save();

    $site_config = $this->configFactory->getEditable('system.site');
    $site_config->set('name', 'Example Site');
    $site_config->set('mail', 'info@example.org');
    $site_config->save();

    $global_settings = $this->configFactory->getEditable('registration.settings');

    $host_entity = $registration->getHostEntity();
    $settings = $host_entity->getSettings();
    $settings->set('from_address', 'webmaster@example.org');
    $settings->save();

    // The default "From" header is derived from site name and email.
    $result = $action->execute($registration);
    $this->assertTrue($result);
    $this->assertEquals('Example Site <info@example.org>', $this->state->get('registration_from'));

    // Replace the default using the site name and the from address in
    // registration settings.
    $global_settings->set('replace_from_header', TRUE);
    $global_settings->save();
    $result = $action->execute($registration);
    $this->assertTrue($result);
    $this->assertEquals('Example Site <webmaster@example.org>', $this->state->get('registration_from'));

    // Replace the default using only the from address in registration settings,
    // since it is already in the form of a header.
    $settings->set('from_address', 'My Site <mysite@example.org>');
    $settings->save();
    $result = $action->execute($registration);
    $this->assertTrue($result);
    $this->assertEquals('My Site <mysite@example.org>', $this->state->get('registration_from'));
  }

}
