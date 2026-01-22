<?php

namespace Drupal\Tests\commerce_registration\Functional;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\commerce_product\Entity\ProductType;
use Drupal\commerce_product\Entity\ProductVariationType;
use Drupal\commerce_store\Entity\StoreInterface;
use Drupal\commerce_store\StoreCreationTrait;
use Drupal\registration\Entity\RegistrationType;
use Drupal\user\UserInterface;

/**
 * Defines the base class for commerce registration test cases.
 */
abstract class CommerceRegistrationBrowserTestBase extends BrowserTestBase {

  use StoreCreationTrait;

  /**
   * Modules to enable.
   *
   * Note that when a child class declares its own $modules list, that list
   * doesn't override this one, it just extends it.
   *
   * @var array
   */
  protected static $modules = [
    'address',
    'block',
    'commerce',
    'commerce_cart',
    'commerce_checkout',
    'commerce_number_pattern',
    'commerce_order',
    'commerce_payment',
    'commerce_price',
    'commerce_product',
    'commerce_registration',
    'commerce_store',
    'datetime',
    'entity',
    'entity_reference_revisions',
    'inline_entity_form',
    'options',
    'path',
    'path_alias',
    'profile',
    'registration',
    'state_machine',
    'system',
    'text',
    'token',
    'views',
    'workflows',
  ];

  /**
   * The default theme.
   *
   * @var mixed
   */
  protected $defaultTheme = 'stark';

  /**
   * Allow schema violations since new test fields are added in some tests.
   *
   * @var bool
   */
  protected $strictConfigSchema = FALSE;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The store.
   *
   * @var \Drupal\commerce_store\Entity\StoreInterface
   */
  protected StoreInterface $store;

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

    $this->entityTypeManager = $this->container->get('entity_type.manager');

    $product_variation_type = ProductVariationType::create([
      'id' => 'event',
      'label' => 'Event',
      'orderItemType' => 'default',
    ]);
    $product_variation_type->save();

    $product_type = ProductType::create([
      'id' => 'event',
      'label' => 'Event',
      'variationType' => 'event',
      'multipleVariations' => TRUE,
    ]);
    $product_type->save();

    $registration_type = RegistrationType::create([
      'id' => 'conference',
      'label' => 'Conference',
      'workflow' => 'registration',
      'defaultState' => 'pending',
      'heldExpireTime' => 1,
      'heldExpireState' => 'canceled',
    ]);
    $registration_type->save();

    $this->store = $this->createStore();

    // Add a registration field to the commerce product variation entity.
    $field_storage_values = [
      'field_name' => 'event_registration',
      'entity_type' => 'commerce_product_variation',
      'type' => 'registration',
      'translatable' => TRUE,
    ];
    $this->entityTypeManager
      ->getStorage('field_storage_config')
      ->create($field_storage_values)
      ->save();
    $field_values = [
      'field_name' => 'event_registration',
      'entity_type' => 'commerce_product_variation',
      'bundle' => 'event',
      'label' => 'Registration',
      'translatable' => FALSE,
    ];
    $this->entityTypeManager
      ->getStorage('field_config')
      ->create($field_values)
      ->save();

    $this->adminUser = $this->drupalCreateUser($this->getAdministratorPermissions());
    $this->drupalLogin($this->adminUser);
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
      'access commerce administration pages',
      'access user profiles',
      'administer commerce_checkout_flow',
      'administer commerce_currency',
      'administer commerce_order',
      'administer commerce_payment',
      'administer commerce_store',
      'administer commerce_store_type',
      'administer registration',
      'administer registration types',
      'view the administration theme',
    ];
  }

}
