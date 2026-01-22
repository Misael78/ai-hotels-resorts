<?php

namespace Drupal\Tests\registration\Kernel;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Site\Settings;
use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\Tests\registration\Traits\RegistrationCreationTrait;
use Drupal\registration\Entity\RegistrationType;

/**
 * Tests registration permissions and access control.
 *
 * @coversDefaultClass \Drupal\registration\RegistrationAccessControlHandler
 *
 * @group registration
 */
class RegistrationAccessTest extends RegistrationKernelTestBase {

  use NodeCreationTrait;
  use RegistrationCreationTrait;

  /**
   * The configuration factory.
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->configFactory = $this->container->get('config.factory');

    $admin_user = $this->createUser();
    $this->setCurrentUser($admin_user);

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
    $access_control_handler = $this->entityTypeManager->getAccessControlHandler('registration');

    $account = $this->createUser(['access registration overview']);

    $node = $this->createAndSaveNode();
    $registration = $this->createRegistration($node);
    $registration->set('user_uid', $account->id());
    $registration->save();

    $this->assertFalse($registration->access('view', $account));
    $this->assertFalse($registration->access('update', $account));
    $this->assertFalse($registration->access('delete', $account));
    $this->assertFalse($registration->access('administer', $account));

    // "Own" permissions.
    $account = $this->createUser(['view own registration']);
    $registration->set('user_uid', $account->id());
    $registration->save();
    $this->assertTrue($registration->access('view', $account));
    $this->assertFalse($registration->access('update', $account));
    $this->assertFalse($registration->access('delete', $account));
    $this->assertFalse($registration->access('administer', $account));

    $account = $this->createUser([
      'view own conference registration',
      'update own conference registration',
    ]);
    $registration->set('user_uid', $account->id());
    $registration->save();
    $this->assertTrue($registration->access('view', $account));
    $this->assertTrue($registration->access('update', $account));
    $this->assertFalse($registration->access('delete', $account));
    $this->assertFalse($registration->access('administer', $account));

    $account = $this->createUser([
      'view own conference registration',
      'update own conference registration',
      'delete own conference registration',
    ]);
    $registration->set('user_uid', $account->id());
    $registration->save();
    $this->assertTrue($registration->access('view', $account));
    $this->assertTrue($registration->access('update', $account));
    $this->assertTrue($registration->access('delete', $account));
    $this->assertFalse($registration->access('administer', $account));

    // "Own" permissions for the wrong type.
    $account = $this->createUser([
      'view own seminar registration',
      'update own seminar registration',
      'delete own seminar registration',
    ]);
    $registration->set('user_uid', $account->id());
    $registration->save();
    $this->assertFalse($registration->access('view', $account));
    $this->assertFalse($registration->access('update', $account));
    $this->assertFalse($registration->access('delete', $account));
    $this->assertFalse($registration->access('administer', $account));

    // "View any" permission.
    $account = $this->createUser(['access content']);
    $registration->set('author_uid', $account->id());
    $registration->set('user_uid', $account->id());
    $registration->save();
    $this->assertFalse($registration->access('view', $account));
    $account = $this->createUser(['view any registration']);
    $this->assertTrue($registration->access('view', $account));
    $this->assertFalse($registration->access('update', $account));
    $this->assertFalse($registration->access('delete', $account));
    $this->assertFalse($registration->access('administer', $account));

    // "View host" permission.
    $account = $this->createUser(['view host registration']);
    $this->assertFalse($registration->access('view', $account));
    $this->assertFalse($registration->access('update', $account));
    $this->assertFalse($registration->access('delete', $account));
    $this->assertFalse($registration->access('administer', $account));
    $account = $this->createUser([
      'bypass node access',
      'view host registration',
    ]);
    $this->assertTrue($registration->access('view', $account));
    $this->assertFalse($registration->access('update', $account));
    $this->assertFalse($registration->access('delete', $account));
    $this->assertFalse($registration->access('administer', $account));

    // "Administer" permission.
    $account = $this->createUser(['administer registration']);
    $this->assertTrue($registration->access('view', $account));
    $this->assertTrue($registration->access('update', $account));
    $this->assertTrue($registration->access('delete', $account));
    $this->assertTrue($registration->access('administer', $account));

    // "Administer type" permission.
    $account = $this->createUser(['administer conference registration']);
    $this->assertTrue($registration->access('view', $account));
    $this->assertTrue($registration->access('update', $account));
    $this->assertTrue($registration->access('delete', $account));
    $this->assertTrue($registration->access('administer', $account));

    // "Administer type settings" permission applies only to settings.
    $account = $this->createUser(['administer conference registration settings']);
    $this->assertFalse($registration->access('view', $account));
    $this->assertFalse($registration->access('update', $account));
    $this->assertFalse($registration->access('delete', $account));
    $this->assertFalse($registration->access('administer', $account));

    // "Administer own type" permission.
    $account = $this->createUser(['administer own conference registration']);
    $this->assertFalse($registration->access('view', $account));
    $this->assertFalse($registration->access('update', $account));
    $this->assertFalse($registration->access('delete', $account));
    $this->assertFalse($registration->access('administer', $account));
    $registration->set('user_uid', $account->id());
    $registration->save();
    // @see https://www.drupal.org/project/drupal/issues/2834344
    $access_control_handler->resetCache();
    $this->assertTrue($registration->access('view', $account));
    $this->assertTrue($registration->access('update', $account));
    $this->assertTrue($registration->access('delete', $account));
    $this->assertTrue($registration->access('administer', $account));

    // "Administer own type settings" permission only applies to settings.
    $account = $this->createUser(['administer own conference registration settings']);
    $this->assertFalse($registration->access('view', $account));
    $this->assertFalse($registration->access('update', $account));
    $this->assertFalse($registration->access('delete', $account));
    $this->assertFalse($registration->access('administer', $account));
    $registration->set('user_uid', $account->id());
    $registration->save();
    // @see https://www.drupal.org/project/drupal/issues/2834344
    $access_control_handler->resetCache();
    $this->assertFalse($registration->access('view', $account));
    $this->assertFalse($registration->access('update', $account));
    $this->assertFalse($registration->access('delete', $account));
    $this->assertFalse($registration->access('administer', $account));

    // "Administer types" permission only applies to types.
    $account = $this->createUser(['administer registration types']);
    $this->assertFalse($registration->access('view', $account));
    $this->assertFalse($registration->access('update', $account));
    $this->assertFalse($registration->access('delete', $account));
    $this->assertFalse($registration->access('administer', $account));

    // "Manage" permissions apply to the host entity, not registrations.
    $account = $this->createUser([
      'bypass node access',
      'manage own conference registration',
      'manage conference registration',
      'manage conference registration settings',
      'manage conference registration broadcast',
    ]);
    $this->assertFalse($registration->access('view', $account));
    $this->assertFalse($registration->access('update', $account));
    $this->assertFalse($registration->access('delete', $account));
    $this->assertFalse($registration->access('administer', $account));
  }

  /**
   * @covers ::checkCreateAccess
   */
  public function testCreateAccess() {
    $access_control_handler = $this->entityTypeManager->getAccessControlHandler('registration');

    $account = $this->createUser(['access content']);
    $this->assertFalse($access_control_handler->createAccess('conference', $account));

    $account = $this->createUser(['bypass node access']);
    $this->assertFalse($access_control_handler->createAccess('conference', $account));

    $account = $this->createUser(['administer registration']);
    $this->assertTrue($access_control_handler->createAccess('conference', $account));

    $account = $this->createUser(['administer conference registration']);
    $this->assertTrue($access_control_handler->createAccess('conference', $account));
    $account = $this->createUser(['administer seminar registration']);
    $this->assertFalse($access_control_handler->createAccess('conference', $account));

    $settings = Settings::getInstance() ? Settings::getAll() : [];
    $settings['registration_disable_create_by_administer_bundle_permission'] = TRUE;
    new Settings($settings);
    $account = $this->createUser(['administer conference registration']);
    $this->assertFalse($access_control_handler->createAccess('conference', $account));

    $account = $this->createUser(['create registration']);
    $this->assertTrue($access_control_handler->createAccess('conference', $account));
    $account = $this->createUser(['create conference registration self']);
    $this->assertTrue($access_control_handler->createAccess('conference', $account));
    $account = $this->createUser(['create conference registration other users']);
    $this->assertTrue($access_control_handler->createAccess('conference', $account));
    $account = $this->createUser(['create conference registration other anonymous']);
    $this->assertTrue($access_control_handler->createAccess('conference', $account));

    $account = $this->createUser(['create seminar registration self']);
    $this->assertFalse($access_control_handler->createAccess('conference', $account));
    $account = $this->createUser(['create seminar registration other users']);
    $this->assertFalse($access_control_handler->createAccess('conference', $account));
    $account = $this->createUser(['create seminar registration other anonymous']);
    $this->assertFalse($access_control_handler->createAccess('conference', $account));
  }

