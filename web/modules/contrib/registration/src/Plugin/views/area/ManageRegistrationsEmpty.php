<?php

namespace Drupal\registration\Plugin\views\area;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\views\Plugin\views\area\AreaPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines an area used when there are no registrations to manage.
 *
 * @ingroup views_area_handlers
 *
 * @ViewsArea("manage_registrations_empty")
 */
class ManageRegistrationsEmpty extends AreaPluginBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): ManageRegistrationsEmpty {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function render($empty = FALSE): array {
    if (!$empty || !empty($this->options['empty'])) {
      $storage = $this->entityTypeManager->getStorage($this->view->args[0]);
      if ($storage && ($entity = $storage->load($this->view->args[1]))) {
        return [
          '#markup' => $this->t('There are no registrants for %name', [
            '%name' => $entity->label(),
          ]),
        ];
      }
    }
    return [];
  }

}
