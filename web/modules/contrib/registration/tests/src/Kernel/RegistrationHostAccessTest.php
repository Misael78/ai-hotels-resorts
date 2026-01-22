<?php

namespace Drupal\Tests\registration\Kernel;

use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\Tests\registration\Traits\RegistrationCreationTrait;

/**
 * Tests registration host access control.
 *
 * @coversDefaultClass \Drupal\registration\RegistrationHostAccessControlHandler
 *
 * @group registration
 */
class RegistrationHostAccessTest extends RegistrationKernelTestBase {

  use NodeCreationTrait;
  use RegistrationCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected bool $usesSuperUserAccessPolicy = FALSE;

  /**
   * @covers ::access
   */
  public function testAccessHook() {
    $node = $this->createAndSaveNode();
    $handler = $this->entityTypeManager->getHandler('registration', 'host_entity');
    $host_entity = $handler->createHostEntity($node);
    $account = $this->createUser();

    // Without permissions, the user does not have access to 'manage' operation.
    $this->state->set('registration_test_host_access_manage_hook_fired', FALSE);
    $result = $host_entity->access('manage', $account, TRUE);
    $this->assertTrue($this->state->get('registration_test_host_access_manage_hook_fired'));
    // The handler checks node update access which returns forbidden,
    // but for 'manage' this is converted to neutral by the handler.
    $this->assertTrue($result->isNeutral());

    // Second checks get a cached result so hooks don't fire.
    $this->state->set('registration_test_host_access_manage_hook_fired', FALSE);
    $result = $host_entity->access('manage', $account, TRUE);
    $this->assertFalse($this->state->get('registration_test_host_access_manage_hook_fired'));
    $this->assertTrue($result->isNeutral());

    // Resetting the cache allows the hook to fire again.
    $handler = $this->entityTypeManager->getHandler('node', 'registration_host_access');
    $handler->resetCache();
    $this->state->set('registration_test_host_access_manage_hook_fired', FALSE);
    $result = $host_entity->access('manage', $account, TRUE);
    $this->assertTrue($this->state->get('registration_test_host_access_manage_hook_fired'));
    $this->assertTrue($result->isNeutral());

    // The hook can be used to grant access.
    $this->state->set('registration_test_host_access_manage_hook_fired', FALSE);
    $this->state->set('registration_test_host_access_manage_result', "allowed");
    $handler->resetCache();
    $result = $host_entity->access('manage', $account, TRUE);
    $this->assertTrue($this->state->get('registration_test_host_access_manage_hook_fired'));
    $this->assertTrue($result->isAllowed());

    // If the hook forbids access, the access handler is ignored.
    // The access handler would return allowed if consulted for 'manage',
    // because the user has node update access.
    $account = $this->createUser(['bypass node access']);
    $this->state->set('registration_test_host_access_manage_result', "forbidden");
    $handler->resetCache();
    $result = $host_entity->access('manage', $account, TRUE);
    // The result is neutral not forbidden because for 'manage' the hook's
    // forbidden is converted into a neutral by the default handler.
    $this->assertTrue($result->isNeutral());
  }

}
