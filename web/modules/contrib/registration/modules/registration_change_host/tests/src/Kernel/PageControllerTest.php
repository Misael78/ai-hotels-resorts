<?php

namespace Drupal\Tests\registration_change_host\Kernel;

use Drupal\node\Entity\Node;
use Drupal\registration_change_host\Controller\RegistrationChangeHostController;

/**
 * Tests for the RegistrationChangeHostController class.
 *
 * @group registration
 * @group registration_change_host
 */
class PageControllerTest extends RegistrationChangeHostKernelTestBase {

  /**
   * Tests page title.
   */
  public function testTitle() {
    $page_controller = RegistrationChangeHostController::create($this->container);
    $title = $page_controller->title($this->registration);
    $this->assertEquals('Select Conference', $title);
  }

  /**
   * Tests page.
   */
  public function testPage() {
    $hostNode2 = Node::create([
      'type' => 'conference',
      'title' => 'possible conference',
      'host_possible' => 'always',
    ]);
    $hostNode2->save();
    \Drupal::entityTypeManager()->getHandler('registration', 'access')->resetCache();

    // Log in the user.
    $this->setCurrentUser($this->registrantUser);

    $page_controller = RegistrationChangeHostController::create($this->container);
    $build = $page_controller->changeHostPage($this->registration);
    $this->assertIsArray($build);
    $this->assertContains('user.node_grants:registration_change_host_test', $build['#cache']['contexts']);
  }

  /**
   * Tests theme.
   */
  public function testTheme() {
    $set = \Drupal::service('registration_change_host.manager')->getPossibleHosts($this->registration);
    $variables = ['set' => $set];
    template_preprocess_registration_change_host_list($variables);
    $this->assertCount(count($set->getHosts()), $variables['hosts']);
  }

}
