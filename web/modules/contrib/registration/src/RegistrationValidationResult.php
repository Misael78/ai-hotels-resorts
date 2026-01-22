<?php

namespace Drupal\registration;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Cache\RefinableCacheableDependencyTrait;
use Drupal\Core\Entity\EntityConstraintViolationList;
use Drupal\Core\Entity\EntityConstraintViolationListInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Defines the class for a registration validation result.
 */
class RegistrationValidationResult implements RefinableCacheableDependencyInterface, RegistrationValidationResultInterface {

  use RefinableCacheableDependencyTrait;

  /**
   * The validation constraints.
   */
  protected array $constraints;

  /**
   * The violation list.
   */
  protected ConstraintViolationListInterface|EntityConstraintViolationListInterface $violationList;

  /**
   * The value that was validated.
   */
  protected mixed $value;

  /**
   * Whether the result was retrieved from cache.
   */
  protected bool $cached = FALSE;

  /**
   * Creates a RegistrationValidationResult object.
   *
   * @param array $constraints
   *   The validation constraints.
   * @param mixed $value
   *   The value being validated.
   */
  public function __construct(array $constraints, mixed $value) {
    $this->constraints = $constraints;
    $this->value = $value;
    $this->violationList = ($value instanceof FieldableEntityInterface) ? new EntityConstraintViolationList($value) : new ConstraintViolationList();

    // If the value implements cache dependencies, initialize cacheability of
    // this result with those dependencies.
    if ($value instanceof CacheableDependencyInterface) {
      $this->addCacheableDependency($value);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function addViolation(string $message, array $parameters = [], ?string $path = NULL, mixed $value = NULL, ?int $plural = NULL, ?string $code = NULL, mixed $cause = NULL): void {
    // Prevent duplicate codes.
    if ($code) {
      $this->removeViolationWithCode($code);
    }

    // @phpcs:disable Drupal.Semantics.FunctionT.NotLiteralString
    $violation = new ConstraintViolation(new TranslatableMarkup($message, $parameters), $message, $parameters, $this->value, $path, $value, $plural, $code, NULL, $cause);
    $this->getViolations()->add($violation);
  }

  /**
   * {@inheritdoc}
   */
  public function addViolations(ConstraintViolationListInterface $other_list): void {
    foreach ($other_list as $violation) {
      // Prevent duplicate codes.
      if ($code = $violation->getCode()) {
        $this->removeViolationWithCode($code);
      }

      $this->getViolations()->add($violation);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata(): CacheableMetadata {
    return CacheableMetadata::createFromObject($this);
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraints(): array {
    return $this->constraints;
  }

  /**
   * Gets the violations as an errors array in legacy format.
   *
   * Generally, this function should not be called by user code. It exists
   * only to provide legacy support for deprecated functions and events.
   *
   * @return array
   *   The errors as an array of error messages indexed by code.
   *   Example codes are "status" and "capacity".
   *
   * @deprecated in registration:3.4.0 and is removed from registration:4.0.0.
   *   Use getViolations instead.
   *
   * @see https://www.drupal.org/node/3496339
   */
  public function getLegacyErrors(): array {
    $errors = [];
    foreach ($this->getViolations() as $violation) {
      $errors[$violation->getCode()] = $violation->getMessage();
    }
    return $errors;
  }

  /**
   * {@inheritdoc}
   */
  public function getReason(): ?TranslatableMarkup {
    if (!$this->isValid()) {
      $causes = [];
      foreach ($this->getViolations() as $violation) {
        $causes[] = (string) $violation->getCause();
      }
      return new TranslatableMarkup('Registration is not available: @causes', [
        '@causes' => implode(' ', $causes),
      ]);
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getValue(): mixed {
    return $this->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getViolations(): ConstraintViolationListInterface|EntityConstraintViolationListInterface {
    return $this->violationList;
  }

  /**
   * {@inheritdoc}
   */
  public function hasViolationWithCode(string $code): bool {
    $violations = $this->violationList->findByCodes([$code]);
    return ($violations->count() > 0);
  }

  /**
   * {@inheritdoc}
   */
  public function isValid(): bool {
    return ($this->violationList->count() == 0);
  }

  /**
   * {@inheritdoc}
   */
  public function removeAllViolations(): void {
    foreach ($this->violationList as $offset => $violation) {
      $this->violationList->remove($offset);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function removeViolationWithCode(string $code): void {
    foreach ($this->violationList as $offset => $violation) {
      if ($violation->getCode() === $code) {
        $this->violationList->remove($offset);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setCached(): void {
    $this->cached = TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function wasCached(): bool {
    return $this->cached;
  }

}
