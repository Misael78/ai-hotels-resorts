<?php

namespace Drupal\registration;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\VariationCacheInterface;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\Core\Validation\DrupalTranslator;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\registration\Event\RegistrationDataAlterEvent;
use Drupal\registration\Event\RegistrationEvents;
use Drupal\registration\Validation\RegistrationConstraintManager;
use Drupal\registration\Validation\RegistrationExecutionContextFactory;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Defines the class for a registration validator.
 *
 * Uses a variation cache wrapped around a memory cache backend to cache
 * validation results within a single page request. This improves performance
 * for forms and blocks that call the validator multiple times within a single
 * request, and when running tests.
 */
class RegistrationValidator implements RegistrationValidatorInterface {

  /**
   * The variation cache.
   */
  protected VariationCacheInterface $cache;

  /**
   * The default cache contexts to vary every cache item by.
   *
   * Tests can change the current user or the language within a single
   * test run, so ensure results are cached per user and per language.
   *
   * @var string[]
   */
  protected array $cacheContexts = [
    'languages',
    'user',
  ];

  /**
   * The class resolver.
   */
  protected ClassResolverInterface $classResolver;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The event dispatcher.
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * The registration constraint manager.
   */
  protected RegistrationConstraintManager $registrationConstraintManager;

  /**
   * The typed data manager.
   */
  protected TypedDataManagerInterface $typedDataManager;

