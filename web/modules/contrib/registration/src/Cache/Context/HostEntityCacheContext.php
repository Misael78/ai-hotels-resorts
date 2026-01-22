<?php

namespace Drupal\registration\Cache\Context;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\CacheContextInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\ContextInterface;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\registration\HostEntityInterface;
use Drupal\registration\RegistrationManagerInterface;

/**
 * Defines the HostEntityCacheContext for "per host entity" caching.
 *
 * Cache context ID: 'host_entity'.
 */
class HostEntityCacheContext implements CacheContextInterface {

  /**
   * The host entity.
   *
   * @var \Drupal\registration\HostEntityInterface
   */
  protected HostEntityInterface $hostEntity;

  /**
   * Constructs a new HostEntityCacheContext object.
   *
   * @param \Drupal\Core\Plugin\Context\ContextRepositoryInterface $context_repository
   *   The context repository.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\registration\RegistrationManagerInterface $registration_manager
   *   The registration manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function __construct(ContextRepositoryInterface $context_repository, EntityTypeManagerInterface $entity_type_manager, RegistrationManagerInterface $registration_manager) {
    // Get the contexts for registration enabled entity types.
    $entity_contexts = array_map(function (EntityTypeInterface $entity_type) {
      return 'entity:' . $entity_type->id();
    }, $registration_manager->getRegistrationEnabledEntityTypes());

    // Get all populated contexts.
    $context_ids = array_keys($context_repository->getAvailableContexts());
    $populated_contexts = array_filter($context_repository->getRuntimeContexts($context_ids), function (ContextInterface $context) {
      return $context->hasContextValue();
    });

    // Get the host entity for the first populated registration enabled context.
    foreach ($populated_contexts as $context) {
      if (in_array($context->getContextDefinition()->getDataType(), $entity_contexts)) {
        $this->hostEntity = $entity_type_manager
          ->getHandler($context->getContextValue()->getEntityTypeId(), 'registration_host_entity')
          ->createHostEntity($context->getContextValue());
        break;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getLabel(): TranslatableMarkup {
    return new TranslatableMarkup('Host entity');
  }

  /**
   * {@inheritdoc}
   */
  public function getContext(): string {
    if (isset($this->hostEntity)) {
      return 'host_entity:' . $this->hostEntity->getEntityTypeId() . ':' . $this->hostEntity->id();
    }
    return 'host_entity:none';
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata(): CacheableMetadata {
    $cacheable_metadata = new CacheableMetadata();
    if (isset($this->hostEntity)) {
      $cacheable_metadata->setCacheTags($this->hostEntity->getEntity()->getCacheTags());
    }
    return $cacheable_metadata;
  }

}
