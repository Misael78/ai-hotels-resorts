<?php

namespace Drupal\Tests\registration\Unit;

use Drupal\Component\Plugin\Context\ContextDefinitionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\ContextInterface;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\registration\Cache\Context\HostEntityCacheContext;
use Drupal\registration\HostEntityInterface;
use Drupal\registration\RegistrationHostEntityHandlerInterface;
use Drupal\registration\RegistrationManagerInterface;

/**
 * Tests the cache context for a host entity.
 *
 * @coversDefaultClass \Drupal\registration\Cache\Context\HostEntityCacheContext
 *
 * @group registration
 */
class HostEntityCacheContextTest extends UnitTestCase {

  /**
   * @covers ::getContext
   * @covers ::getCacheableMetadata
   */
  public function testHostEntityCacheContext() {
    // Mock the required services and objects.
    $context_definition = $this->createMock(ContextDefinitionInterface::class);
    $context_definition->expects($this->any())->method('getDataType')->willReturn('entity:node');

    $entity = $this->createMock(EntityInterface::class);
    $entity->expects($this->any())->method('getCacheTags')->willReturn(['node:57']);

    $context = $this->createMock(ContextInterface::class);
    $context->expects($this->any())->method('hasContextValue')->willReturn(TRUE);
    $context->expects($this->any())->method('getContextValue')->willReturn($entity);
    $context->expects($this->any())->method('getContextDefinition')->willReturn($context_definition);

    $contexts = ['node' => $context];
    $context_repository = $this->createMock(ContextRepositoryInterface::class);
    $context_repository->expects($this->any())->method('getAvailableContexts')->willReturn($contexts);
    $context_repository->expects($this->any())->method('getRuntimeContexts')->willReturn($contexts);

    $entity_type = $this->createMock(EntityTypeInterface::class);
    $entity_type->expects($this->any())->method('id')->willReturn('node');

    $registration_manager = $this->createMock(RegistrationManagerInterface::class);
    $registration_manager->expects($this->any())->method('getRegistrationEnabledEntityTypes')->willReturn([$entity_type]);

    $host_entity = $this->createMock(HostEntityInterface::class);
    $host_entity->expects($this->once())->method('getEntity')->willReturn($entity);
    $host_entity->expects($this->once())->method('id')->willReturn('57');
    $host_entity->expects($this->once())->method('getEntityTypeId')->willReturn('node');

    $handler = $this->createMock(RegistrationHostEntityHandlerInterface::class);
    $handler->expects($this->once())->method('createHostEntity')->willReturn($host_entity);

    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->expects($this->once())->method('getHandler')->willReturn($handler);

    // Cache context when the host entity is a node.
    $cache_context = new HostEntityCacheContext($context_repository, $entity_type_manager, $registration_manager);
    $this->assertEquals('host_entity:node:57', $cache_context->getContext());
    $this->assertEquals(['node:57'], $cache_context->getCacheableMetadata()->getCacheTags());

    // Cache context when there are no registration enabled entity types.
    $registration_manager = $this->createMock(RegistrationManagerInterface::class);
    $registration_manager->expects($this->any())->method('getRegistrationEnabledEntityTypes')->willReturn([]);
    $cache_context = new HostEntityCacheContext($context_repository, $entity_type_manager, $registration_manager);
    $this->assertEquals('host_entity:none', $cache_context->getContext());
    $this->assertEmpty($cache_context->getCacheableMetadata()->getCacheTags());

    // Cache context when there is no host entity.
    $empty_context_repository = $this->createMock(ContextRepositoryInterface::class);
    $empty_context_repository->expects($this->any())->method('getAvailableContexts')->willReturn([]);
    $empty_context_repository->expects($this->once())->method('getRuntimeContexts')->willReturn([]);
    $cache_context = new HostEntityCacheContext($empty_context_repository, $entity_type_manager, $registration_manager);
    $this->assertEquals('host_entity:none', $cache_context->getContext());
    $this->assertEmpty($cache_context->getCacheableMetadata()->getCacheTags());
  }

}
