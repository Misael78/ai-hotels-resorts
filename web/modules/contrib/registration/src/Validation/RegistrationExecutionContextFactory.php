<?php

namespace Drupal\registration\Validation;

use Drupal\Core\Validation\ExecutionContextFactory;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Extends the Drupal execution factory for registration.
 */
class RegistrationExecutionContextFactory extends ExecutionContextFactory {

  /**
   * {@inheritdoc}
   */
  public function createContext(ValidatorInterface $validator, mixed $root): RegistrationExecutionContextInterface {
    return new RegistrationExecutionContext(
      $validator,
      $root,
      $this->translator,
      $this->translationDomain
    );
  }

}
