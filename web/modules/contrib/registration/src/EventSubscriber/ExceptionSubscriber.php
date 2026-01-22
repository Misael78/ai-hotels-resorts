<?php

namespace Drupal\registration\EventSubscriber;

use Drupal\Core\EventSubscriber\HttpExceptionSubscriberBase;
use Drupal\Core\Url;
use Drupal\registration\RegistrationManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

/**
 * Provides an event subscriber to redirect to the user page.
 *
 * This may be necessary if an edit moved a registration from one user to
 * another, and the original user no longer has a registrations task.
 */
class ExceptionSubscriber extends HttpExceptionSubscriberBase {

  /**
   * The registration manager.
   *
   * @var \Drupal\registration\RegistrationManagerInterface
   */
  protected RegistrationManagerInterface $registrationManager;

  /**
   * Constructs an ExceptionSubscriber object.
   *
   * @param \Drupal\registration\RegistrationManagerInterface $registration_manager
   *   The registration manager.
   */
  public function __construct(RegistrationManagerInterface $registration_manager) {
    $this->registrationManager = $registration_manager;
  }

  /**
   * Handle a 403.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   The exception event.
   */
  public function on403(ExceptionEvent $event) {
    $request = $event->getRequest();
    $route_name = $request->attributes->get('_route');
    if ($route_name == 'registration.user_registrations') {
      if ($user = $this->registrationManager->getEntityFromParameters($request->attributes)) {
        $url = Url::fromRoute('entity.user.canonical', ['user' => $user->id()])->toString();
        $response = new RedirectResponse($url);
        $event->setResponse($response);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getHandledFormats(): array {
    return ['html'];
  }

}
