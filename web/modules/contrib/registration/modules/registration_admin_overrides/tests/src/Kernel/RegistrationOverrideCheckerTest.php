<?php

namespace Drupal\Tests\registration_admin_overrides\Kernel;

use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\registration_admin_overrides\RegistrationOverrideCheckerInterface;

/**
 * Tests the registration override checker.
 *
 * @coversDefaultClass \Drupal\registration_admin_overrides\RegistrationOverrideChecker
 *
 * @group registration
 */
class RegistrationOverrideCheckerTest extends RegistrationAdminOverridesKernelTestBase {

  use NodeCreationTrait;

  /**
   * The override checker.
   *
   * @var \Drupal\registration_admin_overrides\RegistrationOverrideCheckerInterface
   */
  protected RegistrationOverrideCheckerInterface $overrideChecker;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->overrideChecker = $this->container->get('registration_admin_overrides.override_checker');
  }

  /**
   * @covers ::accountCanOverride
   */
  public function testRegistrationOverrideChecker() {
    $handler = $this->entityTypeManager->getHandler('node', 'registration_host_entity');

    $node = $this->createAndSaveNode();
    $host_entity = $handler->createHostEntity($node);

    $settings = array_keys($this->overrideChecker->getOverridableSettings($host_entity->getRegistrationType()));
    $this->assertContains('capacity', $settings);
    $this->assertContains('status', $settings);

    // No administrator or override permissions.
    $account = $this->createUser(['create conference registration self']);
    $this->assertFalse($this->overrideChecker->accountCanOverride($host_entity, $account, 'enabled'));

    // Administrator permission but no override permissions.
    $account = $this->createUser(['administer conference registration']);
    $this->assertFalse($this->overrideChecker->accountCanOverride($host_entity, $account, 'enabled'));
    $this->assertFalse($this->overrideChecker->accountCanOverride($host_entity, $account, 'capacity'));

    // Administrator permission and one override permission.
    $account = $this->createUser([
      'administer conference registration',
      'registration override capacity',
    ]);
    $this->assertFalse($this->overrideChecker->accountCanOverride($host_entity, $account, 'enabled'));
    $this->assertTrue($this->overrideChecker->accountCanOverride($host_entity, $account, 'capacity'));

    // Administrator permission and one override permission, but override
    // setting matching the override permission not set for the registration
    // type.
    $this->regType->setThirdPartySetting('registration_admin_overrides', 'capacity', FALSE);
    $this->regType->save();
    $this->assertFalse($this->overrideChecker->accountCanOverride($host_entity, $account, 'capacity'));

    // Administrator permission and two override permissions, but only one has
    // a matching override setting on the registration type.
    $account = $this->createUser([
      'administer conference registration',
      'registration override status',
      'registration override capacity',
    ]);
    $this->assertTrue($this->overrideChecker->accountCanOverride($host_entity, $account, 'status'));
    $this->assertFalse($this->overrideChecker->accountCanOverride($host_entity, $account, 'capacity'));

    // Override permissions but no administrator permission.
    $account = $this->createUser([
      'registration override status',
      'registration override capacity',
    ]);
    $this->assertFalse($this->overrideChecker->accountCanOverride($host_entity, $account, 'status'));
    $this->assertFalse($this->overrideChecker->accountCanOverride($host_entity, $account, 'capacity'));
  }

}
