<?php

namespace Drupal\registration;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityHandlerBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Site\Settings;

/**
 * Defines a default implementation for a host access control handler.
 */
class RegistrationHostAccessControlHandler extends EntityHandlerBase implements RegistrationHostAccessControlHandlerInterface {

  /**
   * Stores calculated access check results.
   *
   * @var array
   */
  protected $accessCache = [];

  /**
   * The entity type ID of the access control handler instance.
   *
   * @var string
   */
  protected $entityTypeId;

  /**
   * Information about the entity type.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface
   */
  protected $entityType;

  /**
   * Constructs an access control handler instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   */
  public function __construct(EntityTypeInterface $entity_type) {
    $this->entityTypeId = $entity_type->id();
    $this->entityType = $entity_type;
  }

  /**
   * {@inheritdoc}
   */
  public function access(HostEntityInterface $host_entity, $operation, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $account = $this->prepareUser($account);
    $langcode = $host_entity->getEntity()->language()->getId();
    $cid = $host_entity->getEntityTypeId() . ':' . $host_entity->id();
    if (($return = $this->getCache($cid, $operation, $langcode, $account)) !== NULL) {
      // Cache hit, no work necessary.
      return $return_as_object ? $return : $return->isAllowed();
    }

    // Invoke hook_registration_host__access(). Hook results
    // take precedence over overridden implementations of
    // EntityAccessControlHandler::checkAccess(). Hosts that have checks that
    // need to be done before the hook is invoked should do so by overriding
    // this method.
    // We grant access to the host if both of these conditions are met:
    // - No modules say to deny access.
    // - At least one module says to grant access.
    // The double underscore in the hook name is to avoid collisions with any
    // registration_host entity type.
    $access = $this->moduleHandler()
      ->invokeAll('registration_host__access', [
        $host_entity,
        $operation,
        $account,
      ]);
    $hook_result = $this->processAccessHookResults($access);

    // Also execute the default access check except when the access result is
    // already forbidden, as in that case, it can not be anything else.
    // For the 'manage' operation, a hook can override the handler and
    // disallow access by returning forbidden, but the final result should
    // never be forbidden as that could override any other access granted in
    // conjunction with this operation in likely unforeseen ways.
    $handler_result = AccessResult::neutral();
    if (!$hook_result->isForbidden()) {
      $handler_result = $this->checkAccess($host_entity, $operation, $account);
      if ($operation === 'manage' && $handler_result->isForbidden()) {
        // Neutralize the forbidden handler result for 'manage'.
        $handler_result = AccessResult::neutral()->addCacheableDependency($handler_result);
      }
    }
    elseif ($operation === 'manage') {
      // Neutralize the forbidden hook result for 'manage'.
      $hook_result = AccessResult::neutral()->addCacheableDependency($hook_result);
    }
    $return = $hook_result->orIf($handler_result);

    $result = $this->setCache($return, $cid, $operation, $langcode, $account);
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * Performs access checks.
   *
   * This method is supposed to be overwritten by extending classes that
   * do their own custom access checking.
   *
   * @param \Drupal\registration\HostEntityInterface $host_entity
   *   The host entity for which to check access.
   * @param string $operation
   *   The entity operation. Usually one of 'view', 'view label', 'update' or
   *   'delete'.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user for which to check access.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  protected function checkAccess(HostEntityInterface $host_entity, $operation, AccountInterface $account): AccessResultInterface {
    // If the host entity is not configured for registration, we return
    // neutral access.
    $type = $host_entity->getRegistrationTypeBundle();
    if (!$type) {
      // The host entity is not configured for registration.
      return AccessResult::neutral()->addCacheableDependency($host_entity);
    }

    if (in_array($operation, ['view registrations', 'update registrations', 'delete registrations'])) {
      $result = $this->checkViewUpdateDeleteRegistrationsAccess($operation, $host_entity, $type, $account);
    }
    elseif ($operation === 'administer registrations') {
      $result = $this->checkAdministerRegistrationsAccess($host_entity, $type, $account);
    }
    elseif ($operation === 'edit registrations state') {
      // There is no matching permission for this operation, but it is
      // supported so other modules can customize this result using the
      // registration_host__access function.
      $result = AccessResult::neutral();
    }
    // 'manage' the host entity.
    elseif ($operation === 'manage') {
      $result = $this->checkManageAccess($host_entity, $account);
    }
    // 'manage' a particular aspect of registration for this host.
    elseif (str_starts_with($operation, 'manage ')) {
      $result = $this->checkManagedAccess($operation, $host_entity, $type, $account);
    }
    elseif (str_starts_with($operation, 'register ')) {
      $result = $this->checkRegisterAccess($operation, $host_entity, $type, $account);
    }

    if (isset($result)) {
      // All the operation results depend on the host entity.
      return $result->addCacheableDependency($host_entity);
    }

    // 'administer' registration' is the super-permission for anything
    // registration-related.
    return AccessResult::allowedIfHasPermission($account, 'administer registration');
  }

  /**
   * Checks access for the 'manage' operation.
   *
   * This is used when checking if the user has administrative access ('owns')
   * the host entity and therefore should be given access to registrations for
   * it. Having it as an operation allows for it to be customized.
   *
   * The 'manage' operation should never return forbidden as that could
   * override any other access granted in conjunction with this operation
   * in likely unforeseen ways. This is enforced in the access() method.
   *
   * @param \Drupal\registration\HostEntityInterface $host_entity
   *   The host entity.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user for which to check access.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  protected function checkManageAccess(HostEntityInterface $host_entity, AccountInterface $account): AccessResultInterface {
    $result = AccessResult::neutral();
    if ($entity = $host_entity->getEntity()) {
      $result = $entity->access('update', $account, TRUE);
    }
    return $result;
  }

  /**
   * Checks access for the 'administer registrations' operation.
   *
   * This grants the ability to perform the 'administer' operation on any
   * registration for this host.
   *
   * @param \Drupal\registration\HostEntityInterface $host_entity
   *   The host entity.
   * @param string $type
   *   The host registration type.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user for which to check access.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  protected function checkAdministerRegistrationsAccess(HostEntityInterface $host_entity, $type, AccountInterface $account): AccessResultInterface {
    $result = AccessResult::allowedIfHasPermissions($account, [
      "administer registration",
      "administer $type registration",
    ], 'OR');
    return $result;
  }

  /**
   * Checks access for 'view_', 'update_' & 'delete_' 'registrations'.
   *
   * @param string $operation
   *   The operation.
   * @param \Drupal\registration\HostEntityInterface $host_entity
   *   The host entity.
   * @param string $type
   *   The host registration type.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user for which to check access.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  protected function checkViewUpdateDeleteRegistrationsAccess($operation, HostEntityInterface $host_entity, $type, AccountInterface $account): AccessResultInterface {
    // Check base permissions first as this check is most performant.
    $base_operation = strstr($operation, ' ', TRUE);
    $result = AccessResult::allowedIfHasPermissions($account, [
      "$base_operation any registration",
      "$base_operation any $type registration",
    ], 'OR');

    // Check administrative access if access is not granted yet. Although the
    // operation requested was not the "administer" operation, if administrative
    // access is granted, that provides implicit access to other operations.
    if ($result->isNeutral()) {
      $administer_result = $host_entity->access('administer registrations', $account, TRUE);
      if ($administer_result->isForbidden()) {
        // Negate a forbidden result that should only apply to the "administer"
        // operation, and should not prevent access to other operations.
        $administer_result = AccessResult::neutral()->addCacheableDependency($administer_result);
      }
      $result = $result->orIf($administer_result);
    }

    // Check host-specific permissions if access is not granted yet.
    if ($result->isNeutral()) {
      $result = AccessResult::allowedIfHasPermission($account, "$base_operation host registration")
        ->andIf($host_entity->access('manage', $account, TRUE))
        ->orIf($result);
    }

    return $result;
  }

  /**
   * Checks access for the 'manage ' operations.
   *
   * This is 'manage registrations', 'manage settings' & 'manage broadcast'.
   *
   * @param string $operation
   *   The operation.
   * @param \Drupal\registration\HostEntityInterface $host_entity
   *   The host entity.
   * @param string $type
   *   The host registration type.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user for which to check access.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  protected function checkManagedAccess($operation, HostEntityInterface $host_entity, $type, AccountInterface $account): AccessResultInterface {
    // Check administrative access. Although the operation requested was not
    // the "administer" operation, if administrative access is granted, that
    // provides implicit access to other operations.
    $result = $host_entity->access('administer registrations', $account, TRUE);
    if ($result->isForbidden()) {
      // Negate a forbidden result that should only apply to the "administer"
      // operation, and should not prevent access to other operations.
      $result = AccessResult::neutral()->addCacheableDependency($result);
    }
    if ($result->isNeutral()) {
      $result = AccessResult::allowedIfHasPermission($account, "administer $type registration settings")
        ->orIf($result);
    }

    // 'manage broadcast' and 'manage settings' operations require additional
    // permissions that the 'manage registrations' operation does not.
    $managed = trim(substr($operation, strlen('manage')));
    $managed_permissions = $managed !== 'registrations' ? ["manage $type registration $managed"] : [];

    // Check the global manage permissions.
    if ($result->isNeutral()) {
      $result = AccessResult::allowedIfHasPermissions($account, array_merge($managed_permissions, ["manage $type registration"]), 'AND')
        ->orIf($result);
    }

    // Check host-specific permissions if access not granted yet.
    if ($result->isNeutral()) {
      $manage_host_result = $this->checkManageAccess($host_entity, $account);

      $administer_own_result = AccessResult::allowedIfHasPermission($account, "administer own $type registration settings")
        ->andIf($manage_host_result);
      $result = $result->orIf($administer_own_result);

      $manage_own_result = AccessResult::allowedIfHasPermissions($account, array_merge($managed_permissions, ["manage own $type registration"]), 'AND')
        ->andIf($manage_host_result);
      $result = $result->orIf($manage_own_result);
    }

    return $result;
  }

  /**
   * Checks access for the 'register' operations.
   *
   * @param string $operation
   *   The operation.
   * @param \Drupal\registration\HostEntityInterface $host_entity
   *   The host entity.
   * @param string $type
   *   The host registration type.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user for which to check access.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  protected function checkRegisterAccess($operation, HostEntityInterface $host_entity, $type, AccountInterface $account): AccessResultInterface {
    $permissions = ['create registration'];
    if ($operation === 'register self') {
      $permissions[] = "create $type registration self";
    }
    elseif ($operation === 'register other users') {
      $permissions[] = "create $type registration other users";
    }
    elseif ($operation === 'register other anonymous') {
      $permissions[] = "create $type registration other anonymous";
    }
    else {
      $permissions = [
        'create registration',
        'create ' . $type . ' registration self',
        'create ' . $type . ' registration other users',
        'create ' . $type . ' registration other anonymous',
      ];
    }
    $permissions[] = 'administer registration';
    if (!Settings::get('registration_disable_create_by_administer_bundle_permission')) {
      $permissions[] = 'administer ' . $type . ' registration';
    }
    return AccessResult::allowedIfHasPermissions($account, $permissions, 'OR');
  }

  /**
   * Determines entity access.
   *
   * We grant access to the entity if both of these conditions are met:
   * - No modules say to deny access.
   * - At least one module says to grant access.
   *
   * @param \Drupal\Core\Access\AccessResultInterface[] $access
   *   An array of access results of the fired access hook.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The combined result of the various access checks' results. All their
   *   cacheability metadata is merged as well.
   *
   * @see \Drupal\Core\Access\AccessResultInterface::orIf()
   */
  protected function processAccessHookResults(array $access): AccessResultInterface {
    // No results means no opinion.
    if (empty($access)) {
      return AccessResult::neutral();
    }

    /** @var \Drupal\Core\Access\AccessResultInterface $result */
    $result = array_shift($access);
    foreach ($access as $other) {
      $result = $result->orIf($other);
    }
    return $result;
  }

  /**
   * Tries to retrieve a previously cached access value from the static cache.
   *
   * @param string $cid
   *   Unique string identifier for the entity/operation, for example the
   *   entity UUID or a custom string.
   * @param string $operation
   *   The entity operation. Usually one of 'view', 'update', 'create' or
   *   'delete'.
   * @param string $langcode
   *   The language code for which to check access.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user for which to check access.
   *
   * @return \Drupal\Core\Access\AccessResultInterface|null
   *   The cached AccessResult, or NULL if there is no record for the given
   *   user, operation, langcode and entity in the cache.
   */
  protected function getCache($cid, $operation, $langcode, AccountInterface $account): ?AccessResultInterface {
    // Return from cache if a value has been set for it previously.
    if (isset($this->accessCache[$account->id()][$cid][$langcode][$operation])) {
      return $this->accessCache[$account->id()][$cid][$langcode][$operation];
    }
    return NULL;
  }

  /**
   * Statically caches whether the given user has access.
   *
   * @param \Drupal\Core\Access\AccessResultInterface $access
   *   The access result.
   * @param string $cid
   *   Unique string identifier for the entity/operation, for example the
   *   entity UUID or a custom string.
   * @param string $operation
   *   The entity operation. Usually one of 'view', 'update', 'create' or
   *   'delete'.
   * @param string $langcode
   *   The language code for which to check access.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user for which to check access.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   Whether the user has access, plus cacheability metadata.
   */
  protected function setCache($access, $cid, $operation, $langcode, AccountInterface $account) {
    // Save the given value in the static cache and directly return it.
    return $this->accessCache[$account->id()][$cid][$langcode][$operation] = $access;
  }

  /**
   * Reset the cache.
   */
  public function resetCache(): void {
    $this->accessCache = [];
  }

  /**
   * Loads the current account object, if it does not exist yet.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account interface instance.
   *
   * @return \Drupal\Core\Session\AccountInterface
   *   Returns the current account object.
   */
  protected function prepareUser(?AccountInterface $account = NULL) {
    if (!$account) {
      $account = \Drupal::currentUser();
    }
    return $account;
  }

}
