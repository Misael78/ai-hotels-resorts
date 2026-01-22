<?php

namespace Drupal\registration_waitlist\EventSubscriber;

use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\registration\Event\RegistrationEvents;
use Drupal\registration\Event\RegistrationFormEvent;
use Drupal\registration\Event\RegistrationSaveEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides a registration event subscriber.
 */
class RegistrationFormEventSubscriber implements EventSubscriberInterface {

  use MessengerTrait;
  use StringTranslationTrait;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $currentUser;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * Alters a registration form.
   *
   * @param \Drupal\registration\Event\RegistrationFormEvent $event
   *   The registration form event.
   */
  public function alterRegisterForm(RegistrationFormEvent $event) {
    $form = $event->getForm();
    $form_state = $event->getFormState();

    if ($host_entity = $form_state->get('host_entity')) {
      if ($registration = $form_state->get('registration')) {
        $spaces = $registration->getSpacesReserved();
        if ($host_entity->shouldAddToWaitList($spaces, $registration)) {
          $admin = $registration->access('administer', $this->currentUser());

          if ($registration->isNew() || !$admin) {
            // Hide the Status field since the registration will be placed
            // in the wait list state on save.
            if (isset($form['state'])) {
              $form['state']['#access'] = FALSE;
            }

            // Add message indicating the registration will be wait listed,
            // if a message was configured.
            if ($host_entity->getSetting('registration_waitlist_message_enable')) {
              if ($message = $host_entity->getSetting('registration_waitlist_message')) {
                $form['message']['#weight'] = -10;
                $form['message'][] = [
                  '#markup' => '<div class="registration-waitlist-message">' . $message . '</div>',
                ];
              }
            }

            // Save the updated form to the event.
            $event->setForm($form);
          }
        }
      }
    }
  }

  /**
   * Logs a new wait listed registration.
   *
   * @param \Drupal\registration\Event\RegistrationSaveEvent $event
   *   The registration save event.
   */
  public function saveLog(RegistrationSaveEvent $event) {
    $context = $event->getContext();
    if (!$event->wasHandled()) {
      $registration = $context['registration'];
      $host_entity = $context['host_entity'];
      if ($context['is_new'] && ($registration->getState()->id() == 'waitlist')) {
        $event->setHandled(TRUE);
        if ($user = $registration->getUser()) {
          $this->logger()->notice('@name was placed on the wait list for %label (ID #@id).', [
            '@name' => $user->getDisplayName(),
            '%label' => $host_entity->label(),
            '@id' => $registration->id(),
          ]);
        }
        else {
          $this->logger()->notice('@email was placed on the wait list for %label (ID #@id).', [
            '@email' => $registration->getEmail(),
            '%label' => $host_entity->label(),
            '@id' => $registration->id(),
          ]);
        }
      }
    }
  }

  /**
   * Displays a confirmation message for a new wait listed registration.
   *
   * @param \Drupal\registration\Event\RegistrationSaveEvent $event
   *   The registration save event.
   */
  public function saveConfirmation(RegistrationSaveEvent $event) {
    $context = $event->getContext();
    if (!$event->wasHandled()) {
      if ($context['registration']->getState()->id() == 'waitlist') {
        if ($context['is_new']) {
          $event->setHandled(TRUE);
          $this->messenger()->addWarning($this->t('Registration placed on the wait list.'));
        }
        elseif ($context['target_state_id'] != 'waitlist') {
          // An existing registration was saved, and the registration state was
          // altered during save to place the registration on the wait list.
          // Give a warning notice so the editor knows the desired target state
          // was discarded.
          $event->setHandled(TRUE);
          $this->messenger()->addWarning($this->t('Registration placed on the wait list.'));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      RegistrationEvents::REGISTRATION_ALTER_REGISTER_FORM => 'alterRegisterForm',
      RegistrationEvents::REGISTRATION_SAVE_LOG => 'saveLog',
      RegistrationEvents::REGISTRATION_SAVE_CONFIRMATION => 'saveConfirmation',
    ];
  }

  /**
   * Returns the current user.
   *
   * @return \Drupal\Core\Session\AccountInterface
   *   The current user.
   */
  protected function currentUser(): AccountInterface {
    if (!isset($this->currentUser)) {
      $this->currentUser = \Drupal::service('current_user');
    }
    return $this->currentUser;
  }

  /**
   * Retrieves the logger.
   *
   * @return \Psr\Log\LoggerInterface
   *   The logger.
   */
  protected function logger(): LoggerInterface {
    if (!isset($this->logger)) {
      $this->logger = \Drupal::service('registration_waitlist.logger');
    }
    return $this->logger;
  }

}
