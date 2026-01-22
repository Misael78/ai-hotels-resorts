<?php

namespace Drupal\Tests\registration_change_host\Kernel;

use Drupal\Core\Routing\RouteMatch;
use Drupal\node\Entity\Node;
use Drupal\registration_change_host\Form\ChangeHostForm;
use Symfony\Component\Routing\Route;

/**
 * Tests the ChangeHostForm access method.
 *
 * @group registration
 * @group registration_change_host
 */
class FormTest extends RegistrationChangeHostKernelTestBase {

  /**
   * The form.
   *
   * @var \Drupal\registration_change_host\Form\ChangeHostForm
   */
  protected $form;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->form = ChangeHostForm::create($this->container);
    $this->setCurrentUser($this->registrantUser);

    // This host is definitely possible and available, to ensure there is
    // at least one in the set.
    $hostNode2 = Node::create([
      'type' => 'conference',
      'title' => $this->randomMachineName(),
      'host_possible' => 'always',
      'host_violation' => 'NONE',
    ]);
    $hostNode2->save();
  }

  /**
   * Data provider for testAccess.
   */
  public function accessDataProvider() {
    return [
      ['always', TRUE, TRUE],
      ['never', TRUE, FALSE],
      ['always', FALSE, FALSE],
      ['never', FALSE, FALSE],
    ];
  }

  /**
   * Tests the access method of ChangeHostForm.
   *
   * @dataProvider accessDataProvider
   */
  public function testAccess($possible, $access, $expected) {
    $new_host = Node::create([
      'type' => 'conference',
      'title' => $this->randomMachineName(),
      'host_possible' => $possible,
      'host_violation' => $access ? 'NONE' : 'some_cause',
    ]);
    $new_host->save();

    $access_result = $this->form->access($this->getRouteMatch('node', $new_host->id()), $this->registration);
    $this->assertSame($expected, $access_result->isAllowed());
  }

  /**
   * Tests entity type access when not configured.
   */
  public function testNotConfiguredEntityTypeAccess() {
    $access_result = $this->form->access($this->getRouteMatch('user', 1), $this->registration);
    $this->assertFalse($access_result->isAllowed());
  }

  /**
   * Tests access to the current host.
   *
   * The current host is never an appropriate host for the ChangeHostForm.
   */
  public function testCurrentHostAccess() {
    // Ensure the current host is not simply suppressed by a violation.
    $this->originalHostNode->set('host_violation', 'NONE')->save();
    $access_result = $this->form->access($this->getRouteMatch('node', $this->originalHostNode->id()), $this->registration);
    $this->assertFalse($access_result->isAllowed());
  }

  /**
   * Get a route match for testing.
   *
   * @param string $host_type_id
   *   The entity type id of the host.
   * @param int $host_id
   *   The id of the host.
   */
  protected function getRouteMatch($host_type_id, $host_id): RouteMatch {
    $route = new Route('/registration/{registration}/update/{host_id}/{host_type_id}');
    return new RouteMatch(
      'registration_change_host.change_host_form',
      $route,
      [],
      ['host_type_id' => $host_type_id, 'host_id' => $host_id]
    );
  }

}
