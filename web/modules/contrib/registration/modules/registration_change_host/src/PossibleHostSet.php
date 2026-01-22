<?php

namespace Drupal\registration_change_host;

use Drupal\Core\Cache\RefinableCacheableDependencyTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\registration\HostEntityInterface;
use Drupal\registration\Entity\RegistrationInterface;

/**
 * Defines a set of possible hosts for a registration.
 *
 * Not all possible hosts are available to every user. Hosts may be present
 * in the set because they should be displayed to the user, but are made
 * unavailable if the user cannot actually change to them; for example, if
 * the host has no more capacity it can be good to acknowledge this rather than
 * hiding the host altogether and leaving the user uncertain.
 *
 * The cacheability metadata of the event specifies the cacheability of the
 * list of possible hosts. It is only concerned with the presence or absence
 * of individual entities in the list, not the cacheability of any information
 * displayed about them.
 *
 * @see \Drupal\registration_change_host\Event\RegistrationChangeHostEvent
 * @see \Drupal\registration_change_host\RegistrationChangeHostManager
 */
class PossibleHostSet implements PossibleHostSetInterface {

  use RefinableCacheableDependencyTrait;
  use StringTranslationTrait;

  /**
   * The registration.
   */
  protected RegistrationInterface $registration;

  /**
   * The possible hosts for the the registration.
   */
  protected array $hosts = [];

  /**
   * Constructs a new RegistrationEvent.
   *
   * @param \Drupal\registration\Entity\RegistrationInterface $registration
   *   The registration.
   */
  public function __construct(RegistrationInterface $registration) {
    $this->registration = $registration;
    $current_host = $this->buildNewPossibleHost($this->registration->getHostEntity());
    $this->addHost($current_host);
    // Any change to the registration may be a change of the host, which
    // would change the current host for the set.
    $this->addCacheableDependency($registration);
  }

  /**
   * {@inheritdoc}
   */
  public function addHost(PossibleHostEntityInterface $host): void {
    $this->hosts[static::key($host)] = $host;
  }

  /**
   * {@inheritdoc}
   */
  public function addHostIfAvailable(PossibleHostEntityInterface $host): void {
    $result = $host->isAvailable(TRUE);
    $this->addCacheableDependency($result);
    if ($result->isValid()) {
      $this->addHost($host);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildNewPossibleHost(HostEntityInterface|EntityInterface $host): PossibleHostEntityInterface {
    $possible_host = new PossibleHostEntity($host, $this->getRegistration());
    return $possible_host;
  }

  /**
   * {@inheritdoc}
   */
  public function getHost($host, $type_id = NULL): ?PossibleHostEntityInterface {
    $key = static::key($host, $type_id);
    if (isset($this->hosts[$key])) {
      return $this->hosts[$key];
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getHosts(): array {
    return $this->hosts;
  }

  /**
   * {@inheritdoc}
   */
  public function getRegistration(): RegistrationInterface {
    return $this->registration;
  }

  /**
   * {@inheritdoc}
   */
  public function removeHost($host, $type_id = NULL): void {
    $key = static::key($host, $type_id);
    if (isset($this->hosts[$key])) {
      unset($this->hosts[$key]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function hasAvailableHosts(): bool {
    $hosts = $this->getHosts();

    foreach ($hosts as $host) {
      if (!$host->isCurrent() && $host->isAvailable()) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function setHosts(array $hosts): void {
    $this->hosts = [];
    foreach ($hosts as $host) {
      $this->addHost($host);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function key($host, $type_id = NULL): string {
    if ($host instanceof PossibleHostEntityInterface) {
      $host = $host->getHostEntity();
    }
    if ($host instanceof HostEntityInterface) {
      $host = $host->getEntity();
    }
    if ($host instanceof EntityInterface) {
      $type_id = $host->getEntityTypeId();
      $host = $host->id();
    }
    elseif (is_object($host)) {
      throw new \InvalidArgumentException("Host must be an instance of PossibleHostEntityInterface, HostEntityInterface or EntityInterface.");
    }
    return "{$type_id}:{$host}";
  }

  /**
   * {@inheritdoc}
   */
  public function __sleep() {
    if (!empty($this->registration)) {
      // @phpstan-ignore-next-line
      $this->_registrationId = $this->registration->id();
      unset($this->registration);
    }
    return ['hosts', '_registrationId'];
  }

  /**
   * {@inheritdoc}
   */
  public function __wakeup() {
    if (!empty($this->_registrationId)) {
      $registration_storage = \Drupal::entityTypeManager()->getStorage('registration');
      $this->registration = $registration_storage->load($this->_registrationId);
      unset($this->_registrationId);
    }
  }

}
