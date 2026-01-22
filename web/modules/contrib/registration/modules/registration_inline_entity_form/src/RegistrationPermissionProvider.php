<?php

namespace Drupal\registration_inline_entity_form;

use Drupal\Core\Entity\BundlePermissionHandlerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\registration\Entity\RegistrationType;

/**
 * Provides registration permissions for the inline entity form module.
 */
class RegistrationPermissionProvider {

  use BundlePermissionHandlerTrait;
  use StringTranslationTrait;

  /**
   * Returns an array of registration type permissions.
   *
   * @return array
   *   The registration type permissions.
   *   @see \Drupal\user\PermissionHandlerInterface::getPermissions()
   */
  public function buildPermissions(): array {
    return $this->generatePermissions(RegistrationType::loadMultiple(), [
      $this, 'buildBundlePermissions',
    ]);
  }

  /**
   * Returns a list of registration permissions for a given registration type.
   *
   * @param \Drupal\registration\Entity\RegistrationType $type
   *   The registration type.
   *
   * @return array
   *   An associative array of permission names and descriptions.
   */
  protected function buildBundlePermissions(RegistrationType $type): array {
    $type_id = $type->id();
    $type_params = ['%type_name' => $type->label()];

    return [
      "edit $type_id registration settings" => [
        'title' => $this->t('%type_name: Edit registration settings', $type_params),
        'description' => $this->t('Edit registration settings for existing host entities with this registration type set.'),
      ],
    ];
  }

}
