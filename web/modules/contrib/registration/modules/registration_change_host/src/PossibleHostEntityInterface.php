<?php

namespace Drupal\registration_change_host;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\registration\HostEntityInterface;
use Drupal\registration\RegistrationValidationResultInterface;

/**
 * Defines a common interface for possible hosts.
 */
interface PossibleHostEntityInterface extends RefinableCacheableDependencyInterface {

  /**
   * Gets the entity's ID.
   *
   * @return string|int|null
   *   The entity id, if available.
   */
  public function id(): string|int|NULL;

  /**
   * Gets the entity's type.
   *
   * @return string
   *   The entity type.
   */
  public function getEntityTypeId(): string;

  /**
   * Gets the entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity.
   */
  public function getEntity(): EntityInterface;

  /**
   * Gets the host entity.
   *
   * @return \Drupal\registration\HostEntityInterface
   *   The host entity.
   */
  public function getHostEntity(): HostEntityInterface;

  /**
   * Determines whether it is allowed to change to this possible host.
   *
   * @param bool $return_as_object
   *   (optional) Defaults to FALSE.
   *
   * @return bool|\Drupal\registration\RegistrationValidationResultInterface
   *   Returns a boolean if $return_as_object is FALSE (this is the default),
   *   and otherwise a RegistrationValidationResultInterface object. When an
   *   object is returned, it contains any violations that prevent changing
   *   to this possible host.
   */
  public function isAvailable(bool $return_as_object = FALSE): bool|RegistrationValidationResultInterface;

  /**
   * Returns a label for this as a possible host.
   *
   * @return string|null
   *   Possible host label, if available.
   */
  public function label(): ?string;

  /**
   * Sets the title of this as a possible host.
   *
   * @param string|null $label
   *   Title of the possible host.
   *
   * @return $this
   *   The called possible host entity.
   */
  public function setLabel(?string $label = NULL): PossibleHostEntityInterface;

  /**
   * Returns the description for this as a possible host.
   *
   * @return string|null
   *   Possible host description, if available.
   */
  public function getDescription(): ?string;

  /**
   * Sets the description of this as a possible host.
   *
   * @param string|null $description
   *   Description of the possible host.
   *
   * @return $this
   *   The called possible host entity.
   */
  public function setDescription(?string $description = NULL): PossibleHostEntityInterface;

  /**
   * Returns the URL for this as a possible host.
   *
   * No default URL is set, only those specified by subscriber.
   *
   * @return \Drupal\Core\Url|null
   *   Possible host URL, if available.
   */
  public function getUrl(): ?Url;

  /**
   * Sets the URL of this as a possible host.
   *
   * @param \Drupal\Core\Url|null $url
   *   URL of the possible host.
   *
   * @return $this
   *   The called possible host entity.
   */
  public function setUrl($url = NULL): PossibleHostEntityInterface;

  /**
   * Returns whether this is the current host for the registration.
   *
   * @return bool
   *   Whether this possible host is the current host.
   */
  public function isCurrent(): bool;

}
