<?php

namespace Drupal\registration\Plugin\views\area;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\views\Plugin\views\area\AreaPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a caption area handler.
 *
 * @ingroup views_area_handlers
 *
 * @ViewsArea("manage_registrations_caption")
 */
class ManageRegistrationsCaption extends AreaPluginBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): ManageRegistrationsCaption {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function render($empty = FALSE): array {
    $build = [];
    if (!$empty || !empty($this->options['empty'])) {
      $storage = $this->entityTypeManager->getStorage($this->view->args[0]);
      if ($storage && ($entity = $storage->load($this->view->args[1]))) {
        $handler = $this->entityTypeManager->getHandler($entity->getEntityTypeId(), 'registration_host_entity');
        $host_entity = $handler->createHostEntity($entity);
        if ($host_entity->getRegistrationTypeBundle()) {
          $settings = $host_entity->getSettings();
          $capacity = $settings->getSetting('capacity');
          $spaces = $host_entity->getActiveSpacesReserved();
          if ($capacity) {
            $caption = $this->formatPlural($capacity,
             'List of registrations for %label. @spaces of 1 space is filled.',
             'List of registrations for %label. @spaces of @count spaces are filled.', [
               '%label' => $host_entity->label(),
               '@capacity' => $capacity,
               '@spaces' => $spaces,
             ]);
          }
          else {
            $caption = $this->formatPlural($spaces,
             'List of registrations for %label. 1 space is filled.',
             'List of registrations for %label. @count spaces are filled.', [
               '%label' => $host_entity->label(),
             ]);
          }
          $build = [
            '#markup' => $caption,
          ];
        }

        // Set cache directives so the area rebuilds when needed.
        $cacheability = CacheableMetadata::createFromObject($host_entity);
        $cacheability->applyTo($build);
      }
    }
    return $build;
  }

}
