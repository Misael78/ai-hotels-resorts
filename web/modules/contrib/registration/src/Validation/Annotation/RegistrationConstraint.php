<?php

namespace Drupal\registration\Validation\Annotation;

use Drupal\Core\Validation\Annotation\Constraint;

/**
 * Defines a validation constraint annotation object for registration.
 *
 * This plugin differs from standard constraints in Drupal core in that it
 * is executed only by the registration.validator service and not the Entity
 * Validation API. It runs using a special execution context that supports
 * cacheable metadata and constraint dependencies.
 *
 * @phpcs:disable Drupal.Commenting.DocComment.LongFullStop
 *
 * Plugin Namespace: Plugin\Validation\RegistrationConstraint
 *
 * @see \Drupal\registration\RegistrationValidator
 * @see \Drupal\registration\Validation\RegistrationConstraintManager
 * @see \Drupal\registration\Validation\RegistrationExecutionContext
 * @see \Symfony\Component\Validator\Constraint
 * @see plugin_api
 *
 * @Annotation
 */
class RegistrationConstraint extends Constraint {}
