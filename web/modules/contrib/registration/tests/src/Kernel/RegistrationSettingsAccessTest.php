<?php

namespace Drupal\Tests\registration\Kernel;

use Drupal\Core\Url;
use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\node\NodeInterface;
use Drupal\registration\Entity\RegistrationSettings;
use Drupal\registration\Entity\RegistrationType;

/**
 * Tests registration settings permissions and access control.
 *
 * @coversDefaultClass \Drupal\registration\RegistrationAccessControlHandler
 *
 * @group registration
 */
class RegistrationSettingsAccessTest extends RegistrationKernelTestBase {

  use NodeCreationTrait;

  /**
   * The node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected NodeInterface $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $admin_user = $this->createUser();
    $this->setCurrentUser($admin_user);

    $this->node = $this->createAndSaveNode();

    $registration_type = RegistrationType::create([
      'id' => 'seminar',
      'label' => 'Seminar',
      'workflow' => 'registration',
      'defaultState' => 'pending',
      'heldExpireTime' => 1,
      'heldExpireState' => 'canceled',
    ]);
    $registration_type->save();
  }

  /**
   * @covers ::checkAccess
   */
  public function testAccess() {
    $settings = RegistrationSettings::create([
      'entity_type_id' => 'node',
      'entity_id' => $this->node->id(),
    ]);
    $settings->save();

    $account = $this->createUser(['access content']);
    $this->assertFalse($settings->access('view', $account));
    $this->assertFalse($settings->access('update', $account));
    $this->assertFalse($settings->access('delete', $account));

    $account = $this->createUser(['administer registration']);
    $this->assertTrue($settings->access('view', $account));
    $this->assertTrue($settings->access('update', $account));
    $this->assertTrue($settings->access('delete', $account));

    // "Own" permission also requires edit access to the host entity.
    $account = $this->createUser([
      'administer own conference registration settings',
    ]);
    $this->assertFalse($settings->access('view', $account));
    $this->assertFalse($settings->access('update', $account));
    $this->assertFalse($settings->access('delete', $account));

    $account = $this->createUser([
      'administer own conference registration settings',
      'bypass node access',
    ]);
    $this->assertTrue($settings->access('view', $account));
    $this->assertTrue($settings->access('update', $account));
    $this->assertTrue($settings->access('delete', $account));

    // Only settings permission gives access.
    $account = $this->createUser([
      'administer own conference registration',
      'bypass node access',
    ]);
    $this->assertFalse($settings->access('view', $account));
    $this->assertFalse($settings->access('update', $account));
    $this->assertFalse($settings->access('delete', $account));

    // The node is configured for conference registration, so seminar
    // registration permission should not grant access.
    $account = $this->createUser([
      'administer own seminar registration settings',
      'bypass node access',
    ]);
    $this->assertFalse($settings->access('view', $account));
    $this->assertFalse($settings->access('update', $account));
    $this->assertFalse($settings->access('delete', $account));
  }

  /**
   * @covers ::checkCreateAccess
   */
  public function testCreateAccess() {
    $access_control_handler = $this->entityTypeManager->getAccessControlHandler('registration');

    $account = $this->createUser(['access content']);
    $this->assertFalse($access_control_handler->createAccess(NULL, $account));

    $account = $this->createUser(['administer registration']);
    $this->assertTrue($access_control_handler->createAccess(NULL, $account));
  }

