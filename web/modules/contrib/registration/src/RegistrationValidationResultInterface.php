<?php

namespace Drupal\registration;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityConstraintViolationListInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Defines the interface for a result from a registration validation check.
 */
interface RegistrationValidationResultInterface {

  /**
   * Adds a violation to the result.
   *
   * This method should not be called from constraint validators. It is intended
   * for use in event subscribers and other cases when a validation context is
   * not available. Constraint validators should use the standard constraint
   * builder methods documented at the link below.
   *
   * If a code is passed and a violation already exists with that code, the
   * the existing violation is replaced by the new one, to avoid duplicates.
   *
   * @param string $message
   *   The violation message template.
   * @param array $parameters
   *   (Optional) The violation parameters.
   * @param string|null $path
   *   (Optional) The property path.
   * @param mixed $value
   *   (Optional) The invalid value.
   * @param int|null $plural
   *   (Optional) The plural value.
   * @param string|null $code
   *   (Optional) The violation code.
   * @param mixed $cause
   *   (Optional) The violation cause.
   *
   * @see https://www.drupal.org/docs/drupal-apis/entity-api/entity-validation-api/defining-constraints-validations-on-entities-andor-fields
   */
  public function addViolation(string $message, array $parameters = [], ?string $path = NULL, mixed $value = NULL, ?int $plural = NULL, ?string $code = NULL, mixed $cause = NULL): void;

  /**
   * Merges another violation list into the result.
   *
   * Violations in the other list that match an existing violation code
   * replace the existing violations, to avoid duplicates.
   *
   * @param \Symfony\Component\Validator\ConstraintViolationListInterface $other_list
   *   The other violation list.
   */
  public function addViolations(ConstraintViolationListInterface $other_list): void;

  /**
   * Gets the cacheable metadata resulting from a validation check.
   *
   * This is a convenience function that creates the metadata from the result.
   * Callers of this method that alter the returned metadata are changing a
   * copy of the result metadata, and are not changing the metadata within
   * the result. To alter the metadata within the result, callers should use
   * the methods in \Drupal\Core\Cache\RefinableCacheableDependencyInterface,
   * using code similar to this:
   *
   * @code
   *   $validation_result->addCacheableDependency($other_object);
   *   // or:
   *   $validation_result->addCacheTags(['other_object:1']);
   *   // or:
   *   $validation_result->addCacheContexts(['some_context']);
   *   // or:
   *   $validation_result->mergeCacheMaxAge(Cache::PERMANENT);
   * @endcode
   *
   * @return \Drupal\Core\Cache\CacheableMetadata
   *   The cacheable metadata.
   */
  public function getCacheableMetadata(): CacheableMetadata;

  /**
   * Gets the validation constraints.
   *
   * @return string[]
   *   The constraints that were validated, as an array of plugin IDs.
   */
  public function getConstraints(): array;

  /**
   * Gets the reason when there are one or more violations.
   *
   * This is a convenience method that provides a short message with the
   * violation causes appended, e.g. "Registration is not available: No room."
   *
   * This method can be used when there is limited space to display messages,
   * or in contexts when the full message text is not needed, such as throwing
   * exceptions. In most cases, it is preferable to loop on the violations and
   * use the getMessage() method of the violation interface to display the full
   * error message text.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   The reason, if available. Returns NULL if the result is valid.
   *
   * @see \Symfony\Component\Validator\ConstraintViolationInterface
   */
  public function getReason(): ?TranslatableMarkup;

  /**
   * Gets the value that was validated.
   *
   * @return mixed
   *   The value.
   */
  public function getValue(): mixed;

  /**
   * Gets the violations resulting from a validation check.
   *
   * Note that the list may be empty if an object passes validation.
   *
   * @return \Symfony\Component\Validator\ConstraintViolationListInterface|\Drupal\Core\Entity\EntityConstraintViolationListInterface
   *   The violations. An entity constraint violation list is returned if
   *   possible. If the value being validated is not a fieldable entity,
   *   a standard constraint violation list is returned instead.
   */
  public function getViolations(): ConstraintViolationListInterface|EntityConstraintViolationListInterface;

  /**
   * Determines if there is a violation with the specified code.
   *
   * @param string $code
   *   The code.
   *
   * @return bool
   *   TRUE if there is a violation with the specified code, FALSE otherwise.
   */
  public function hasViolationWithCode(string $code): bool;

  /**
   * Determines if an object passed validation.
   *
   * This is a convenience function that checks if the violation count is zero.
   *
   * @return bool
   *   TRUE if an object passed validation, FALSE otherwise.
   */
  public function isValid(): bool;

  /**
   * Removes all violations.
   */
  public function removeAllViolations(): void;

  /**
   * Removes a violation with the specified code, if it exists.
   *
   * @param string $code
   *   The code.
   */
  public function removeViolationWithCode(string $code): void;

  /**
   * Indicates that the result was retrieved from cache.
   *
   * This method should only be called by registration validators.
   */
  public function setCached(): void;

  /**
   * Determines if the result was retrieved from cache.
   *
   * Results are cached within the duration of a single page request using a
   * memory cache, so most results are recalculated and not retrieved from
   * cache. This will only return TRUE when the same check to validate the
   * same value is made within one page build, and the result is not
   * invalidated in the cache between invocations.
   *
   * @return bool
   *   TRUE if the result was retrieved from cache, FALSE otherwise.
   */
  public function wasCached(): bool;

}
