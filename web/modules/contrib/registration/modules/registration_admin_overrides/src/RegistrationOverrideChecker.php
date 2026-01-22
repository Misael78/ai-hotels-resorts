<?php

namespace Drupal\registration_admin_overrides;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\registration\Entity\RegistrationInterface;
use Drupal\registration\Entity\RegistrationTypeInterface;
use Drupal\registration\HostEntityInterface;

/**
 * Defines the class for the registration override checker service.
 */
class RegistrationOverrideChecker implements RegistrationOverrideCheckerInterface {

  /**
   * The typed config manager.
   *
   * @var \Drupal\Core\Config\TypedConfigManagerInterface
   */
  protected $typedConfigManager;

  /**
   * Constructs a RegistrationOverrideChecker object.
   */
  public function __construct(TypedConfigManagerInterface $typed_config_manager) {
    $this->typedConfigManager = $typed_config_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function accountCanOverride(?HostEntityInterface $host_entity, AccountInterface $account, string $setting, ?RegistrationInterface $registration = NULL, ?CacheableMetadata $cacheable_metadata = NULL): bool {
    $cacheable_metadata = $cacheable_metadata ?? new CacheableMetadata();
    if ($host_entity) {
      if ($registration_type = $host_entity->getRegistrationType()) {
        if ($registration) {
          $admin_result = $registration->access('administer', $account, TRUE);
          $admin = $admin_result->isAllowed();
          $cacheable_metadata->addCacheableDependency($admin_result);
        }
        else {
          $admin = $account->hasPermission("administer registration") || $account->hasPermission("administer {$registration_type->id()} registration");
          $cacheable_metadata->addCacheContexts(['user.permissions']);
        }
        if ($admin) {
          $cacheable_metadata->addCacheContexts(['user.permissions']);
          if ($account->hasPermission('registration override ' . str_replace('_', ' ', $setting))) {
            $cacheable_metadata->addCacheableDependency($registration_type);
            $setting_result = (bool) $registration_type->getThirdPartySetting('registration_admin_overrides', $setting);
            return $setting_result;
          }
        }
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getOverridableSettings(RegistrationTypeInterface $registration_type): array {
    $schema = $this->typedConfigManager->getDefinition('registration.type.*.third_party.registration_admin_overrides');
    $overridable_settings = [];
    foreach ($schema['mapping'] as $key => $definition) {
      $overridable_settings[$key] = $definition['label'];
    }
    return $overridable_settings;
  }

}
