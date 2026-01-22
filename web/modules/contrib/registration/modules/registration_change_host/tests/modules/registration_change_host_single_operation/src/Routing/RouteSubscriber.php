<?php

namespace Drupal\registration_change_host_single_operation\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Alter the change host route.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  public function alterRoutes(RouteCollection $collection) {
    $change_host_route = $collection->get('entity.registration.change_host');
    $edit_route = $collection->get('entity.registration.edit_form');
    if ($change_host_route && $edit_route) {
      // Place the change host controller on the edit route
      // at the /host path, and adjust its access requirements.
      $change_host_route->setRequirements($edit_route->getRequirements());
      $change_host_route->setDefault('_controller', '\Drupal\registration_change_host_single_operation\Controller\RegistrationChangeHostSingleOperationController::changeHostPage');
      $collection->remove('entity.registration.edit_form');
      $collection->add('entity.registration.edit_form', $change_host_route);

      // Create a new route for the actual edit form, still on /edit.
      $collection->add('entity.registration.edit_fields_form', $edit_route);

      // Block access to the change host route.
      // This hides the local task and operation.
      $collection->remove('entity.registration.change_host');
    }
  }

}
