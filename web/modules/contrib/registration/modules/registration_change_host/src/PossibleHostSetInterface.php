<?php

namespace Drupal\registration_change_host;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\registration\Entity\RegistrationInterface;
use Drupal\registration\HostEntityInterface;

/**
 * Defines a common interface for possible hosts.
 */
interface PossibleHostSetInterface extends RefinableCacheableDependencyInterface {

  /**
   * Adds a candidate host to the set.
   *
   * If the host is already present, update it.
   *
   * When adding a host, also set cacheability metadata on the host set to
   * specify the cacheability of the host's presence in the set.
   *
   * @var \Drupal\registration_change_host\PossibleHostEntityInterface $host
   *   The host.
   */
  public function addHost(PossibleHostEntityInterface $host): void;

  /**
   * Adds a candidate host to the set, if available.
   *
   * If the host is already present, update it. Unlike ::addHost() this adds
   * the host only if it is available for registration.
   *
   * When adding a host, also set cacheability metadata on the host set to
   * specify the cacheability of the host's presence in the set.
   *
   * @var \Drupal\registration_change_host\PossibleHostEntityInterface $host
   *   The host.
   */
  public function addHostIfAvailable(PossibleHostEntityInterface $host): void;

  /**
   * Builds a new possible host entity.
   *
   * This is a convenience method for event subscribers.
   *
   * @param \Drupal\registration\HostEntityInterface|\Drupal\Core\Entity\EntityInterface $host
   *   The host or its underlying entity.
   *
   * @return \Drupal\registration_change_host\PossibleHostEntityInterface
   *   A possible host entity object.
   */
  public function buildNewPossibleHost(HostEntityInterface|EntityInterface $host): PossibleHostEntityInterface;

  /**
   * Gets the existing registration being changed.
   */
  public function getRegistration(): RegistrationInterface;

  /**
   * Gets a specific host from the set.
   *
   * @param \Drupal\registration_change_host\PossibleHostEntityInterface|\Drupal\registration\HostEntityInterface|\Drupal\Core\Entity\EntityInterface|string|int $host
   *   The host or its id.
   * @param string $type_id
   *   (optional) The host entity type id if id passed as $host.
   *
   * @return \Drupal\registration_change_host\PossibleHostEntityInterface|null
   *   The host or null if not found.
   */
  public function getHost($host, $type_id = NULL): ?PossibleHostEntityInterface;

  /**
   * Gets possible hosts the registration could be changed to.
   *
   * Returns an associative array of possible hosts where the key is type:id
   * and the values are PossibleHostEntity entities.
   *
   * @return PossibleHostEntityInterface[]
   *   The possible hosts.
   */
  public function getHosts(): array;

  /**
   * Establishes whether there are possible and available hosts in this set.
   *
   * @return bool
   *   Whether a non-current possible available host is available.
   */
  public function hasAvailableHosts(): bool;

  /**
   * Removes a host from the set.
   *
   * @param \Drupal\registration_change_host\PossibleHostEntityInterface|\Drupal\registration\HostEntityInterface|\Drupal\Core\Entity\EntityInterface|string|int $host
   *   The host or its id.
   * @param string $type_id
   *   (optional) The host entity type id if id passed as $host.
   */
  public function removeHost($host, $type_id = NULL): void;

  /**
   * Specify the complete set of candidate host entities.
   *
   * @param array \Drupal\registration_change_host\PossibleHostEntityInterface[] $hosts
   *   An array of possible host entities.
   */
  public function setHosts(array $hosts): void;

  /**
   * Get a possible host key for a host.
   *
   * @param \Drupal\registration_change_host\PossibleHostEntityInterface|\Drupal\registration\HostEntityInterface|\Drupal\Core\Entity\EntityInterface|string|int $host
   *   The host or its id.
   * @param string $type_id
   *   (optional) The host entity type id if id passed as $host.
   *
   * @return string
   *   The possible host key.
   */
  public static function key($host, $type_id = NULL): string;

}
