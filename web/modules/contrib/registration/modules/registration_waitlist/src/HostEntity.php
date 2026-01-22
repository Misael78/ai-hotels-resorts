<?php

namespace Drupal\registration_waitlist;

use Drupal\Core\Database\Database;
use Drupal\Core\Session\AccountInterface;
use Drupal\registration\Entity\RegistrationInterface;
use Drupal\registration\Event\RegistrationDataAlterEvent;
use Drupal\registration\Event\RegistrationEvents;
use Drupal\registration\HostEntity as BaseHostEntity;

/**
 * Extends the class for the host entity.
 */
class HostEntity extends BaseHostEntity implements HostEntityInterface {

  /**
   * {@inheritdoc}
   */
  public function getWaitListSpacesRemaining(?RegistrationInterface $registration = NULL): ?int {
    if ($this->isWaitListEnabled()) {
      if ($capacity = $this->getSetting('registration_waitlist_capacity')) {
        // Allow other modules to alter the number of spaces remaining.
        $spaces_remaining = $capacity - $this->getWaitListSpacesReserved($registration);
        $event = new RegistrationDataAlterEvent($spaces_remaining, [
          'host_entity' => $this,
          'settings' => $this->getSettings(),
          'registration' => $registration,
          'waitlist' => TRUE,
        ]);
        $this->eventDispatcher()->dispatch($event, RegistrationEvents::REGISTRATION_ALTER_SPACES_REMAINING);
        return $event->getData() ?? NULL;
      }
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getWaitListSpacesReserved(?RegistrationInterface $registration = NULL): int {
    $database = Database::getConnection();
    $query = $database->select('registration')
      ->condition('entity_id', $this->id())
      ->condition('entity_type_id', $this->getEntityTypeId())
      ->condition('state', 'waitlist');

    if ($registration && !$registration->isNew()) {
      $query->condition('registration_id', $registration->id(), '<>');
    }

    $query->addExpression('sum(count)', 'spaces');

    $spaces = $query->execute()->fetchField();
    $spaces = empty($spaces) ? 0 : $spaces;

    // Allow other modules to alter the number of spaces reserved.
    $event = new RegistrationDataAlterEvent($spaces, [
      'host_entity' => $this,
      'settings' => $this->getSettings(),
      'registration' => $registration,
      'states' => ['waitlist'],
    ]);
    $this->eventDispatcher()->dispatch($event, RegistrationEvents::REGISTRATION_ALTER_USAGE);
    return $event->getData() ?? 0;
  }

  /**
   * {@inheritdoc}
   */
  public function hasRoom(int $spaces = 1, ?RegistrationInterface $registration = NULL): bool {
    if ($this->isWaitListEnabled()) {
      return $this->hasRoomOffWaitList($spaces, $registration) || $this->hasRoomOnWaitList($spaces, $registration);
    }
    return parent::hasRoom($spaces, $registration);
  }

  /**
   * {@inheritdoc}
   */
  public function hasRoomOffWaitList(int $spaces = 1, ?RegistrationInterface $registration = NULL): bool {
    return parent::hasRoom($spaces, $registration);
  }

  /**
   * {@inheritdoc}
   */
  public function hasRoomOnWaitList(int $spaces = 1, ?RegistrationInterface $registration = NULL): bool {
    if ($this->isWaitListEnabled()) {
      $capacity = $this->getSetting('registration_waitlist_capacity');
      if ($capacity) {
        $projected_usage = $this->getWaitListSpacesReserved($registration) + $spaces;
        if (($capacity - $projected_usage) < 0) {
          // Wait list is full.
          return FALSE;
        }
      }
      // Wait list has room.
      return TRUE;
    }
    // Wait list is not enabled.
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmailRegistered(string $email): bool {
    @trigger_error('Calling HostEntity::isEmailRegistered() is deprecated in registration:3.1.5 and will be removed before registration:4.0.0. See https://www.drupal.org/node/3465690', E_USER_DEPRECATED);
    $states = [];

    if ($registration_type = $this->getRegistrationType()) {
      $states = $registration_type->getActiveOrHeldStates();
    }

    $states['waitlist'] = 'waitlist';

    $database = Database::getConnection();
    $query = $database->select('registration')
      ->condition('entity_id', $this->id())
      ->condition('entity_type_id', $this->getEntityTypeId())
      ->condition('anon_mail', $email)
      ->condition('state', array_keys($states), 'IN');

    $count = $query->countQuery()->execute()->fetchField();
    return ($count > 0);
  }

  /**
   * {@inheritdoc}
   */
  public function isUserRegistered(AccountInterface $account): bool {
    @trigger_error('Calling HostEntity::isUserRegistered() is deprecated in registration:3.1.5 and will be removed before registration:4.0.0. See https://www.drupal.org/node/3465690', E_USER_DEPRECATED);
    $states = [];

    if ($registration_type = $this->getRegistrationType()) {
      $states = $registration_type->getActiveOrHeldStates();
    }

    $states['waitlist'] = 'waitlist';

    $database = Database::getConnection();
    $query = $database->select('registration')
      ->condition('entity_id', $this->id())
      ->condition('entity_type_id', $this->getEntityTypeId())
      ->condition('user_uid', $account->id())
      ->condition('state', array_keys($states), 'IN');

    $count = $query->countQuery()->execute()->fetchField();
    return ($count > 0);
  }

  /**
   * {@inheritdoc}
   */
  public function isRegistrant(?AccountInterface $account = NULL, $email = NULL, array $states = []): bool {
    if (!$states) {
      if ($registration_type = $this->getRegistrationType()) {
        $states = array_keys($registration_type->getActiveOrHeldStates());
      }
      $states['waitlist'] = 'waitlist';
    }
    return parent::isRegistrant($account, $email, $states);
  }

  /**
   * {@inheritdoc}
   */
  public function isWaitListEnabled(): bool {
    return (bool) $this->getSetting('registration_waitlist_enable');
  }

  /**
   * {@inheritdoc}
   */
  public function shouldAddToWaitList(int $spaces = 1, ?RegistrationInterface $registration = NULL): bool {
    return !$this->hasRoomOffWaitList($spaces, $registration) && $this->isWaitListEnabled() && $this->hasRoomOnWaitList($spaces, $registration);
  }

}