  /**
   * Tests delete access for registrations.
   */
  public function testDeleteAccess() {
    $access_control_handler = $this->entityTypeManager->getAccessControlHandler('registration');

    // Delete "own" permission.
    $account = $this->createUser(['delete own conference registration']);
    $node = $this->createAndSaveNode();
    $registration = $this->createRegistration($node);
    $registration->set('author_uid', $account->id());
    $registration->save();
    $this->assertFalse($registration->access('delete', $account));
    $registration->set('user_uid', $account->id());
    $registration->save();
    // @see https://www.drupal.org/project/drupal/issues/2834344
    $access_control_handler->resetCache();
    $this->assertTrue($registration->access('delete', $account));

    // Delete "any" permission.
    $account = $this->createUser(['delete any conference registration']);
    $this->assertTrue($registration->access('delete', $account));
    $account = $this->createUser(['delete any seminar registration']);
    $this->assertFalse($registration->access('delete', $account));

    // Delete "host" permission.
    $account = $this->createUser(['delete host registration']);
    $this->assertFalse($registration->access('delete', $account));
    $account = $this->createUser([
      'bypass node access',
      'delete host registration',
    ]);
    $this->assertTrue($registration->access('delete', $account));
  }

  /**
   * Tests update access for registrations.
   */
  public function testUpdateAccess() {
    $access_control_handler = $this->entityTypeManager->getAccessControlHandler('registration');

    // Update "own" permission.
    $account = $this->createUser(['update own conference registration']);
    $node = $this->createAndSaveNode();
    $registration = $this->createRegistration($node);
    $registration->set('author_uid', $account->id());
    $registration->save();
    $this->assertFalse($registration->access('update', $account));
    $registration->set('user_uid', $account->id());
    $registration->save();
    // @see https://www.drupal.org/project/drupal/issues/2834344
    $access_control_handler->resetCache();
    $this->assertTrue($registration->access('update', $account));

    // By default, regular users cannot update registrations for disabled hosts.
    $host_entity = $registration->getHostEntity();
    $settings = $host_entity->getSettings();
    $settings->set('status', FALSE);
    $settings->save();
    $access_control_handler->resetCache();
    $this->assertFalse($registration->access('update', $account));
    $account = $this->createUser(['administer conference registration']);
    $access_control_handler->resetCache();
    $this->assertTrue($registration->access('update', $account));

    // When the "prevent edit for disabled hosts" option is turned off, regular
    // users can edit registrations for disabled hosts.
    $account = $this->createUser(['update own conference registration']);
    $registration->set('user_uid', $account->id());
    $registration->save();
    $global_settings = $this->configFactory->getEditable('registration.settings');
    $global_settings->set('prevent_edit_disabled', FALSE);
    $global_settings->save();
    $access_control_handler->resetCache();
    $this->assertTrue($registration->access('update', $account));
    $account = $this->createUser(['administer conference registration']);
    $access_control_handler->resetCache();
    $this->assertTrue($registration->access('update', $account));
    $settings->set('status', TRUE);
    $settings->save();
    $access_control_handler->resetCache();

    // Update "any" permission.
    $account = $this->createUser(['update any conference registration']);
    $this->assertTrue($registration->access('update', $account));
    $account = $this->createUser(['update any seminar registration']);
    $this->assertFalse($registration->access('update', $account));

    // Update "host" permission.
    $account = $this->createUser(['update host registration']);
    $this->assertFalse($registration->access('update', $account));
    $account = $this->createUser([
      'bypass node access',
      'update host registration',
    ]);
    $this->assertTrue($registration->access('update', $account));
  }

