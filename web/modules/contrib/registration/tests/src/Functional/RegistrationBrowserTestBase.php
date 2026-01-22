<?php

namespace Drupal\Tests\registration\Functional;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\block\Traits\BlockCreationTrait;
use Drupal\user\UserInterface;

/**
 * Defines the base class for registration test cases.
 */
abstract class RegistrationBrowserTestBase extends BrowserTestBase {

  use BlockCreationTrait;
  use StringTranslationTrait;

  /**
   * Modules to enable.
   *
   * Note that when a child class declares its own $modules list, that list
   * doesn't override this one, it just extends it.
   *
   * @var array
   */
  protected static $modules = [
    'block',
    'datetime',
    'field',
    'workflows',
    'registration',
  ];

  /**
   * The default theme.
   *
   * @var mixed
   */
  protected $defaultTheme = 'stark';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * A test user with administrative privileges.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->placeBlock('local_tasks_block');
    $this->placeBlock('local_actions_block');
    $this->placeBlock('page_title_block');

    $this->entityTypeManager = $this->container->get('entity_type.manager');

    // Add a registration field to the User entity.
    $field_storage_values = [
      'field_name' => 'field_registration',
      'entity_type' => 'user',
      'type' => 'registration',
      'translatable' => TRUE,
    ];
    $this->entityTypeManager
      ->getStorage('field_storage_config')
      ->create($field_storage_values)
      ->save();
    $field_values = [
      'field_name' => 'field_registration',
      'entity_type' => 'user',
      'bundle' => 'user',
      'label' => 'Registration',
      'translatable' => FALSE,
    ];
    $this->entityTypeManager
      ->getStorage('field_config')
      ->create($field_values)
      ->save();

    $this->adminUser = $this->drupalCreateUser($this->getAdministratorPermissions());
    $this->drupalLogin($this->adminUser);

    // Create a registration type.
    $registration_type = $this->entityTypeManager
      ->getStorage('registration_type')
      ->create([
        'id' => 'conference',
        'label' => 'Conference',
        'workflow' => 'registration',
        'defaultState' => 'pending',
        'heldExpireTime' => 1,
        'heldExpireState' => 'canceled',
      ]);
    $registration_type->save();
  }

  /**
   * Gets the permissions for the admin user.
   *
   * @return string[]
   *   The permissions.
   */
  protected function getAdministratorPermissions(): array {
    return [
      'access administration pages',
      'access user profiles',
      'administer blocks',
      'administer registration',
      'administer registration types',
      'view the administration theme',
    ];
  }

}
