<?php

namespace Drupal\registration;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\BundlePermissionHandlerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\registration\Entity\RegistrationType;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides registration type permissions.
 */
class RegistrationPermissionProvider implements ContainerInjectionInterface {

  use BundlePermissionHandlerTrait;
  use StringTranslationTrait;

  /**
   * The configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  /**
   * Constructs a new RegistrationPermissionProvider object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->config = $config_factory->get('registration.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory')
    );
  }

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

    $permissions = [
      "administer $type_id registration" => [
        'title' => $this->t('%type_name: Administer registrations', $type_params),
        'description' => $this->t('View, edit and delete any registrations of this type. Manage registrations and registration settings of this type for all host entities.'),
      ],
      "administer own $type_id registration" => [
        'title' => $this->t('%type_name: Administer own registrations', $type_params),
        'description' => $this->t('View, edit and delete own registrations of this type.'),
      ],
      "administer $type_id registration settings" => [
        'title' => $this->t('%type_name: Administer settings', $type_params),
        'description' => $this->t('Manage registrations and registration settings of this type for all host entities.'),
      ],
      "administer own $type_id registration settings" => [
        'title' => $this->t('%type_name: Administer settings for editable entities', $type_params),
        'description' => $this->t('Manage registrations and registration settings of this type for host entities to which a user has edit access.'),
      ],
      "manage $type_id registration" => [
        'title' => $this->t('%type_name: Manage registrations', $type_params),
        'description' => $this->t('Manage registrations of this type for all host entities.'),
      ],
      "manage own $type_id registration" => [
        'title' => $this->t('%type_name: Manage registrations for editable entities', $type_params),
        'description' => $this->t('Manage registrations of this type for host entities to which a user has edit access.'),
      ],
      "manage $type_id registration settings" => [
        'title' => $this->t('%type_name: Manage registration settings', $type_params),
        'description' => $this->t('Allow changing registration settings for registrations of this type. In a standard installation this must be paired with one of the two "Manage registrations" permissions.'),
      ],
      "manage $type_id registration broadcast" => [
        'title' => $this->t('%type_name: Manage sending registrant email', $type_params),
        'description' => $this->t('Allow sending email to registrants of this type. In a standard installation this must be paired with one of the two "Manage registrations" permissions.'),
      ],
      "edit $type_id registration state" => [
        'title' => $this->t('%type_name: Edit registration state', $type_params),
      ],
      "create $type_id registration self" => [
        'title' => $this->t('%type_name: Register self', $type_params),
      ],
      "create $type_id registration other users" => [
        'title' => $this->t('%type_name: Register other accounts', $type_params),
        'description' => $this->t("Register other users by username. Note that giving this permission to Anonymous is not recommended, as it can expose a user's registration status to a site visitor who knows their username."),
      ],
      "create $type_id registration other anonymous" => [
        'title' => $this->t('%type_name: Register other people', $type_params),
      ],
      "view any $type_id registration" => [
        'title' => $this->t('%type_name: View any registration', $type_params),
      ],
      "view own $type_id registration" => [
        'title' => $this->t('%type_name: View own registrations', $type_params),
      ],
      "update any $type_id registration" => [
        'title' => $this->t('%type_name: Update any registration', $type_params),
      ],
      "update own $type_id registration" => [
        'title' => $this->t('%type_name: Update own registrations', $type_params),
      ],
      "delete any $type_id registration" => [
        'title' => $this->t('%type_name: Delete any registration', $type_params),
      ],
      "delete own $type_id registration" => [
        'title' => $this->t('%type_name: Delete own registrations', $type_params),
      ],
    ];

    if ($this->config->get('limit_field_values')) {
      $permissions["assign $type_id registration field"] = [
        'title' => $this->t('%type_name: Assign this type to host entity registration fields', $type_params),
        'description' => $this->t('The ability to assign this type may also be restricted by the "allowed types" registration field setting.'),
      ];
    }

    return $permissions;
  }

}