  /**
   * Tests "edit state" access for registrations.
   */
  public function testEditStateAccess() {
    $node = $this->createAndSaveNode();
    $registration = $this->createAndSaveRegistration($node);

    $account = $this->createUser(['administer registration']);
    $this->assertTrue($registration->access('edit state', $account));

    $account = $this->createUser(['administer conference registration']);
    $this->assertTrue($registration->access('edit state', $account));

    $account = $this->createUser(['edit conference registration state']);
    $this->assertFalse($registration->access('edit state', $account));

    $account = $this->createUser([
      'edit conference registration state',
      'update any conference registration',
    ]);
    $this->assertTrue($registration->access('edit state', $account));

    $account = $this->createUser(['update any conference registration']);
    $this->assertFalse($registration->access('edit state', $account));

    $account = $this->createUser(['access registration overview']);
    $this->assertFalse($registration->access('edit state', $account));

    // Test BC layer. Administrators cannot edit state without also having the
    // "edit state" permission, when settings.php is configured in this way.
    $settings = Settings::getInstance() ? Settings::getAll() : [];
    $settings['registration_disable_edit_state_by_administer_permission'] = TRUE;
    new Settings($settings);

    $account = $this->createUser(['administer registration']);
    $this->assertFalse($registration->access('edit state', $account));

    $account = $this->createUser(['administer conference registration']);
    $this->assertFalse($registration->access('edit state', $account));

    $account = $this->createUser(['edit conference registration state']);
    $this->assertFalse($registration->access('edit state', $account));

    $account = $this->createUser([
      'edit conference registration state',
      'update any conference registration',
    ]);
    $this->assertTrue($registration->access('edit state', $account));

    $account = $this->createUser(['update any conference registration']);
    $this->assertFalse($registration->access('edit state', $account));

    $account = $this->createUser(['access registration overview']);
    $this->assertFalse($registration->access('edit state', $account));

    $account = $this->createUser([
      'administer registration',
      'edit conference registration state',
    ]);
    $this->assertTrue($registration->access('edit state', $account));

    $account = $this->createUser([
      'administer conference registration',
      'edit conference registration state',
    ]);
    $this->assertTrue($registration->access('edit state', $account));
  }

