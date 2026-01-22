<?php

namespace Drupal\registration\Plugin\views\field;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\registration\Plugin\views\UserContextualFilterTrait;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\field\Boolean;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;

/**
 * Field handler to display if a user account is registered for a host entity.
 *
 * This plugin requires a contextual filter containing a User ID. The
 * recommended setup when the host entities are nodes is a content listing at
 * path "/user/%user/content", and a Global: Null contextual filter with User ID
 * validation enabled.
 *
 * @ViewsField("host_entity_user_is_registered")
 */
class HostEntityUserIsRegistered extends Boolean {

  use UserContextualFilterTrait;

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, ?array &$options = NULL): void {
    parent::init($view, $display, $options);

    $default_formats = [
      'yes-no' => [$this->t('Yes'), $this->t('No')],
      'true-false' => [$this->t('True'), $this->t('False')],
      'on-off' => [$this->t('On'), $this->t('Off')],
      'enabled-disabled' => [$this->t('Registered'), $this->t('Not registered')],
      'boolean' => [1, 0],
      'unicode-yes-no' => ['✔', '✖'],
    ];
    $output_formats = $this->definition['output formats'] ?? [];
    $custom_format = ['custom' => [$this->t('Custom')]];
    $this->formats = array_merge($default_formats, $output_formats, $custom_format);
  }

  /**
   * {@inheritdoc}
   */
  public function clickSortable(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    // Leave empty to avoid a query on this field.
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values): array {
    $build['#markup'] = parent::render($values);
    if ($entity = $this->getEntity($values)) {
      $handler = $this->entityTypeManager->getHandler($entity->getEntityTypeId(), 'registration_host_entity');
      $host_entity = $handler->createHostEntity($entity);
      // Rebuild when the host entity changes.
      $cacheability = CacheableMetadata::createFromObject($host_entity);
      if ($host_entity->isConfiguredForRegistration()) {
        // Rebuild when registrations are added or removed for this host entity.
        $cacheability->addCacheTags([$host_entity->getRegistrationListCacheTag()]);
      }
      $cacheability->applyTo($build);
    }
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getValue(ResultRow $values, $field = NULL): bool {
    $position = $this->options['user_argument'];
    $uid = $this->view->args[$position] ?? NULL;
    if ($uid && ($entity = $this->getEntity($values))) {
      if ($user = $this->entityTypeManager->getStorage('user')->load($uid)) {
        $handler = $this->entityTypeManager->getHandler($entity->getEntityTypeId(), 'registration_host_entity');
        $host_entity = $handler->createHostEntity($entity);
        return $host_entity->isRegistrant($user, NULL, array_filter($this->options['registration_states']));
      }
    }
    return FALSE;
  }

}
