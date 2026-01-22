<?php

namespace Drupal\Tests\registration_change_host\Kernel;

use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\registration_change_host_single_operation\Controller\RegistrationChangeHostSingleOperationController;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Tests for the RegistrationChangeHostController class.
 *
 * @group registration
 * @group registration_change_host
 */
class SingleOperationTest extends RegistrationChangeHostKernelTestBase {

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
   * Tests routing is modified.
   */
  public function testRouting(): void {
    $edit_form_route_url = Url::fromRoute('entity.registration.edit_form', ['registration' => $this->registration->id()]);
    $this->assertSame('/registration/' . $this->registration->id() . '/host', $edit_form_route_url->toString());

    $edit_fields_form_route_url = Url::fromRoute('entity.registration.edit_fields_form', ['registration' => $this->registration->id()]);
    $this->assertSame('/registration/' . $this->registration->id() . '/edit', $edit_fields_form_route_url->toString());
  }

  /**
   * Test current host is available. A sanity checkup on the test setup.
   */
  public function testCurrentHostAccess(): void {
    $hosts = \Drupal::service('registration_change_host.manager')->getPossibleHosts($this->registration);
    $current = $hosts->getHost($this->originalHostNode);
    $this->assertTrue($current->isAvailable());
  }

  /**
   * Tests page redirects when appropriate.
   */
  public function testPageRedirect(): void {
    $this->drupalSetCurrentUser($this->registrantUser);

    $page_controller = RegistrationChangeHostSingleOperationController::create($this->container);
    // Page redirects to edit current host if there are no possible hosts.
    $page = $page_controller->changeHostPage($this->registration);
    $this->assertInstanceOf(RedirectResponse::class, $page);
    $this->assertStringContainsString('/registration/' . $this->registration->id() . '/edit', $page->getTargetUrl());

    $hostNode2 = Node::create([
      'type' => 'conference',
      'title' => 'possible conference',
      'host_possible' => 'always',
    ]);
    $hostNode2->save();
    \Drupal::entityTypeManager()->getHandler('registration', 'access')->resetCache();

    // Now there is a possible host, the page is shown.
    $page = $page_controller->changeHostPage($this->registration, $this->registrantUser);
    $this->assertIsArray($page);
  }

}
