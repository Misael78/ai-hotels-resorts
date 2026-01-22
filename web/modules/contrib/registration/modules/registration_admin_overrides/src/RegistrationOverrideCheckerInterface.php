<?php

namespace Drupal\registration_admin_overrides;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Session\AccountInterface;
use Drupal\registration\Entity\RegistrationInterface;
use Drupal\registration\Entity\RegistrationTypeInterface;
use Drupal\registration\HostEntityInterface;

/**
 * Defines the interface for the registration override checker service.
 */
interface RegistrationOverrideCheckerInterface {

  /**
   * Determines if the specified account can override a registration setting.
   *
   * If a cacheable metadata object is provided, modifying it should describe
   * the cacheability of the returned value.
   *
   * @param \Drupal\registration\HostEntityInterface|null $host_entity
   *   The host entity, if available.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   * @param string $setting
   *   The name of the registration setting, for example 'capacity'.
   * @param \Drupal\registration\RegistrationInterface|null $registration
   *   (optional) The registration entity.
   * @param \Drupal\Core\Cache\CacheableMetadata|null $cacheable_metadata
   *   (optional) The cacheable metadata.
   *
   * @return bool
   *   TRUE if the account can override the setting, FALSE otherwise.
   */
  public function accountCanOverride(?HostEntityInterface $host_entity, AccountInterface $account, string $setting, ?RegistrationInterface $registration = NULL, ?CacheableMetadata $cacheable_metadata = NULL): bool;

  /**
   * Gets the settings that can be overridden for a registration type.
   *
   * @param \Drupal\registration\Entity\RegistrationTypeInterface $registration_type
   *   The registration type.
   *
   * @return array
   *   The settings that can be overridden.
   *   The keys are setting machine names, the values are descriptions.
   */
  public function getOverridableSettings(RegistrationTypeInterface $registration_type): array;

}
