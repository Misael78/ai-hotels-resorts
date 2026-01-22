<?php

namespace Drupal\registration\Validation;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\registration\RegistrationValidationResultInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Extends the execution context of a validation run.
 */
interface RegistrationExecutionContextInterface extends ExecutionContextInterface {

  /**
   * Gets the cacheable metadata for the execution context.
   *
   * @return \Drupal\Core\Cache\CacheableMetadata
   *   The cacheable metadata.
   */
  public function getCacheableMetadata(): CacheableMetadata;

  /**
   * Gets the original (unchanged) entity.
   *
   * This method returns NULL unless the value being validated is an existing
   * entity. The original entity can be compared to the entity being validated
   * to see what data has changed.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The original entity, if available.
   */
  public function getOriginal(): ?EntityInterface;

  /**
   * Gets the current validation result.
   *
   * In a constraint pipeline with multiple constraints, this is an interim
   * result. Constraint validators can inspect the result but should not
   * modify it.
   *
   * @return \Drupal\registration\RegistrationValidationResultInterface
   *   The result.
   */
  public function getResult(): RegistrationValidationResultInterface;

  /**
   * Sets the cacheable metadata for the execution context.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheableMetadata
   *   The cacheable metadata.
   */
  public function setCacheableMetadata(CacheableMetadata $cacheableMetadata): void;

  /**
   * Sets the original (unchanged) entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity before it was changed.
   */
  public function setOriginal(EntityInterface $entity);

  /**
   * Sets the current validation result.
   *
   * @param \Drupal\registration\RegistrationValidationResultInterface $result
   *   The result.
   */
  public function setResult(RegistrationValidationResultInterface $result);

  /**
   * Determines if the context should end execution of the pipeline early.
   *
   * @return bool
   *   TRUE if the context should end execution, FALSE otherwise.
   */
  public function shouldEndPipelineEarly(): bool;

  /**
   * End execution of the active pipeline early.
   */
  public function endPipelineEarly(): void;

}
