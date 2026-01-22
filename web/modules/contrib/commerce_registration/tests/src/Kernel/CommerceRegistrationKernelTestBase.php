<?php

namespace Drupal\Tests\commerce_registration\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\commerce_product\Entity\ProductType;
use Drupal\commerce_product\Entity\ProductVariationType;
use Drupal\commerce_store\Entity\StoreInterface;
use Drupal\commerce_store\StoreCreationTrait;
use Drupal\registration\Entity\RegistrationType;

/**
 * Provides a base class for Commerce Registration kernel tests.
 */
abstract class CommerceRegistrationKernelTestBase extends EntityKernelTestBase implements ServiceModifierInterface {

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
    'commerce',
    'commerce_cart',
    'commerce_checkout',
    'commerce_number_pattern',
    'commerce_order',
    'commerce_price',
    'commerce_product',
    'commerce_registration',
    'commerce_registration_test',
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
    'text',
    'token',
    'views',
    'workflows',
  ];

  /**
   * The store.
   *
   * @var \Drupal\commerce_store\Entity\StoreInterface
   */
  protected StoreInterface $store;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('commerce_store');
    $this->installEntitySchema('commerce_order');
    $this->installEntitySchema('commerce_order_item');
    $this->installEntitySchema('commerce_product');
    $this->installEntitySchema('commerce_product_variation');
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('profile');
    $this->installEntitySchema('registration');
    $this->installEntitySchema('registration_settings');
    $this->installEntitySchema('workflow');

    $this->installConfig('system');
    $this->installConfig('commerce_store');
    $this->installConfig('commerce_product');
    $this->installConfig('commerce_order');
    $this->installConfig('commerce_cart');
    $this->installConfig('profile');
    $this->installConfig('commerce_checkout');
    $this->installConfig('registration');
    $this->installConfig('commerce_registration');

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
  }

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // Set up an override that returns store 1.
    $service_definition = $container->getDefinition('commerce_store.current_store');
    $service_definition->setClass(CurrentStore::class);
  }

}
