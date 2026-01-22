<?php

namespace Drupal\Tests\registration_change_host\Functional;

use Drupal\node\Entity\Node;
use Drupal\Tests\registration\Functional\RegistrationBrowserTestBase;
use Drupal\Tests\registration_change_host\Traits\RegistrationChangeHostTrait;

/**
 * Test the Registration Change Host Single Operation module.
 *
 * @group registration
 * @group registration_change_host
 */
class SingleOperationTest extends RegistrationBrowserTestBase {

  use RegistrationChangeHostTrait;

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
    'registration_change_host_single_operation',
  ];

  /**
   * The edit url for the registration.
   *
   * @var string
   */
  protected $editRouteUrl;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->registrationChangeHostSetUp();
    $this->editRouteUrl = '/registration/' . $this->registration->id() . '/host';
  }

  /**
   * Test current host is available.
   *
   * This is repeated from the kernel test, as a sanity check.
   */
  public function testCurrentHostAccess(): void {
    $hosts = \Drupal::service('registration_change_host.manager')->getPossibleHosts($this->registration);
    $current = $hosts->getHost($this->originalHostNode);
    $this->assertCount(0, $current->isAvailable(TRUE)->getViolations());
    $this->assertTrue($current->isAvailable());
  }

  /**
   * Tests page still shows possible hosts if available.
   */
  public function testRegistrantUserPage(): void {
    $hostNode2 = Node::create([
      'type' => 'conference',
      'title' => 'possible available conference',
      'host_possible' => 'always',
    ]);
    $hostNode2->save();
    $this->drupalLogin($this->registrantUser);

    // Select a new host.
    $this->drupalGet($this->editRouteUrl);
    $this->assertSession()->statusCodeEquals(200);
    $expected_url = $this->baseUrl . $this->editRouteUrl;
    $this->assertEquals($expected_url, $this->getSession()->getCurrentUrl());
    $this->assertSession()->pageTextContains('original conference');
    $this->assertSession()->pageTextContains('possible available conference');
    $this->getSession()->getPage()->find('named', ['link_or_button', 'possible available conference'])->click();
    $this->assertSession()->statusCodeEquals(200);
    $expected_url = $this->baseUrl . '/registration/' . $this->registration->id() . '/update/' . $hostNode2->id() . '/node';
    $this->assertEquals($expected_url, $this->getSession()->getCurrentUrl());
    $this->assertSession()->pageTextContains('Confirm change of Conference');

    // Select the current host.
    $this->drupalGet($this->editRouteUrl);
    $this->assertSession()->statusCodeEquals(200);
    $expected_url = $this->baseUrl . $this->editRouteUrl;
    $this->assertEquals($expected_url, $this->getSession()->getCurrentUrl());
    $this->getSession()->getPage()->find('named', ['link_or_button', 'original conference Currently registered'])->click();
    $this->assertSession()->statusCodeEquals(200);
    $expected_url = $this->baseUrl . '/registration/' . $this->registration->id() . '/edit';
    $this->assertEquals($expected_url, $this->getSession()->getCurrentUrl());
    $this->assertSession()->pageTextContains('Edit Registration');
  }

  /**
   * Tests page is bypassed if no possible hosts.
   */
  public function testRedirect(): void {
    $this->drupalLogin($this->registrantUser);
    $this->drupalGet($this->editRouteUrl);
    $this->assertSession()->statusCodeEquals(200);
    $expected_url = "{$this->baseUrl}/registration/{$this->registration->id()}/edit";
    $this->assertEquals($expected_url, $this->getSession()->getCurrentUrl());
    $this->assertSession()->pageTextContains('Edit Registration #' . $this->registration->id());
  }

  /**
   * Tests redirect works even if destination is set.
   */
  public function testRedirectDespiteDestination(): void {
    $this->drupalLogin($this->registrantUser);
    $this->drupalGet($this->editRouteUrl, ['query' => ['destination' => '/node/' . $this->originalHostNode->id()]]);
    $this->assertSession()->statusCodeEquals(200);
    $expected_url = "{$this->baseUrl}/registration/{$this->registration->id()}/edit?destination=/node/{$this->originalHostNode->id()}";
    $this->assertEquals($expected_url, $this->getSession()->getCurrentUrl());
    $this->assertSession()->pageTextContains('Edit Registration #' . $this->registration->id());
  }

}