  /**
   * Creates a RegistrationValidator object.
   *
   * @param \Drupal\Core\Cache\VariationCacheInterface $cache
   *   The variation cache.
   * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $class_resolver
   *   The class resolver.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\registration\Validation\RegistrationConstraintManager $registration_constraint_manager
   *   The registration constraint manager.
   * @param \Drupal\Core\TypedData\TypedDataManagerInterface $typed_data_manager
   *   The typed data manager.
   */
  public function __construct(VariationCacheInterface $cache, ClassResolverInterface $class_resolver, EntityTypeManagerInterface $entity_type_manager, EventDispatcherInterface $event_dispatcher, RegistrationConstraintManager $registration_constraint_manager, TypedDataManagerInterface $typed_data_manager) {
    $this->cache = $cache;
    $this->classResolver = $class_resolver;
    $this->entityTypeManager = $entity_type_manager;
    $this->eventDispatcher = $event_dispatcher;
    $this->registrationConstraintManager = $registration_constraint_manager;
    $this->typedDataManager = $typed_data_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(string $pipeline_id, string|array $constraint_ids, mixed $value): RegistrationValidationResultInterface {
    if (empty($constraint_ids)) {
      throw new \InvalidArgumentException("Constraints cannot be empty");
    }

    // Ensure the constraint IDs variable is an array.
    if (is_string($constraint_ids)) {
      $constraint_ids = [$constraint_ids];
    }

    // Check the cache.
    if ($cache_keys = $this->getCacheKeys($pipeline_id, $constraint_ids, $value)) {
      $cached = $this->cache->get($cache_keys, (new CacheableMetadata())->setCacheContexts($this->cacheContexts));
      if ($cached) {
        $validation_result = $cached->data;
        $validation_result->setCached();
        return $validation_result;
      }
    }

    // Calculate the original entity if the value is an existing entity.
    $original = NULL;
    if (($value instanceof EntityInterface) && !$value->isNew()) {
      $storage = $this->entityTypeManager->getStorage($value->getEntityTypeId());
      $original = $storage->loadUnchanged($value->id());
    }

    // Get the context factory.
    $context_factory = new RegistrationExecutionContextFactory(new DrupalTranslator());

    // Get the typed data validator needed to create a context.
    $typed_data_validator = $this->typedDataManager->getValidator();

    // Initialize the result.
    $validation_result = new RegistrationValidationResult($constraint_ids, $value);

    // Get the constraint pipeline.
    $pipeline = $this->getConstraintPipeline($constraint_ids);

    // Normalize the constraint IDs.
    $constraint_ids = $this->normalize($constraint_ids);

    // Execute the constraint pipeline.
    foreach ($pipeline as $constraint_id => $constraint) {
      // Check dependencies.
      /** @var \Drupal\registration\Validation\RegistrationConstraintInterface $constraint */
      if ($constraint->hasUnmetDependencies($constraint_id, $constraint_ids)) {
        throw new \InvalidArgumentException("Constraint $constraint_id has unmet dependencies");
      }

      // Get the constraint validator.
      $validator = $this->classResolver->getInstanceFromDefinition($constraint->validatedBy());

      // Build the execution context needed for validation.
      $context = $context_factory->createContext($typed_data_validator, $value);
      $context->setConstraint($constraint);
      $context->setCacheableMetadata($validation_result->getCacheableMetadata());
      $context->setResult($validation_result);

      // Set the original entity if available.
      if ($original) {
        $context->setOriginal($original);
      }

      // Initialize and validate.
      $validator->initialize($context);
      $validator->validate($value, $constraint);

      // Accumulate violations in the result.
      $validation_result->addViolations($context->getViolations());

      // Accumulate cacheability in the result.
      $validation_result->addCacheableDependency($context->getCacheableMetadata());

      if ($context->shouldEndPipelineEarly()) {
        // End execution of the pipeline early. The context can request this if
        // there was a severe error, or the validator discovered unexpected or
        // incorrect configuration that makes additional validation useless.
        break;
      }
    }

    // Extract host entity and registration.
    [$host_entity, $registration] = RegistrationHelper::extractEntitiesFromValue($value);

    // Dispatch a legacy event for BC reasons.
    if ($this->shouldDispatchLegacyEvent($pipeline_id)) {
      if ($host_entity instanceof HostEntityInterface) {
        if ($settings = $host_entity->getSettings()) {
          // @phpstan-ignore-next-line
          $errors = $validation_result->getLegacyErrors();

          $event = new RegistrationDataAlterEvent($validation_result->isValid(), [
            'host_entity' => $host_entity,
            'settings' => $settings,
            'spaces' => $registration?->getSpacesReserved() ?? 1,
            'registration' => $registration,
            'errors' => $errors,
          ]);
          // @phpstan-ignore-next-line
          $this->eventDispatcher->dispatch($event, RegistrationEvents::REGISTRATION_ALTER_ENABLED);

          // Update the errors array if the event has updated errors.
          if ($event->hasErrors()) {
            $errors = $event->getErrors();
          }

          // Remove all violations if status changed to enabled.
          $enabled = $event->getData() ?? FALSE;
          if ($enabled && !$validation_result->isValid()) {
            // Create a new valid result, but retain cacheability from the
            // original result.
            $cacheable_metadata = $validation_result->getCacheableMetadata();
            $validation_result = new RegistrationValidationResult($constraint_ids, $value);
            $validation_result->addCacheableDependency($cacheable_metadata);
          }

          // Status changed to disabled.
          elseif (!$enabled && $validation_result->isValid()) {
            $this->copyErrorsToViolations($validation_result, $errors);

            // If there are still no violations, then the errors array is empty
            // even though status is disabled. Add a disabled violation to cover
            // this possibility.
            if ($validation_result->isValid()) {
              $validation_result->addViolation('Registration for %label is disabled.', [
                '%label' => $host_entity->label(),
              ], NULL, NULL, NULL, 'status');
            }
          }

          // No status change.
          else {
            $violations = $validation_result->getViolations();

            // Remove violations that were removed from errors.
            foreach ($violations as $index => $violation) {
              if ($code = $violation->getCode()) {
                if (!isset($errors[$code])) {
                  $violations->remove($index);
                }
              }
            }

            // Add violations that were added to errors.
            $this->copyErrorsToViolations($validation_result, $errors);
          }
        }
      }
    }

    // Allow other modules to override the result.
    $event = new RegistrationDataAlterEvent($validation_result, [
      'pipeline_id' => $pipeline_id,
      'constraint_ids' => $constraint_ids,
      'value' => $value,
      'host_entity' => $host_entity,
      'registration' => $registration,
    ]);
    $this->eventDispatcher->dispatch($event, RegistrationEvents::REGISTRATION_ALTER_VALIDATION_RESULT);

    /** @var \Drupal\registration\RegistrationValidationResultInterface $validation_result */
    $validation_result = $event->getData();

    // Store in the cache if cacheable.
    if ($cache_keys) {
      $cacheable_metadata = $validation_result->getCacheableMetadata();
      $this->cache->set(
        $cache_keys,
        $validation_result,
        // Add cache contexts without affecting actual result cacheability.
        (new CacheableMetadata())->addCacheableDependency($cacheable_metadata)->addCacheContexts($this->cacheContexts),
        // Set the initial cache contexts, also used when doing cache lookups.
        (new CacheableMetadata())->setCacheContexts($this->cacheContexts)
      );
    }

    return $validation_result;
  }

  /**
   * Copies error messages to the violations list in the validation result.
   *
   * @param \Drupal\registration\RegistrationValidationResultInterface $validation_result
   *   The validation result.
   * @param array $errors
   *   The errors, indexed by error code.
   */
  protected function copyErrorsToViolations(RegistrationValidationResultInterface $validation_result, array $errors): void {
    foreach ($errors as $code => $error) {
      if (is_string($code)) {
        if (!$validation_result->hasViolationWithCode($code)) {
          if ($error instanceof TranslatableMarkup) {
            $validation_result->addViolation($error->getUntranslatedString(), $error->getArguments(), NULL, NULL, NULL, $code);
          }
          elseif (is_string($error)) {
            $validation_result->addViolation($error, [], NULL, NULL, NULL, $code);
          }
        }
      }
    }
  }

  /**
   * Gets the cache keys for a given validation check.
   *
   * @param string $pipeline_id
   *   An identifier for the constraint pipeline.
   * @param array $constraint_ids
   *   A list of constraint plugin IDs.
   * @param mixed $value
   *   The value to validate, e.g. an entity or other object. This is most
   *   often a registration entity, but can be any value or object relevant
   *   to registrations.
   *
   * @return string[]|null
   *   The cache keys, or NULL if the validation check is not cacheable.
   */
  protected function getCacheKeys(string $pipeline_id, array $constraint_ids, mixed $value): ?array {
    // Only the availability and open checks are cacheable, as the results of
    // other checks can vary per invocation.
    $cacheable_pipelines = [
      'available_for_registration',
      'open_for_registration',
    ];
    if (in_array($pipeline_id, $cacheable_pipelines)) {
      if ($value instanceof HostEntityInterface) {
        $host_entity_id = $value->id();
        if (!empty($host_entity_id)) {
          $cache_keys = [$pipeline_id];
          $cache_keys[] = $value->getEntityTypeId();
          $cache_keys[] = (string) $host_entity_id;
          return $cache_keys;
        }
      }
    }
    return NULL;
  }

  /**
   * Gets the constraint pipeline from a list of constraint plugin IDs.
   *
   * @param array $constraint_ids
   *   A list of constraint plugin IDs.
   *
   * @return \Symfony\Component\Validator\Constraint[]
   *   The pipeline as a list of constraint objects, indexed by plugin ID.
   */
  protected function getConstraintPipeline(array $constraint_ids): array {
    $constraints = [];
    foreach ($constraint_ids as $index => $constraint_id) {
      if (is_string($index) && is_array($constraint_id)) {
        // The index is the plugin ID, and the constraint ID is actually a
        // plugin configuration array.
        $constraints[$index] = $this->registrationConstraintManager->create($index, $constraint_id);
      }
      else {
        $constraints[$constraint_id] = $this->registrationConstraintManager->create($constraint_id, NULL);
      }
    }

    return $constraints;
  }

  /**
   * Normalizes the input constraint ID list into an associative array.
   *
   * This allows event subscribers to have a consistent way of checking the
   * constraint list.
   *
   * @param array $constraint_ids
   *   A list of constraint plugin IDs.
   *
   * @return array
   *   A list of constraints indexed by constraint plugin IDs.
   *   The array values are configuration arrays, which may be empty.
   */
  protected function normalize(array $constraint_ids): array {
    $associative_constraint_ids = [];
    foreach ($constraint_ids as $index => $constraint_id) {
      if (is_string($index) && is_array($constraint_id)) {
        // The index is the plugin ID, and the constraint ID is actually a
        // plugin configuration array.
        $associative_constraint_ids[$index] = $constraint_id;
      }
      else {
        $associative_constraint_ids[$constraint_id] = [];
      }
    }

    return $associative_constraint_ids;
  }

  /**
   * Determines if a legacy event should be dispatched for a given pipeline.
   *
   * @param string $pipeline_id
   *   The pipeline ID.
   *
   * @return bool
   *   TRUE if a legacy event should be dispatched, FALSE otherwise.
   */
  protected function shouldDispatchLegacyEvent(string $pipeline_id): bool {
    $availability_pipelines = [
      'available_for_registration',
      'enabled_for_registration',
      'has_room_for_registration',
      'open_for_registration',
      'validate_registration',
    ];

    return in_array($pipeline_id, $availability_pipelines);
  }

}