  /**
   * Tests route access for registration settings.
   */
  public function testRouteAccess() {
    // Manage registrations route.
    $url = Url::fromRoute('entity.node.registration.manage_registrations', [
      'node' => $this->node->id(),
    ]);
    $account = $this->createUser(['administer registration']);
    $this->assertTrue($url->access($account));
    $account = $this->createUser(['access registration overview']);
    $this->assertFalse($url->access($account));
    $account = $this->createUser([
      'administer own conference registration',
      'bypass node access',
    ]);
    $this->assertFalse($url->access($account));
    $account = $this->createUser([
      'administer own seminar registration',
      'bypass node access',
    ]);
    $this->assertFalse($url->access($account));
    $account = $this->createUser([
      'administer own conference registration settings',
      'bypass node access',
    ]);
    $this->assertTrue($url->access($account));
    $account = $this->createUser([
      'administer own conference registration settings',
    ]);
    $this->assertFalse($url->access($account));
    $account = $this->createUser([
      'administer own seminar registration settings',
      'bypass node access',
    ]);
    $this->assertFalse($url->access($account));
    $account = $this->createUser(['manage conference registration']);
    $this->assertTrue($url->access($account));
    $account = $this->createUser([
      'manage own conference registration',
      'bypass node access',
    ]);
    $this->assertTrue($url->access($account));
    $account = $this->createUser([
      'manage own conference registration',
    ]);
    $this->assertFalse($url->access($account));

    // Edit settings route.
    $url = Url::fromRoute('entity.node.registration.registration_settings', [
      'node' => $this->node->id(),
    ]);
    $account = $this->createUser(['administer registration']);
    $this->assertTrue($url->access($account));
    $account = $this->createUser(['access registration overview']);
    $this->assertFalse($url->access($account));
    $account = $this->createUser([
      'administer own conference registration',
      'bypass node access',
    ]);
    $this->assertFalse($url->access($account));
    $account = $this->createUser([
      'administer own seminar registration',
      'bypass node access',
    ]);
    $this->assertFalse($url->access($account));
    $account = $this->createUser([
      'administer own conference registration settings',
      'bypass node access',
    ]);
    $this->assertTrue($url->access($account));
    $account = $this->createUser([
      'administer own conference registration settings',
    ]);
    $this->assertFalse($url->access($account));
    $account = $this->createUser([
      'administer own seminar registration settings',
      'bypass node access',
    ]);
    $this->assertFalse($url->access($account));
    $account = $this->createUser(['manage conference registration']);
    $this->assertFalse($url->access($account));
    $account = $this->createUser(['manage conference registration settings']);
    $this->assertFalse($url->access($account));
    $account = $this->createUser([
      'manage conference registration',
      'manage conference registration settings',
    ]);
    $this->assertTrue($url->access($account));
    $account = $this->createUser([
      'manage own conference registration',
      'bypass node access',
    ]);
    $this->assertFalse($url->access($account));
    $account = $this->createUser([
      'manage own conference registration',
      'manage conference registration settings',
    ]);
    $this->assertFalse($url->access($account));
    $account = $this->createUser([
      'manage own conference registration',
      'manage conference registration settings',
      'bypass node access',
    ]);
    $this->assertTrue($url->access($account));
    $account = $this->createUser([
      'manage own conference registration',
      'manage conference registration broadcast',
      'bypass node access',
    ]);
    $this->assertFalse($url->access($account));

    // Broadcast route.
    $url = Url::fromRoute('entity.node.registration.broadcast', [
      'node' => $this->node->id(),
    ]);
    $account = $this->createUser(['administer registration']);
    $this->assertTrue($url->access($account));
    $account = $this->createUser(['access registration overview']);
    $this->assertFalse($url->access($account));
    $account = $this->createUser([
      'administer own conference registration',
      'bypass node access',
    ]);
    $this->assertFalse($url->access($account));
    $account = $this->createUser([
      'administer own seminar registration',
      'bypass node access',
    ]);
    $this->assertFalse($url->access($account));
    $account = $this->createUser([
      'administer own conference registration settings',
      'bypass node access',
    ]);
    $this->assertTrue($url->access($account));
    $account = $this->createUser([
      'administer own conference registration settings',
    ]);
    $this->assertFalse($url->access($account));
    $account = $this->createUser([
      'administer own seminar registration settings',
      'bypass node access',
    ]);
    $this->assertFalse($url->access($account));
    $account = $this->createUser(['manage conference registration']);
    $this->assertFalse($url->access($account));
    $account = $this->createUser(['manage conference registration broadcast']);
    $this->assertFalse($url->access($account));
    $account = $this->createUser([
      'manage conference registration',
      'manage conference registration settings',
    ]);
    $this->assertFalse($url->access($account));
    $account = $this->createUser([
      'manage conference registration',
      'manage conference registration broadcast',
    ]);
    $this->assertTrue($url->access($account));
    $account = $this->createUser([
      'manage own conference registration',
      'bypass node access',
    ]);
    $this->assertFalse($url->access($account));
    $account = $this->createUser([
      'manage own conference registration',
      'manage conference registration broadcast',
    ]);
    $this->assertFalse($url->access($account));
    $account = $this->createUser([
      'manage own conference registration',
      'manage conference registration broadcast',
      'bypass node access',
    ]);
    $this->assertTrue($url->access($account));
    $account = $this->createUser([
      'manage own conference registration',
      'manage conference registration settings',
      'bypass node access',
    ]);
    $this->assertFalse($url->access($account));
  }

}
