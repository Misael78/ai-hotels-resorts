<?php

namespace Drupal\Tests\registration_inline_entity_form\Kernel;

use Drupal\Tests\registration\Kernel\RegistrationKernelTestBase;
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
   * Modules to enable.
   *
   * Note that when a child class declares its own $modules list, that list
   * doesn't override this one, it just extends it.
   *
   * @var array
   */
  protected static $modules = [
    'inline_entity_form',
    'registration_inline_entity_form',
  ];

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
    // Tests include "bypass node access" permission since edit access to the
    // host entity is required for "edit registration settings" access to apply.
    $settings = RegistrationSettings::create([
      'entity_type_id' => 'node',
      'entity_id' => $this->node->id(),
    ]);
    $settings->save();

    // Edit registration settings.
    $account = $this->createUser([
      'bypass node access',
      'edit registration settings',
    ]);
    $this->assertTrue($settings->access('view', $account));
    $this->assertTrue($settings->access('update', $account));
    $this->assertTrue($settings->access('delete', $account));

    // Edit type registration settings.
    $account = $this->createUser([
      'bypass node access',
      'edit conference registration settings',
    ]);
    $this->assertTrue($settings->access('view', $account));
    $this->assertTrue($settings->access('update', $account));
    $this->assertTrue($settings->access('delete', $account));

    // Wrong type.
    $account = $this->createUser([
      'bypass node access',
      'edit seminar registration settings',
    ]);
    $this->assertFalse($settings->access('view', $account));
    $this->assertFalse($settings->access('update', $account));
    $this->assertFalse($settings->access('delete', $account));

    // Edit type registration settings only applies to existing nodes.
    $node = $this->createAndSaveNode();
    $settings = RegistrationSettings::create([
      'entity_type_id' => 'node',
      'entity_id' => $node->id(),
    ]);
    $account = $this->createUser([
      'bypass node access',
      'edit conference registration settings',
    ]);
    $this->assertTrue($settings->access('view', $account));
    $this->assertTrue($settings->access('update', $account));
    $node = $this->createNode();
    $settings = RegistrationSettings::create([
      'entity_type_id' => 'node',
      'entity_id' => $node->id(),
    ]);
    $this->assertFalse($settings->access('view', $account));
    $this->assertFalse($settings->access('update', $account));
  }

}
