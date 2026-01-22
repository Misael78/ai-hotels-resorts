<?php

namespace Drupal\Tests\registration_change_host\Functional;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\Tests\registration\Functional\RegistrationBrowserTestBase;
use Drupal\Tests\registration_change_host\Traits\RegistrationChangeHostTrait;

/**
 * Test the registration single step change host form.
 *
 * @group registration
 * @group registration_change_host
 */
class SingleStepFormTest extends RegistrationBrowserTestBase {

  use RegistrationChangeHostTrait;

  /**
   * The configuration factory.
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'node',
    'user',
    'registration_test',
    'registration_change_host',
    'registration_change_host_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->configFactory = $this->container->get('config.factory');

    $config = $this->configFactory->getEditable('registration_change_host.settings');
    $config->set('workflow', 'single_step');
    $config->save();

    $this->registrationChangeHostSetUp();
  }

  /**
   * Helper to get registration URL.
   */
  protected function getFormUrl(?NodeInterface $node = NULL) {
    $url = 'registration/' . $this->registration->id() . '/host';
    return $url;
  }

  /**
   * Tests anonymous access to change host form.
   */
  public function testAnonymous() {
    $possibleHost = Node::create([
      'type' => 'conference',
      'title' => 'possible available conference',
      'host_possible' => 'always',
      'host_violation' => 'NONE',
    ]);
    $possibleHost->save();
    $this->drupalLogout();
    $this->drupalGet($this->getFormUrl($possibleHost));
    $expected_url = $this->baseUrl . '/' . $this->getFormUrl($possibleHost);
    $this->assertEquals($expected_url, $this->getSession()->getCurrentUrl());
    $this->assertSession()->statusCodeEquals(403);
    $this->assertRegistrationHost($this->originalHostNode);
  }

  /**
   * Tests registrant change host but same type.
   */
  public function testRegistrantUserNewHostSameType() {
    $possibleHost = Node::create([
      'type' => 'conference',
      'title' => 'possible available conference',
      'host_possible' => 'always',
      'host_violation' => 'NONE',
    ]);
    $possibleHost->save();
    $this->assertTrue($possibleHost->bundle() === $this->originalHostNode->bundle());
    $this->drupalLogin($this->registrantUser);
    $this->updateRegistrationByForm($possibleHost);
  }

  /**
   * Tests registrant change host to same type without update permission.
   */
  public function testStaffUserNewHostSameTypeWithoutUpdate() {
    $possibleHost = Node::create([
      'type' => 'conference',
      'title' => 'possible available conference',
      'host_possible' => 'always',
      'host_violation' => 'NONE',
    ]);
    $possibleHost->save();
    $this->assertTrue($possibleHost->bundle() === $this->originalHostNode->bundle());
    $this->drupalLogin($this->staffUserWithoutUpdate);
    $this->updateRegistrationByForm($possibleHost);
  }

  /**
   * Tests registrant change host to different type.
   */
  public function testRegistrantUserNewHostDifferentType() {
    $possibleHost = Node::create([
      'type' => 'event',
      'title' => 'possible available event',
      'host_possible' => 'always',
      'host_violation' => 'NONE',
    ]);
    $possibleHost->save();
    $this->assertTrue($possibleHost->bundle() !== $this->originalHostNode->bundle());
    $this->drupalLogin($this->registrantUser);
    $this->updateRegistrationByForm($possibleHost, TRUE);
  }

  /**
   * Tests registrant change host to different type.
   */
  public function testStaffUserNewHostDifferentType() {
    $possibleHost = Node::create([
      'type' => 'event',
      'title' => 'possible available event',
      'host_possible' => 'always',
      'host_violation' => 'NONE',
    ]);
    $possibleHost->save();
    $this->assertTrue($possibleHost->bundle() !== $this->originalHostNode->bundle());
    $this->drupalLogin($this->staffUser);
    $this->updateRegistrationByForm($possibleHost, TRUE);
  }

  /**
   * Submit the form and ensure the registration saves properly.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The host.
   * @param bool $error
   *   (Optional) Whether an error is expected.
   *   TRUE if an error should be expected, FALSE otherwise.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  protected function updateRegistrationByForm(NodeInterface $node, bool $error = FALSE) {
    $this->drupalGet($this->getFormUrl($node));
    $expected_url = $this->baseUrl . '/' . $this->getFormUrl($node);
    $this->assertEquals($expected_url, $this->getSession()->getCurrentUrl());
    $this->assertSession()->statusCodeEquals(200);

    // Select the new host.
    $field = 'new_host';
    $this->assertSession()->fieldExists($field);
    $this->getSession()->getPage()->fillField($field, $node->getEntityTypeId() . ':' . $node->id());

    // Save and check the host updated.
    $this->getSession()->getPage()->pressButton("Save Registration");

    // Check for error.
    if ($error) {
      $this->assertSession()->pageTextContains('The selection has an incompatible registration type.');
      $this->assertSession()->pageTextNotContains('The registration was saved.');
      return;
    }

    // Save and check the host updated.
    $this->assertSession()->pageTextNotContains('The selection has an incompatible registration type.');
    $this->assertSession()->pageTextContains('The registration was saved.');
    $this->assertRegistrationHost($node);

    // Make sure the bundle-specific field value saved.
    $registration = $this->reloadEntity($this->registration);

    // We use the same names for registration and
    // corresponding host node types for convenience.
    $this->assertSame($registration->bundle(), $node->bundle(), "Registration and host should have same bundle name.");
  }

  /**
   * Assert a registration has a particular host.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The host.
   */
  protected function assertRegistrationHost(NodeInterface $node) {
    $registration = $this->reloadEntity($this->registration);
    $this->assertSame('node', $registration->getHostEntityTypeId(), "The registration host should be a node");
    $this->assertEquals($node->id(), $registration->getHostEntityId(), "The host should have id " . $node->id());
  }

  /**
   * Reload an entity from the database.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity to reload.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   Reloaded entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function reloadEntity(EntityInterface $entity) {
    $type_id = $entity->getEntityTypeId();
    $storage = $this->entityTypeManager->getStorage($type_id);
    $storage->resetCache([$entity->id()]);
    $entity = $storage->load($entity->id());
    $this->assertInstanceOf(EntityInterface::class, $entity, "The $type_id should exist.");
    return $entity;
  }

}
