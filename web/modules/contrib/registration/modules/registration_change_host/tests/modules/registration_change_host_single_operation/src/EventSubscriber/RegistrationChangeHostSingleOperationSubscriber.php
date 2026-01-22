<?php

namespace Drupal\registration_change_host_single_operation\EventSubscriber;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\registration_change_host\Event\RegistrationChangeHostEvents;
use Drupal\registration_change_host\Event\RegistrationChangeHostPossibleHostsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides a registration change host single operation event subscriber.
 */
class RegistrationChangeHostSingleOperationSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      RegistrationChangeHostEvents::REGISTRATION_CHANGE_HOST_POSSIBLE_HOSTS => ['addPossibleHosts', -100],
    ];
  }

  /**
   * Set current host as available.
   *
   * @param \Drupal\registration_change_host\Event\RegistrationChangeHostPossibleHostsEvent $event
   *   The registration change host event.
   */
  public function addPossibleHosts(RegistrationChangeHostPossibleHostsEvent $event) {
    $current_host = NULL;
    $set = $event->getPossibleHostsSet();
    foreach ($set->getHosts() as $host) {
      if ($host->isCurrent()) {
        $current_host = $host;
      }
    }
    if (is_null($current_host)) {
      throw new \Exception("Current host is not listed as possible host but single operation module expects it.");
    }
    $current_host->isAvailable(TRUE)->removeViolationWithCode('current_host');
    $current_host->setDescription($this->t('Currently registered.'));
    $current_host->setUrl(Url::fromRoute('entity.registration.edit_fields_form', ['registration' => $set->getRegistration()->id()]));
  }

}
