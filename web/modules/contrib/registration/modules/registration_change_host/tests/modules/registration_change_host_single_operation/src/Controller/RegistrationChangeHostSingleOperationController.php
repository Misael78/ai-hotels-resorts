<?php

namespace Drupal\registration_change_host_single_operation\Controller;

use Drupal\registration\Entity\RegistrationInterface;
use Drupal\registration_change_host\Controller\RegistrationChangeHostController;
use Drupal\registration_change_host\PossibleHostSet;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * The Registration Change Host page.
 */
class RegistrationChangeHostSingleOperationController extends RegistrationChangeHostController {

  /**
   * {@inheritdoc}
   */
  public function changeHostPage(RegistrationInterface $registration): array|RedirectResponse {
    $set = $this->registrationChangeHostManager->getPossibleHosts($registration);

    // Redirect if there are no possible hosts. This is done here, rather than
    // in route access, so that the local task and operation stay visible.
    $current_key = PossibleHostSet::key($registration->getHostEntity());
    $hosts = $set->getHosts();
    unset($hosts[$current_key]);
    if (count($hosts) === 0) {
      // Pass any provided destination on so it can be the final destination.
      $options = [];
      if ($destination = \Drupal::request()->query->get('destination')) {
        $options['query']['destination'] = $destination;
      }
      // Ignore destination on this request or it will take over response.
      \Drupal::service('redirect_response_subscriber')->setIgnoreDestination();
      return $this->redirect('entity.registration.edit_fields_form', ['registration' => $registration->id()], $options);
    }

    // If there are hosts but they are unavailable, show the list
    // as usual for a predictable UX. There's no need to show the message
    // added by the parent class because it's not surprising since the user
    // is not necessarily seeking to change host.
    elseif (!$set->hasAvailableHosts()) {
      \Drupal::messenger()->deleteAll();
    }
    return parent::changeHostPage($registration);
  }

}
