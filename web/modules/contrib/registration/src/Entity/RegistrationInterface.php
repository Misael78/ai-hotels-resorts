<?php

namespace Drupal\registration\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\registration\HostEntityInterface;
use Drupal\user\UserInterface;
use Drupal\workflows\StateInterface;
use Drupal\workflows\WorkflowInterface;

/**
 * Defines the interface for registrations.
 */
interface RegistrationInterface extends ContentEntityInterface, EntityChangedInterface {

  /**
   * If user has access to create registrations for their account.
   */
  const REGISTRATION_REGISTRANT_TYPE_ME = 'registration_registrant_type_me';

  /**
   * If user has access to create registrations for other users.
   */
  const REGISTRATION_REGISTRANT_TYPE_USER = 'registration_registrant_type_user';

  /**
   * If user has access to create registrations for people identified by email.
   */
  const REGISTRATION_REGISTRANT_TYPE_ANON = 'registration_registrant_type_anon';

  /**
   * Gets the email address used for an anonymous registration.
   *
   * @return string
   *   The email for an anonymous registration, or a blank string otherwise.
   */
  public function getAnonymousEmail(): string;

  /**
   * Gets the user for the creator of the registration.
   *
   * Returns NULL if the creator was an anonymous registrant.
   *
   * @return \Drupal\user\UserInterface|null
   *   The user entity, if available.
   */
  public function getAuthor(): ?UserInterface;

  /**
   * Gets the display name for the creator of the registration.
   *
   * Returns NULL if the creator was an anonymous registrant.
   *
   * @return string|null
   *   The author name, if available.
   */
  public function getAuthorDisplayName(): ?string;

  /**
   * Gets the registrant email address.
   *
   * If the registrant is an authenticated user, this is the email address
   * currently associated with that user account. Otherwise, this is the
   * email address entered by the anonymous registrant.
   *
   * @return string
   *   The registrant email address.
   */
  public function getEmail(): string;

  /**
   * Gets the host entity.
   *
   * @param string|null $langcode
   *   (optional) The language the host entity should be returned in.
   *
   * @return \Drupal\registration\HostEntityInterface|null
   *   The host entity, if available.
   */
  public function getHostEntity(?string $langcode = NULL): ?HostEntityInterface;

  /**
   * Gets the entity type label of the host entity.
   *
   * If the entity type has bundles, the bundle label is returned instead.
   *
   * @return string|null
   *   The host entity type or bundle label, for example "Event".
   */
  public function getHostEntityTypeLabel(): ?string;

  /**
   * Gets the registrant type relative to the given account.
   *
   * @return string|null
   *   The registrant type as a constant, if available.
   */
  public function getRegistrantType(AccountInterface $account): ?string;

  /**
   * Gets the number of spaces reserved by the registration.
   *
   * @return int
   *   The number of spaces. Defaults to 1 for a new registration.
   */
  public function getSpacesReserved(): int;

  /**
   * Gets the registration type.
   *
   * @return \Drupal\registration\Entity\RegistrationTypeInterface
   *   The registration type.
   */
  public function getType(): RegistrationTypeInterface;

  /**
   * Gets the user if the registration is for a user account.
   *
   * @return \Drupal\user\UserInterface|null
   *   The user entity, or NULL for an anonymous or new registration.
   */
  public function getUser(): ?UserInterface;

  /**
   * Gets the registrant user ID if the registration is for a user account.
   *
   * @return int
   *   The registrant user ID, or 0 for an anonymous or new registration.
   */
  public function getUserId(): int;

  /**
   * Gets the workflow that the registration is in.
   *
   * @return \Drupal\workflows\WorkflowInterface
   *   The workflow.
   */
  public function getWorkflow(): WorkflowInterface;

  /**
   * Gets the registration state.
   *
   * @return \Drupal\workflows\StateInterface
   *   The registration state.
   */
  public function getState(): StateInterface;

  /**
   * Gets the registration completed timestamp.
   *
   * @return int
   *   The registration completed timestamp, if available.
   */
  public function getCompletedTime(): ?int;

  /**
   * Gets the registration creation timestamp.
   *
   * @return int
   *   The registration creation timestamp.
   */
  public function getCreatedTime(): int;

  /**
   * Sets the registration creation timestamp.
   *
   * @param int $timestamp
   *   The registration creation timestamp.
   *
   * @return $this
   */
  public function setCreatedTime(int $timestamp): RegistrationInterface;

  /**
   * Determines whether a registration is in an active state.
   *
   * @return bool
   *   TRUE if the registration is in an active state, FALSE otherwise.
   */
  public function isActive(): bool;

  /**
   * Determines whether a registration is in a canceled state.
   *
   * @return bool
   *   TRUE if the registration is in a canceled state, FALSE otherwise.
   */
  public function isCanceled(): bool;

  /**
   * Determines whether a registration is in the workflow complete state.
   *
   * @return bool
   *   TRUE if the registration is in the workflow state, FALSE otherwise.
   */
  public function isComplete(): bool;

  /**
   * Determines whether a registration is in a held state.
   *
   * @return bool
   *   TRUE if the registration is in a held state, FALSE otherwise.
   */
  public function isHeld(): bool;

  /**
   * Determines whether a registration is new to the host.
   *
   * @return bool
   *   TRUE if the registration is new or the host is changed, FALSE otherwise.
   */
  public function isNewToHost(): bool;

  /**
   * Determines whether a registration requires a capacity check before save.
   *
   * Returns FALSE for canceled registrations. Otherwise returns TRUE for new
   * registrations, and existing registrations changing state or adding spaces.
   *
   * @param bool $checkCanceled
   *   (optional) Whether a canceled registration should be checked.
   *   Defaults to FALSE.
   *
   * @return bool
   *   TRUE if a capacity check is needed, FALSE otherwise.
   */
  public function requiresCapacityCheck(bool $checkCanceled = FALSE): bool;

}
