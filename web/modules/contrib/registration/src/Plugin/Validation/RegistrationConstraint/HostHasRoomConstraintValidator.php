<?php

namespace Drupal\registration\Plugin\Validation\RegistrationConstraint;

use Drupal\registration\HostEntityInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the HostHasRoomConstraint constraint.
 *
 * This validator adds a cache dependency on the list of registrations,
 * and should be avoided when used as part of access control. Otherwise
 * the access control will need to recalculate too often, making the
 * caching less effective.
 *
 * @phpcs:disable Drupal.Semantics.FunctionT.NotLiteralString
 */
class HostHasRoomConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    /** @var HostHasRoomConstraint $constraint */
    $host_entity = $constraint->hostEntity ?? $value;
    $spaces = $constraint->spaces ?? 1;

    if ($host_entity instanceof HostEntityInterface) {
      // Recheck when registrations are added, updated or deleted for this host.
      $list_cache_tag = $host_entity->getRegistrationListCacheTag();
      $this->context->getCacheableMetadata()->addCacheTags([$list_cache_tag]);

      if (!$host_entity->hasRoom($spaces)) {
        $this->context
          ->buildViolation($constraint->noRoomMessage, [
            '%label' => $host_entity->label(),
          ])
          ->setCode($constraint->noRoomCode)
          ->setCause(t($constraint->noRoomCause))
          ->addViolation();
      }
    }
  }

}
