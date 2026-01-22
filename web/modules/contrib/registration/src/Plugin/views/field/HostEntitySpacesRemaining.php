<?php

namespace Drupal\registration\Plugin\views\field;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Field handler to present the spaces remaining for a host entity.
 *
 * @ViewsField("host_entity_spaces_remaining")
 */
class HostEntitySpacesRemaining extends FieldPluginBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): HostEntitySpacesRemaining {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function clickSortable() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Leave empty to avoid a query on this field.
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $build = [];
    if ($entity = $this->getEntity($values)) {
      $handler = $this->entityTypeManager->getHandler($entity->getEntityTypeId(), 'registration_host_entity');
      $host_entity = $handler->createHostEntity($entity);
      // Rebuild when the host entity changes.
      $cacheability = CacheableMetadata::createFromObject($host_entity);
      if ($host_entity->isConfiguredForRegistration()) {
        $spaces_remaining = $host_entity->getSpacesRemaining();
        if (!is_null($spaces_remaining)) {
          $build = [
            '#markup' => $spaces_remaining,
          ];
        }
        else {
          $build = [
            '#markup' => $this->t('Unlimited'),
          ];
        }
        // Rebuild when registrations are added or removed for this host entity.
        $cacheability->addCacheTags([$host_entity->getRegistrationListCacheTag()]);
      }
      $cacheability->applyTo($build);
    }
    return $build;
  }

}
