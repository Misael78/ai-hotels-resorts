<?php

namespace Drupal\registration\Plugin\Validation\RegistrationConstraint;

use Drupal\registration\Entity\RegistrationInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the RegistrationWithinCapacity constraint.
 *
 * This validator adds a cache dependency on the list of registrations,
 * and should be avoided when used as part of access control. Otherwise
 * the access control will need to recalculate too often, making the
 * caching less effective.
 *
 * @phpcs:disable Drupal.Semantics.FunctionT.NotLiteralString
 */
class RegistrationWithinCapacityConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($registration, Constraint $constraint) {
    /** @var RegistrationWithinCapacityConstraint $constraint */
    if ($registration instanceof RegistrationInterface) {
      if ($host_entity = $registration->getHostEntity()) {

        // Recheck if registrations are added, updated or deleted for this host.
        $list_cache_tag = $host_entity->getRegistrationListCacheTag();
        $this->context->getCacheableMetadata()->addCacheTags([$list_cache_tag]);

        $spaces = $registration->getSpacesReserved();
        if (!$host_entity->hasRoom($spaces, $registration)) {
          if ($spaces > 1) {
            // The host entity does not have room for a registration requesting
            // more than one space. The only way for users to request multiple
            // spaces is if the Spaces field (machine name "count") is displayed
            // on the registration form. So ensure this field is highlighted by
            // using the atPath method to identify the Spaces field as the
            // field containing the invalid value. The user can then lower the
            // value in this field and retry, if desired.
            $this->context
              ->buildViolation($constraint->noRoomMessage, [
                '%label' => $host_entity->label(),
              ])
              ->atPath('count')
              ->setCode($constraint->noRoomCode)
              ->setCause(t($constraint->noRoomCause))
              ->addViolation();
          }
          else {
            // The host entity does not have room for a registration requesting
            // a single space. There is no way to request less than one space,
            // so add a general violation at the entity level and not the field
            // level. The user will not be able to register unless some other
            // registration is canceled.
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
  }

}
