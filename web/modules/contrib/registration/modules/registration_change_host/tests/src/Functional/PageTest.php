<?php

namespace Drupal\Tests\registration_change_host\Functional;

use Drupal\node\Entity\Node;
use Drupal\Tests\registration\Functional\RegistrationBrowserTestBase;
use Drupal\Tests\registration_change_host\Traits\RegistrationChangeHostTrait;

/**
 * Test the /registration/[registration]/host page.
 *
 * @group registration
 * @group registration_change_host
 */
class PageTest extends RegistrationBrowserTestBase {

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
  ];

  /**
   * The change page url for the registration.
   *
   * @var string
   */
  protected $pageUrl;

  /**
   * The change form url for the registration.
   *
   * @var string
   */
  protected $formUrl;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->registrationChangeHostSetUp();
    $this->pageUrl = 'registration/' . $this->registration->id() . '/host';
  }

  /**
   * Tests anonymous access to change registration page.
   */
  public function testAnonymous() {
    $this->drupalLogout();
    $this->drupalGet($this->pageUrl);
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests page is not accessible to users without update permission.
   */
  public function testStaffUserPageWithoutUpdate() {
    $this->drupalLogin($this->staffUserWithoutUpdate);
    $this->drupalGet($this->pageUrl);
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests page shows possible hosts to registrant.
   */
  public function testRegistrantUserPage() {
    // If a possible alternative host exists, show page.
    $hostNode2 = Node::create([
      'type' => 'conference',
      'title' => 'possible available conference',
      'host_possible' => 'always',
    ]);
    $hostNode2->save();
    $this->drupalLogin($this->registrantUser);
    $this->drupalGet($this->pageUrl);
    $expected_url = $this->baseUrl . '/' . $this->pageUrl;
    $this->assertEquals($expected_url, $this->getSession()->getCurrentUrl());
    $this->assertSession()->statusCodeEquals(200);

    // Current and possible hosts should be shown on page.
    $this->assertSession()->pageTextContains('original conference');
    $this->assertSession()->pageTextContains('possible available conference');
  }

  /**
   * Tests different page shows for different registrations.
   */
  public function testCaching() {
    // If a possible alternative host exists, show page.
    $hostNode2 = Node::create([
      'type' => 'conference',
      'title' => 'possible available conference',
      'host_possible' => 'always',
    ]);
    $hostNode2->save();
    $hostNode3 = Node::create([
      'type' => 'conference',
      'title' => 'possible available conference 2',
      'host_possible' => 'always',
    ]);
    $hostNode3->save();

    // The original host node is only possible if you're currently
    // registered for it.
    $this->originalHostNode->set('host_possible', 'never');
    $this->originalHostNode->save();

    $registration2 = $this->createRegistration($hostNode2);
    $registration2->set('user_uid', $this->registrantUser);
    $registration2->save();

    // The first registration should have the original host.
    $this->drupalLogin($this->registrantUser);
    $this->drupalGet('registration/' . $this->registration->id() . '/host');
    $this->assertSession()->pageTextContains('original conference');
    $this->assertSession()->pageTextContains('possible available conference');
    $this->assertSession()->pageTextContains('possible available conference 2');

    // The original host is not possible for the second registration.
    // If it is present, that may indicate the page is wrongly cached.
    $this->drupalGet('registration/' . $registration2->id() . '/host');
    $this->assertSession()->pageTextNotContains('original conference');
    $this->assertSession()->pageTextContains('possible available conference');
    $this->assertSession()->pageTextContains('possible available conference 2');
  }

  /**
   * Tests message if no possible hosts to change to.
   */
  public function testNoHosts() {
    $this->drupalLogin($this->registrantUser);
    $this->drupalGet($this->pageUrl);
    $this->assertSession()->pageTextContains('There is nothing available to change to.');
  }

}
