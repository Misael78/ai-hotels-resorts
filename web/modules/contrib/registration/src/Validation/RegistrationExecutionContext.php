<?php

namespace Drupal\registration\Validation;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Validation\ExecutionContext;
use Drupal\registration\RegistrationValidationResultInterface;

/**
 * Extends the execution context with cacheable metadata.
 */
class RegistrationExecutionContext extends ExecutionContext implements RegistrationExecutionContextInterface {

  /**
   * Indicates if an early end to pipeline execution was requested.
   */
  protected bool $endEarly = FALSE;

  /**
   * The original (unchanged) entity.
   */
  protected EntityInterface $original;

  /**
   * The validation result.
   */
  protected RegistrationValidationResultInterface $result;

  /**
   * The cacheable metadata.
   */
  protected CacheableMetadata $cacheableMetadata;

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata(): CacheableMetadata {
    if (!isset($this->cacheableMetadata)) {
      $this->setCacheableMetadata((new CacheableMetadata()));
    }
    return $this->cacheableMetadata;
  }

  /**
   * {@inheritdoc}
   */
  public function getOriginal(): ?EntityInterface {
    return $this->original ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getResult(): RegistrationValidationResultInterface {
    return $this->result;
  }

  /**
   * {@inheritdoc}
   */
  public function setOriginal(EntityInterface $entity) {
    $this->original = $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setCacheableMetadata(CacheableMetadata $cacheableMetadata): void {
    $this->cacheableMetadata = $cacheableMetadata;
  }

  /**
   * {@inheritdoc}
   */
  public function setResult(RegistrationValidationResultInterface $result) {
    $this->result = $result;
  }

  /**
   * {@inheritdoc}
   */
  public function shouldEndPipelineEarly(): bool {
    return $this->endEarly;
  }

  /**
   * {@inheritdoc}
   */
  public function endPipelineEarly(): void {
    $this->endEarly = TRUE;
  }

}
