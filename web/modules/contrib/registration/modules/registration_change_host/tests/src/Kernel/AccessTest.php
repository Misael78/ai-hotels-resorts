<?php

namespace Drupal\Tests\registration_change_host\Kernel;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Access\AccessResultReasonInterface;

/**
 * Tests the change_host access operation on the registration entity.
 *
 * @group registration
 * @group registration_change_host
 */
class AccessTest extends RegistrationChangeHostKernelTestBase {

  /**
   * Test access to change host on registration entities.
   *
   * @param array $permissions
   *   The permissions the test user should have.
   * @param bool $is_allowed_own
   *   The user should be allowed to change host on own registrations.
   * @param bool $is_allowed_any
   *   The user should be allowed to change host on other's registrations.
   *
   * @covers ::getPossibleHostsAccess
   * @dataProvider dataProviderChangeHostAccess
   */
  public function testChangeHostAccess(array $permissions, $is_allowed_own, $is_allowed_any) {
    $registered_user = $this->drupalCreateUser($permissions);
    $other_user = $this->drupalCreateUser($permissions);
    $registration = $this->createRegistration($this->originalHostNode);
    $registration->set('user_uid', $registered_user);
    $registration->save();

    $this->setCurrentUser($registered_user);
    $access = $registration->access('change host', $registered_user, TRUE);
    $this->assertSame($is_allowed_own, $access->isAllowed(), $this->getAccessMessage($is_allowed_own, $access));

    $this->setCurrentUser($other_user);
    $access = $registration->access('change host', $other_user, TRUE);
    $this->assertSame($is_allowed_any, $access->isAllowed(), $this->getAccessMessage($is_allowed_any, $access));

    \Drupal::entityTypeManager()->getHandler('registration', 'access')->resetCache();
    $this->registration->getHostEntity()->getSettings()->set('status', FALSE)->save();
    $access = $registration->access('change host', $registered_user, TRUE);
    $this->assertFalse($access->isAllowed(), $this->getAccessMessage(FALSE, $access, "Access denied because host is disabled"));
  }

  /**
   * Get a message for asserting the access result.
   *
   * @param bool $expected
   *   Whether access is expected to be allowed.
   * @param \Drupal\Core\Access\AccessResultInterface $access
   *   The access result.
   * @param string $message
   *   A message to display.
   *
   * @return string
   *   The message including access result reason.
   */
  protected function getAccessMessage(bool $expected, AccessResultInterface $access, $message = '') {
    $allowed = $expected ? 'Access should be allowed but was not.' : 'Access should not be allowed but was.';
    $reason = '';
    if (!$access->isAllowed()) {
      $reason = $access instanceof AccessResultReasonInterface ? $access->getReason() : 'No reason given, likely indicates a cached access result was returned.';
    }
    return "$allowed \n$message \n$reason";
  }

  /**
   * Data provider for host access test.
   *
   * @return \Generator
   *   The test data.
   */
  public function dataProviderChangeHostAccess() {
    return [
      'change host own but not update' => [
        'permissions' => ['change host own registration'],
        'is_allowed_own' => FALSE,
        'is_allowed_any' => FALSE,
      ],
      'change host own and update own' => [
        'permissions' => ['change host own registration', 'update own conference registration'],
        'is_allowed_own' => TRUE,
        'is_allowed_any' => FALSE,
      ],
      'change host own and update any' => [
        'permissions' => ['change host own registration', 'update any conference registration'],
        'is_allowed_own' => TRUE,
        'is_allowed_any' => FALSE,
      ],
      'change host any but not update' => [
        'permissions' => ['change host any registration'],
        'is_allowed_own' => FALSE,
        'is_allowed_any' => FALSE,
      ],
      'change host any and update own' => [
        'permissions' => ['change host any registration', 'update own conference registration'],
        'is_allowed_own' => TRUE,
        'is_allowed_any' => FALSE,
      ],
      'change host any and update any' => [
        'permissions' => ['change host any registration', 'update any conference registration'],
        'is_allowed_own' => TRUE,
        'is_allowed_any' => TRUE,
      ],
    ];
  }

}
