<?php

namespace Drupal\Tests\registration\Kernel\Form;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\user\Entity\User;
use Drupal\Tests\registration\Kernel\CurrentRouteMatch;
use Drupal\Tests\registration\Kernel\Plugin\Field\Formatter\FormatterTestBase;
use Drupal\Tests\registration\Traits\NodeCreationTrait;

/**
 * Tests the register form.
 *
 * @group registration
 */
class RegisterFormTest extends FormatterTestBase implements ServiceModifierInterface {

  use NodeCreationTrait;

  /**
   * The entity form builder.
   *
   * @var \Drupal\Core\Entity\EntityFormBuilderInterface
   */
  protected EntityFormBuilderInterface $entityFormBuilder;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityFormBuilder = $this->container->get('entity.form_builder');

    $user = User::getAnonymousUser();
    $this->setCurrentUser($user);
  }

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // Set up an override that returns the route for node/1. The route is
    // needed during form rendering.
    $service_definition = $container->getDefinition('current_route_match');
    $service_definition->setClass(CurrentRouteMatch::class);
  }

  /**
   * Tests that the form builds and has the correct cache information.
   */
  public function testRegisterForm() {
    $node = $this->createAndSaveNode();

    /** @var \Drupal\registration\HostEntityInterface $host_entity */
    $host_entity = $this->entityTypeManager
      ->getHandler($node->getEntityTypeId(), 'registration_host_entity')
      ->createHostEntity($node);
    $registration = $this->entityTypeManager->getStorage('registration')->create([
      'entity_type_id' => $host_entity->getEntityTypeId(),
      'entity_id' => $host_entity->id(),
      'type' => 'conference',
    ]);

    // Anonymous user.
    $form = $this->entityFormBuilder->getForm($registration, 'register', [
      'host_entity' => $host_entity,
    ]);
    $output = $this->renderElement($form);
    $this->assertStringContainsString('<form class="registration-conference-register-form', $output);
    $this->assertStringContainsString('Save Registration', $output);
    $this->assertStringNotContainsString('Registration for <em class="placeholder">My event</em> is not open yet.', $output);
    $metadata = CacheableMetadata::createFromRenderArray($form);
    $this->assertContains('node:1', $metadata->getCacheTags());
    $this->assertContains('config:registration.type.conference', $metadata->getCacheTags());
    $this->assertContains('config:workflows.workflow.registration', $metadata->getCacheTags());
    // Settings have not been saved yet for the host entity, so the cache tags
    // for the settings entity are not present, but the settings list tag is.
    $this->assertNotContains('registration_settings:1', $metadata->getCacheTags());
    $this->assertNotContains('registration_settings_list', $metadata->getCacheTags());
    $this->assertContains($host_entity->getRegistrationSettingsListCacheTag(), $metadata->getCacheTags());
    $this->assertNotContains('registration_list', $metadata->getCacheTags());
    $this->assertContains($host_entity->getRegistrationListCacheTag(), $metadata->getCacheTags());
    $this->assertContains('session', $metadata->getCacheContexts());
    $this->assertEquals(-1, $metadata->getCacheMaxAge());

    // Logged in user.
    $user = $this->createUser(['create conference registration self']);
    $this->setCurrentUser($user);
    $form = $this->entityFormBuilder->getForm($registration, 'register', [
      'host_entity' => $host_entity,
    ]);
    $this->assertStringContainsString('<form class="registration-conference-register-form', $output);
    $this->assertStringContainsString('Save Registration', $output);
    $this->assertStringNotContainsString('Registration for <em class="placeholder">My event</em> is not open yet.', $output);
    $metadata = CacheableMetadata::createFromRenderArray($form);
    $this->assertContains('node:1', $metadata->getCacheTags());
    $this->assertContains('config:registration.type.conference', $metadata->getCacheTags());
    $this->assertContains('config:workflows.workflow.registration', $metadata->getCacheTags());
    $this->assertNotContains('registration_settings:1', $metadata->getCacheTags());
    $this->assertNotContains('registration_settings_list', $metadata->getCacheTags());
    $this->assertContains($host_entity->getRegistrationSettingsListCacheTag(), $metadata->getCacheTags());
    $this->assertNotContains('registration_list', $metadata->getCacheTags());
    $this->assertContains($host_entity->getRegistrationListCacheTag(), $metadata->getCacheTags());
    $this->assertContains('user', $metadata->getCacheContexts());
    $this->assertContains('user.permissions', $metadata->getCacheContexts());
    $this->assertNotContains('session', $metadata->getCacheContexts());
    $this->assertEquals(-1, $metadata->getCacheMaxAge());

    // Allow multiple registrations per user.
    $settings = $host_entity->getSettings();
    $settings->set('multiple_registrations', TRUE);
    $settings->save();
    $form = $this->entityFormBuilder->getForm($registration, 'register', [
      'host_entity' => $host_entity,
    ]);
    $this->assertStringContainsString('<form class="registration-conference-register-form', $output);
    $this->assertStringContainsString('Save Registration', $output);
    $this->assertStringNotContainsString('Registration for <em class="placeholder">My event</em> is not open yet.', $output);
    $metadata = CacheableMetadata::createFromRenderArray($form);
    $this->assertContains('node:1', $metadata->getCacheTags());
    $this->assertContains('config:registration.type.conference', $metadata->getCacheTags());
    $this->assertContains('config:workflows.workflow.registration', $metadata->getCacheTags());
    // Settings were saved so the cache tag for the settings entity is present.
    $this->assertContains('registration_settings:1', $metadata->getCacheTags());
    $this->assertNotContains('registration_settings_list', $metadata->getCacheTags());
    $this->assertNotContains($host_entity->getRegistrationSettingsListCacheTag(), $metadata->getCacheTags());
    $this->assertNotContains('registration_list', $metadata->getCacheTags());
    $this->assertContains($host_entity->getRegistrationListCacheTag(), $metadata->getCacheTags());
    // The user cache context is not present when multiple registrations per
    // user are allowed, since the current user does not have to be checked
    // against the existing registrants.
    $this->assertNotContains('user', $metadata->getCacheContexts());
    $this->assertContains('user.permissions', $metadata->getCacheContexts());
    $this->assertNotContains('session', $metadata->getCacheContexts());
    $this->assertEquals(-1, $metadata->getCacheMaxAge());

    // Disable registration.
    $settings->set('open', '2220-01-01T00:00:00');
    $settings->save();
    $form = $this->entityFormBuilder->getForm($registration, 'register', [
      'host_entity' => $host_entity,
    ]);
    $output = $this->renderElement($form);
    $this->assertStringContainsString('<form class="registration-conference-register-form', $output);
    $this->assertStringNotContainsString('Save Registration', $output);
    $this->assertStringContainsString('Registration for <em class="placeholder">My event</em> is not open yet.', $output);
    $metadata = CacheableMetadata::createFromRenderArray($form);
    $this->assertContains('node:1', $metadata->getCacheTags());
    $this->assertContains('config:registration.type.conference', $metadata->getCacheTags());
    $this->assertContains('config:workflows.workflow.registration', $metadata->getCacheTags());
    $this->assertContains('registration_settings:1', $metadata->getCacheTags());
    $this->assertNotContains('registration_settings_list', $metadata->getCacheTags());
    $this->assertNotContains($host_entity->getRegistrationSettingsListCacheTag(), $metadata->getCacheTags());
    $this->assertNotContains('registration_list', $metadata->getCacheTags());
    $this->assertContains($host_entity->getRegistrationListCacheTag(), $metadata->getCacheTags());
    $this->assertNotContains('user', $metadata->getCacheContexts());
    $this->assertContains('user.permissions', $metadata->getCacheContexts());
    $this->assertNotContains('session', $metadata->getCacheContexts());
    // The cache max-age is set to expire on the open date.
    $this->assertNotEquals(-1, $metadata->getCacheMaxAge());

    // Edit registration.
    $settings->set('open', NULL);
    $settings->save();
    $user = $this->createUser([
      'create conference registration self',
      'update own conference registration',
    ]);
    $this->setCurrentUser($user);
    $registration->set('author_uid', $user->id());
    $registration->set('user_uid', $user->id());
    $registration->save();
    $registration = $this->reloadEntity($registration);
    $form = $this->entityFormBuilder->getForm($registration, 'register', [
      'host_entity' => $host_entity,
    ]);
    $output = $this->renderElement($form);
    $this->assertStringContainsString('<form class="registration-conference-register-form', $output);
    $this->assertStringContainsString('Save Registration', $output);
    $metadata = CacheableMetadata::createFromRenderArray($form);
    $this->assertContains('node:1', $metadata->getCacheTags());
    $this->assertContains('config:registration.type.conference', $metadata->getCacheTags());
    $this->assertContains('config:workflows.workflow.registration', $metadata->getCacheTags());
    $this->assertContains('registration:1', $metadata->getCacheTags());
    $this->assertContains('registration.user:' . $user->id(), $metadata->getCacheTags());
    $this->assertContains('registration_settings:1', $metadata->getCacheTags());
    $this->assertNotContains('registration_settings_list', $metadata->getCacheTags());
    $this->assertNotContains($host_entity->getRegistrationSettingsListCacheTag(), $metadata->getCacheTags());
    $this->assertNotContains('registration_list', $metadata->getCacheTags());
    $this->assertNotContains($host_entity->getRegistrationListCacheTag(), $metadata->getCacheTags());
    $this->assertContains('user', $metadata->getCacheContexts());
    $this->assertContains('user.permissions', $metadata->getCacheContexts());
    $this->assertNotContains('session', $metadata->getCacheContexts());
    $this->assertEquals(-1, $metadata->getCacheMaxAge());
  }

}