  /**
   * Tests route access for registrations.
   */
  public function testRouteAccess() {
    $node = $this->createAndSaveNode();
    $registration = $this->createRegistration($node);
    $registration->set('user_uid', 1);
    $registration->save();

    $account = $this->createUser(['administer registration']);
    $this->assertTrue($registration->toUrl('collection')->access($account));
    $this->assertTrue($registration->toUrl('edit-form')->access($account));
    $this->assertTrue($registration->toUrl('delete-form')->access($account));

    $account = $this->createUser(['access registration overview']);
    $this->assertTrue($registration->toUrl('collection')->access($account));
    $this->assertFalse($registration->toUrl('edit-form')->access($account));
    $this->assertFalse($registration->toUrl('delete-form')->access($account));

    $account = $this->createUser(['access content overview']);
    $this->assertFalse($registration->toUrl('collection')->access($account));
    $this->assertFalse($registration->toUrl('edit-form')->access($account));
    $this->assertFalse($registration->toUrl('delete-form')->access($account));
  }

  /**
   * Tests cacheability of registration access control.
   */
  public function testAccessCacheability() {
    $node = $this->createAndSaveNode();

    /** @var \Drupal\registration\HostEntityInterface $host_entity */
    $host_entity = $this->entityTypeManager
      ->getHandler($node->getEntityTypeId(), 'registration_host_entity')
      ->createHostEntity($node);

    // Access via "administer" is dependent only on permissions.
    $account = $this->createUser(['administer registration']);
    $registration = $this->createRegistration($node);
    $registration->set('user_uid', $account->id());
    $registration->save();
    $access_result = $registration->access('view', $account, TRUE);
    $this->assertTrue($access_result->isAllowed());
    $metadata = CacheableMetadata::createFromObject($access_result);
    $this->assertNotContains('registration:' . $registration->id(), $metadata->getCacheTags());
    $this->assertNotContains('node:1', $metadata->getCacheTags());
    $this->assertNotContains('config:registration.type.conference', $metadata->getCacheTags());
    $this->assertNotContains('config:workflows.workflow.registration', $metadata->getCacheTags());
    $this->assertNotContains('registration_settings:1', $metadata->getCacheTags());
    $this->assertNotContains('registration_list', $metadata->getCacheTags());
    $this->assertNotContains($host_entity->getRegistrationListCacheTag(), $metadata->getCacheTags());
    $this->assertContains('user.permissions', $metadata->getCacheContexts());
    $this->assertNotContains('user', $metadata->getCacheContexts());
    $this->assertEquals(-1, $metadata->getCacheMaxAge());

    // Access via "administer type" is dependent only on the registration and
    // permissions.
    $account = $this->createUser(['administer conference registration']);
    $registration = $this->createRegistration($node);
    $registration->set('user_uid', $account->id());
    $registration->save();
    $access_result = $registration->access('view', $account, TRUE);
    $this->assertTrue($access_result->isAllowed());
    $metadata = CacheableMetadata::createFromObject($access_result);
    $this->assertContains('registration:' . $registration->id(), $metadata->getCacheTags());
    $this->assertNotContains('node:1', $metadata->getCacheTags());
    $this->assertNotContains('config:registration.type.conference', $metadata->getCacheTags());
    $this->assertNotContains('config:workflows.workflow.registration', $metadata->getCacheTags());
    $this->assertNotContains('registration_settings:1', $metadata->getCacheTags());
    $this->assertNotContains('registration_list', $metadata->getCacheTags());
    $this->assertNotContains($host_entity->getRegistrationListCacheTag(), $metadata->getCacheTags());
    $this->assertContains('user.permissions', $metadata->getCacheContexts());
    $this->assertNotContains('user', $metadata->getCacheContexts());
    $this->assertEquals(-1, $metadata->getCacheMaxAge());

    // Access via "view any type" is dependent only on the registration and
    // permissions.
    $account = $this->createUser(['view any conference registration']);
    $registration = $this->createRegistration($node);
    $registration->set('user_uid', $account->id());
    $registration->save();
    $access_result = $registration->access('view', $account, TRUE);
    $this->assertTrue($access_result->isAllowed());
    $metadata = CacheableMetadata::createFromObject($access_result);
    $this->assertContains('registration:' . $registration->id(), $metadata->getCacheTags());
    $this->assertNotContains('node:1', $metadata->getCacheTags());
    $this->assertNotContains('config:registration.type.conference', $metadata->getCacheTags());
    $this->assertNotContains('config:workflows.workflow.registration', $metadata->getCacheTags());
    $this->assertNotContains('registration_settings:1', $metadata->getCacheTags());
    $this->assertNotContains('registration_list', $metadata->getCacheTags());
    $this->assertNotContains($host_entity->getRegistrationListCacheTag(), $metadata->getCacheTags());
    $this->assertContains('user.permissions', $metadata->getCacheContexts());
    $this->assertNotContains('user', $metadata->getCacheContexts());
    $this->assertEquals(-1, $metadata->getCacheMaxAge());

    // Access via "host" is dependent on the registration, host and permissions.
    // The host includes its entity, settings, registration type and workflow.
    $account = $this->createUser(['view host registration', 'bypass node access']);
    $registration = $this->createRegistration($node);
    $registration->set('user_uid', $account->id());
    $registration->save();
    $access_result = $registration->access('view', $account, TRUE);
    $this->assertTrue($access_result->isAllowed());
    $metadata = CacheableMetadata::createFromObject($access_result);
    $this->assertContains('registration:' . $registration->id(), $metadata->getCacheTags());
    $this->assertContains('node:1', $metadata->getCacheTags());
    $this->assertContains('config:registration.type.conference', $metadata->getCacheTags());
    $this->assertContains('config:workflows.workflow.registration', $metadata->getCacheTags());
    $this->assertContains('registration_settings:1', $metadata->getCacheTags());
    $this->assertNotContains('registration_list', $metadata->getCacheTags());
    $this->assertNotContains($host_entity->getRegistrationListCacheTag(), $metadata->getCacheTags());
    $this->assertContains('user.permissions', $metadata->getCacheContexts());
    $this->assertNotContains('user', $metadata->getCacheContexts());
    $this->assertEquals(-1, $metadata->getCacheMaxAge());

    // Access via "own" is dependent on the registration, host and user.
    // The host includes its entity, settings, registration type and workflow.
    $account = $this->createUser(['view own registration']);
    $registration = $this->createRegistration($node);
    $registration->set('user_uid', $account->id());
    $registration->save();
    $access_result = $registration->access('view', $account, TRUE);
    $this->assertTrue($access_result->isAllowed());
    $metadata = CacheableMetadata::createFromObject($access_result);
    $this->assertContains('registration:' . $registration->id(), $metadata->getCacheTags());
    $this->assertContains('node:1', $metadata->getCacheTags());
    $this->assertContains('config:registration.type.conference', $metadata->getCacheTags());
    $this->assertContains('config:workflows.workflow.registration', $metadata->getCacheTags());
    $this->assertContains('registration_settings:1', $metadata->getCacheTags());
    $this->assertNotContains('registration_list', $metadata->getCacheTags());
    $this->assertNotContains($host_entity->getRegistrationListCacheTag(), $metadata->getCacheTags());
    $this->assertContains('user.permissions', $metadata->getCacheContexts());
    $this->assertContains('user', $metadata->getCacheContexts());
    $this->assertEquals(-1, $metadata->getCacheMaxAge());

    // Access not granted is dependent on the registration, host and user,
    // since all paths are checked trying to find an allowed result.
    // The host includes its entity, settings, registration type and workflow.
    $account = $this->createUser(['access registration overview']);
    $registration = $this->createRegistration($node);
    $registration->set('user_uid', $account->id());
    $registration->save();
    $access_result = $registration->access('view', $account, TRUE);
    $this->assertFalse($access_result->isAllowed());
    $metadata = CacheableMetadata::createFromObject($access_result);
    $this->assertContains('registration:' . $registration->id(), $metadata->getCacheTags());
    $this->assertContains('node:1', $metadata->getCacheTags());
    $this->assertContains('config:registration.type.conference', $metadata->getCacheTags());
    $this->assertContains('config:workflows.workflow.registration', $metadata->getCacheTags());
    $this->assertContains('registration_settings:1', $metadata->getCacheTags());
    $this->assertNotContains('registration_list', $metadata->getCacheTags());
    $this->assertNotContains($host_entity->getRegistrationListCacheTag(), $metadata->getCacheTags());
    $this->assertContains('user.permissions', $metadata->getCacheContexts());
    $this->assertContains('user', $metadata->getCacheContexts());
    $this->assertEquals(-1, $metadata->getCacheMaxAge());

    // Access to manage registrations is dependent on the host and permissions.
    // The host includes its entity, settings, registration type and workflow.
    $account = $this->createUser(['manage conference registration', 'bypass node access']);
    $registration = $this->createRegistration($node);
    $registration->set('user_uid', $account->id());
    $registration->save();
    $host_entity = $registration->getHostEntity();
    $access_result = $host_entity->access('manage registrations', $account, TRUE);
    $this->assertTrue($access_result->isAllowed());
    $metadata = CacheableMetadata::createFromObject($access_result);
    $this->assertNotContains('registration:' . $registration->id(), $metadata->getCacheTags());
    $this->assertContains('node:1', $metadata->getCacheTags());
    $this->assertContains('config:registration.type.conference', $metadata->getCacheTags());
    $this->assertContains('config:workflows.workflow.registration', $metadata->getCacheTags());
    $this->assertContains('registration_settings:1', $metadata->getCacheTags());
    $this->assertNotContains('registration_list', $metadata->getCacheTags());
    $this->assertNotContains($host_entity->getRegistrationListCacheTag(), $metadata->getCacheTags());
    $this->assertContains('user.permissions', $metadata->getCacheContexts());
    $this->assertNotContains('user', $metadata->getCacheContexts());
    $this->assertEquals(-1, $metadata->getCacheMaxAge());

    // Access to manage own registrations is dependent on the host and user.
    // The host includes its entity, settings, registration type and workflow.
    $account = $this->createUser([
      'manage own conference registration',
      'access content',
      'edit own event content',
    ]);
    $node->set('uid', $account->id());
    $node->save();
    $registration = $this->createRegistration($node);
    $registration->set('user_uid', $account->id());
    $registration->save();
    $host_entity = $registration->getHostEntity();
    $access_result = $host_entity->access('manage registrations', $account, TRUE);
    $this->assertTrue($access_result->isAllowed());
    $metadata = CacheableMetadata::createFromObject($access_result);
    $this->assertNotContains('registration:' . $registration->id(), $metadata->getCacheTags());
    $this->assertContains('node:1', $metadata->getCacheTags());
    $this->assertContains('config:registration.type.conference', $metadata->getCacheTags());
    $this->assertContains('config:workflows.workflow.registration', $metadata->getCacheTags());
    $this->assertContains('registration_settings:1', $metadata->getCacheTags());
    $this->assertNotContains('registration_list', $metadata->getCacheTags());
    $this->assertNotContains($host_entity->getRegistrationListCacheTag(), $metadata->getCacheTags());
    $this->assertContains('user.permissions', $metadata->getCacheContexts());
    $this->assertContains('user', $metadata->getCacheContexts());
    $this->assertEquals(-1, $metadata->getCacheMaxAge());

    // Access to manage registration settings is dependent on the host and
    // permissions. The host includes its entity, settings, registration type
    // and workflow.
    $account = $this->createUser([
      'manage conference registration',
      'manage conference registration settings',
      'bypass node access',
    ]);
    $registration = $this->createRegistration($node);
    $registration->set('user_uid', $account->id());
    $registration->save();
    $host_entity = $registration->getHostEntity();
    $access_result = $host_entity->access('manage settings', $account, TRUE);
    $this->assertTrue($access_result->isAllowed());
    $metadata = CacheableMetadata::createFromObject($access_result);
    $this->assertNotContains('registration:' . $registration->id(), $metadata->getCacheTags());
    $this->assertContains('node:1', $metadata->getCacheTags());
    $this->assertContains('config:registration.type.conference', $metadata->getCacheTags());
    $this->assertContains('config:workflows.workflow.registration', $metadata->getCacheTags());
    $this->assertContains('registration_settings:1', $metadata->getCacheTags());
    $this->assertNotContains('registration_list', $metadata->getCacheTags());
    $this->assertNotContains($host_entity->getRegistrationListCacheTag(), $metadata->getCacheTags());
    $this->assertContains('user.permissions', $metadata->getCacheContexts());
    $this->assertNotContains('user', $metadata->getCacheContexts());
    $this->assertEquals(-1, $metadata->getCacheMaxAge());
    // Also check cacheability when access is not granted.
    $account = $this->createUser([
      'manage conference registration settings',
      'bypass node access',
    ]);
    $registration = $this->createRegistration($node);
    $registration->set('user_uid', $account->id());
    $registration->save();
    $host_entity = $registration->getHostEntity();
    $access_result = $host_entity->access('manage settings', $account, TRUE);
    $this->assertFalse($access_result->isAllowed());
    $metadata = CacheableMetadata::createFromObject($access_result);
    $this->assertNotContains('registration:' . $registration->id(), $metadata->getCacheTags());
    $this->assertContains('node:1', $metadata->getCacheTags());
    $this->assertContains('config:registration.type.conference', $metadata->getCacheTags());
    $this->assertContains('config:workflows.workflow.registration', $metadata->getCacheTags());
    $this->assertContains('registration_settings:1', $metadata->getCacheTags());
    $this->assertNotContains('registration_list', $metadata->getCacheTags());
    $this->assertNotContains($host_entity->getRegistrationListCacheTag(), $metadata->getCacheTags());
    $this->assertContains('user.permissions', $metadata->getCacheContexts());
    $this->assertNotContains('user', $metadata->getCacheContexts());
    $this->assertEquals(-1, $metadata->getCacheMaxAge());

    // Access to manage own registration settings is dependent on the host and
    // user. The host includes its entity, settings, registration type and
    // workflow.
    $account = $this->createUser([
      'manage own conference registration',
      'manage conference registration settings',
      'access content',
      'edit own event content',
    ]);
    $node->set('uid', $account->id());
    $node->save();
    $registration = $this->createRegistration($node);
    $registration->set('user_uid', $account->id());
    $registration->save();
    $host_entity = $registration->getHostEntity();
    $access_result = $host_entity->access('manage settings', $account, TRUE);
    $this->assertTrue($access_result->isAllowed());
    $metadata = CacheableMetadata::createFromObject($access_result);
    $this->assertNotContains('registration:' . $registration->id(), $metadata->getCacheTags());
    $this->assertContains('node:1', $metadata->getCacheTags());
    $this->assertContains('config:registration.type.conference', $metadata->getCacheTags());
    $this->assertContains('config:workflows.workflow.registration', $metadata->getCacheTags());
    $this->assertContains('registration_settings:1', $metadata->getCacheTags());
    $this->assertNotContains('registration_list', $metadata->getCacheTags());
    $this->assertNotContains($host_entity->getRegistrationListCacheTag(), $metadata->getCacheTags());
    $this->assertContains('user.permissions', $metadata->getCacheContexts());
    $this->assertContains('user', $metadata->getCacheContexts());
    $this->assertEquals(-1, $metadata->getCacheMaxAge());

    // Access to manage registration broadcasts is dependent on the host and
    // permissions. The host includes its entity, settings, registration type
    // and workflow.
    $account = $this->createUser([
      'manage conference registration',
      'manage conference registration broadcast',
      'bypass node access',
    ]);
    $registration = $this->createRegistration($node);
    $registration->set('user_uid', $account->id());
    $registration->save();
    $host_entity = $registration->getHostEntity();
    $access_result = $host_entity->access('manage broadcast', $account, TRUE);
    $this->assertTrue($access_result->isAllowed());
    $metadata = CacheableMetadata::createFromObject($access_result);
    $this->assertNotContains('registration:' . $registration->id(), $metadata->getCacheTags());
    $this->assertContains('node:1', $metadata->getCacheTags());
    $this->assertContains('config:registration.type.conference', $metadata->getCacheTags());
    $this->assertContains('config:workflows.workflow.registration', $metadata->getCacheTags());
    $this->assertContains('registration_settings:1', $metadata->getCacheTags());
    $this->assertNotContains('registration_list', $metadata->getCacheTags());
    $this->assertNotContains($host_entity->getRegistrationListCacheTag(), $metadata->getCacheTags());
    $this->assertContains('user.permissions', $metadata->getCacheContexts());
    $this->assertNotContains('user', $metadata->getCacheContexts());
    $this->assertEquals(-1, $metadata->getCacheMaxAge());

    // Access to manage own registration broadcasts is dependent on the host
    // and user. The host includes its entity, settings, registration type and
    // workflow.
    $account = $this->createUser([
      'manage own conference registration',
      'manage conference registration broadcast',
      'access content',
      'edit own event content',
    ]);
    $node->set('uid', $account->id());
    $node->save();
    $registration = $this->createRegistration($node);
    $registration->set('user_uid', $account->id());
    $registration->save();
    $host_entity = $registration->getHostEntity();
    $access_result = $host_entity->access('manage broadcast', $account, TRUE);
    $this->assertTrue($access_result->isAllowed());
    $metadata = CacheableMetadata::createFromObject($access_result);
    $this->assertNotContains('registration:' . $registration->id(), $metadata->getCacheTags());
    $this->assertContains('node:1', $metadata->getCacheTags());
    $this->assertContains('config:registration.type.conference', $metadata->getCacheTags());
    $this->assertContains('config:workflows.workflow.registration', $metadata->getCacheTags());
    $this->assertContains('registration_settings:1', $metadata->getCacheTags());
    $this->assertNotContains('registration_list', $metadata->getCacheTags());
    $this->assertNotContains($host_entity->getRegistrationListCacheTag(), $metadata->getCacheTags());
    $this->assertContains('user.permissions', $metadata->getCacheContexts());
    $this->assertContains('user', $metadata->getCacheContexts());
    $this->assertEquals(-1, $metadata->getCacheMaxAge());

    // When the host is not configured for registration, "host" access not
    // granted is dependent on the registration, host and user. The host
    // includes its entity, but no longer has a registration type, workflow or
    // settings.
    $account = $this->createUser(['view host registration', 'bypass node access']);
    $registration = $this->createRegistration($node);
    $registration->set('user_uid', $account->id());
    $registration->save();
    $node->set('event_registration', NULL);
    $node->save();
    $registration = $this->reloadEntity($registration);
    $access_result = $registration->access('view', $account, TRUE);
    $this->assertFalse($access_result->isAllowed());
    $metadata = CacheableMetadata::createFromObject($access_result);
    $this->assertContains('registration:' . $registration->id(), $metadata->getCacheTags());
    $this->assertContains('node:1', $metadata->getCacheTags());
    $this->assertNotContains('config:registration.type.conference', $metadata->getCacheTags());
    $this->assertNotContains('config:workflows.workflow.registration', $metadata->getCacheTags());
    $this->assertNotContains('registration_settings:1', $metadata->getCacheTags());
    $this->assertNotContains('registration_list', $metadata->getCacheTags());
    $this->assertNotContains($host_entity->getRegistrationListCacheTag(), $metadata->getCacheTags());
    $this->assertContains('user.permissions', $metadata->getCacheContexts());
    $this->assertContains('user', $metadata->getCacheContexts());
    $this->assertEquals(-1, $metadata->getCacheMaxAge());

    // When the host is deleted, "host" access not granted depends only on the
    // registration and the user.
    $account = $this->createUser(['view host registration', 'bypass node access']);
    $registration = $this->createRegistration($node);
    $registration->set('user_uid', $account->id());
    $registration->save();
    $node->delete();
    $registration = $this->reloadEntity($registration);
    $access_result = $registration->access('view', $account, TRUE);
    $this->assertFalse($access_result->isAllowed());
    $metadata = CacheableMetadata::createFromObject($access_result);
    $this->assertContains('registration:' . $registration->id(), $metadata->getCacheTags());
    $this->assertNotContains('node:1', $metadata->getCacheTags());
    $this->assertNotContains('config:registration.type.conference', $metadata->getCacheTags());
    $this->assertNotContains('config:workflows.workflow.registration', $metadata->getCacheTags());
    $this->assertNotContains('registration_settings:1', $metadata->getCacheTags());
    $this->assertNotContains('registration_list', $metadata->getCacheTags());
    $this->assertNotContains($host_entity->getRegistrationListCacheTag(), $metadata->getCacheTags());
    $this->assertContains('user.permissions', $metadata->getCacheContexts());
    $this->assertContains('user', $metadata->getCacheContexts());
    $this->assertEquals(-1, $metadata->getCacheMaxAge());

    // When the host is deleted, an administrator can still view the
    // registration, and the access only depends on the permissions.
    $account = $this->createUser(['administer registration']);
    $access_result = $registration->access('view', $account, TRUE);
    $this->assertTrue($access_result->isAllowed());
    $metadata = CacheableMetadata::createFromObject($access_result);
    $this->assertNotContains('registration:' . $registration->id(), $metadata->getCacheTags());
    $this->assertNotContains('node:1', $metadata->getCacheTags());
    $this->assertNotContains('config:registration.type.conference', $metadata->getCacheTags());
    $this->assertNotContains('config:workflows.workflow.registration', $metadata->getCacheTags());
    $this->assertNotContains('registration_settings:1', $metadata->getCacheTags());
    $this->assertNotContains('registration_list', $metadata->getCacheTags());
    $this->assertNotContains($host_entity->getRegistrationListCacheTag(), $metadata->getCacheTags());
    $this->assertContains('user.permissions', $metadata->getCacheContexts());
    $this->assertNotContains('user', $metadata->getCacheContexts());
    $this->assertEquals(-1, $metadata->getCacheMaxAge());
  }

}
