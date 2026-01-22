<?php

namespace Drupal\registration\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\PagerSelectExtender;
use Drupal\Core\Database\Query\TableSortExtender;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RedirectDestinationTrait;
use Drupal\Core\Url;
use Drupal\registration\Entity\RegistrationInterface;
use Drupal\registration\HostEntityInterface;
use Drupal\registration\RegistrationManagerInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for Registration routes.
 */
class RegistrationController extends ControllerBase {

  use RedirectDestinationTrait;

  /**
   * The database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * The registration manager.
   *
   * @var \Drupal\registration\RegistrationManagerInterface
   */
  protected RegistrationManagerInterface $registrationManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): RegistrationController {
    $instance = parent::create($container);
    $instance->database = $container->get('database');
    $instance->dateFormatter = $container->get('date.formatter');
    $instance->registrationManager = $container->get('registration.manager');
    return $instance;
  }

  /**
   * Displays the Manage Registrations task.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return array
   *   A render array as expected by drupal_render().
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function manageRegistrations(Request $request): array {
    $build = [];

    // Retrieve the host entity.
    if ($host_entity = $this->registrationManager->getEntityFromParameters($request->attributes, TRUE)) {
      $view = NULL;
      $access_result = NULL;

      // Use the built-in manage registrations view if available.
      if ($this->moduleHandler()->moduleExists('views')) {
        if ($view = $this->entityTypeManager()->getStorage('view')->load('manage_registrations')) {
          $display = 'block_1';
          if ($view->getExecutable()->access($display)) {
            $build = [
              '#type' => 'view',
              '#name' => 'manage_registrations',
              '#display_id' => $display,
              '#arguments' => [
                $host_entity->getEntityTypeId(),
                $host_entity->id(),
              ],
            ];
            $build['#attached']['library'][] = 'registration/manage_registrations';
          }
        }
      }

      // Fallback to data table.
      if (empty($build)) {
        $handler = $this->entityTypeManager()->getHandler($host_entity->getEntityTypeId(), 'registration_host_access');
        $access_result = $handler->access($host_entity, 'view registrations', $this->currentUser(), TRUE);

        if ($access_result->isAllowed()) {
          $build = $this->buildDataTable($host_entity);
        }
        else {
          // The user cannot view registrations, so show a summary instead.
          $build = $this->buildSummary($host_entity);
        }
      }

      // Set cache directives so the task rebuilds when needed.
      $cacheability = CacheableMetadata::createFromObject($host_entity);
      if ($view) {
        $cacheability->addCacheableDependency($view);
      }
      if ($access_result) {
        $cacheability->addCacheableDependency($access_result);
      }
      $cacheability->addCacheContexts(['user.permissions']);
      $cacheability->applyTo($build);
    }

    return $build;
  }

  /**
   * Displays the Users Registrations task.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return array
   *   A render array as expected by drupal_render().
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function userRegistrations(Request $request): array {
    $build = [];
    $view = NULL;

    /** @var \Drupal\user\UserInterface $user */
    if ($user = $this->registrationManager->getEntityFromParameters($request->attributes)) {
      // Use the built-in user registrations view if available.
      if ($this->moduleHandler()->moduleExists('views')) {
        if ($view = $this->entityTypeManager()->getStorage('view')->load('user_registrations')) {
          $display = 'block_1';
          if ($view->getExecutable()->access($display)) {
            $build = [
              '#type' => 'view',
              '#name' => 'user_registrations',
              '#display_id' => $display,
              '#arguments' => [
                $user->id(),
              ],
            ];
          }
        }
      }

      // Fallback to data table.
      if (empty($build)) {
        $access_result = AccessResult::allowedIfHasPermissions($this->currentUser(), [
          "administer registration",
          "view any registration",
        ], 'OR');
        if (!$access_result->isAllowed() && $this->currentUser()->isAuthenticated() && ($user->id() == $this->currentUser()->id())) {
          $access_result = AccessResult::allowedIfHasPermissions($this->currentUser(), [
            "view own registration",
          ]);
        }
        if ($access_result->isAllowed()) {
          $build = $this->buildUserDataTable($user);
        }
      }

      // Set cache directives so the form rebuilds when needed.
      $renderer = \Drupal::service('renderer');
      $renderer->addCacheableDependency($build, $user);
      if ($view) {
        $renderer->addCacheableDependency($build, $view);
      }

      // Rebuild when registrations for this user are added and deleted.
      $build['#cache']['tags'][] = 'registration.user:' . $user->id();

      // Rebuild when user permissions change.
      $build['#cache']['contexts'][] = 'user.permissions';
    }

    return $build;
  }

  /**
   * Builds the Manage Registrations data table.
   *
   * @param \Drupal\registration\HostEntityInterface $host_entity
   *   The host entity.
   *
   * @return array
   *   A render array as expected by drupal_render().
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  protected function buildDataTable(HostEntityInterface $host_entity): array {
    $capacity = $host_entity->getSetting('capacity');
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

    $header = [
      [
        'data' => $this->t('Id'),
        'field' => 'r.registration_id',
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      [
        'data' => $this->t('Email'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      [
        'data' => $this->t('User'),
        'field' => 'r.user_uid',
        'class' => [RESPONSIVE_PRIORITY_MEDIUM],
      ],
      [
        'data' => $this->t('Created By'),
        'field' => 'r.author_uid',
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      [
        'data' => $this->t('Spaces'),
        'field' => 'r.count',
        'class' => [RESPONSIVE_PRIORITY_MEDIUM],
        'initial_click_sort' => 'desc',
      ],
      [
        'data' => $this->t('Created'),
        'field' => 'r.created',
        'sort' => 'desc',
        'class' => [RESPONSIVE_PRIORITY_MEDIUM],
      ],
      [
        'data' => $this->t('Status'),
        'field' => 'r.state',
        'class' => [RESPONSIVE_PRIORITY_MEDIUM],
      ],
      [
        'data' => $this->t('Operations'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
    ];

    $rows = [];

    $registration_storage = $this->entityTypeManager()->getStorage('registration');

    /** @var \Drupal\Core\Database\Query\TableSortExtender $query */
    $query = $this->database->select('registration', 'r')
      ->extend(PagerSelectExtender::class)
      ->extend(TableSortExtender::class);
    $query->fields('r', [
      'registration_id',
      'anon_mail',
      'count',
      'user_uid',
      'author_uid',
      'state',
      'created',
    ]);
    $query->condition('r.entity_type_id', $host_entity->getEntityTypeId());
    $query->condition('r.entity_id', $host_entity->id());
    $query->addTag('registration_access');
    $result = $query
      ->limit(20)
      ->orderByHeader($header)
      ->execute();

    // Add the rows to the table.
    foreach ($result as $record) {
      /** @var \Drupal\registration\Entity\RegistrationInterface $registration */
      $registration = $registration_storage->load($record->registration_id);

      // User.
      if ($user = $registration->getUser()) {
        $user = [
          '#theme' => 'username',
          '#account' => $user,
        ];
      }
      // Author.
      if ($author = $registration->getAuthor()) {
        $author = [
          '#theme' => 'username',
          '#account' => $author,
        ];
      }
      else {
        // No author entity, this returns Anonymous.
        // This case occurs for an anonymous self registration.
        $author = $registration->getAuthorDisplayName();
      }

      $rows[] = [
        'data' => [
          ['data' => Link::fromTextAndUrl($registration->id(), $registration->toUrl())],
          ['data' => $registration->getEmail()],
          ['data' => $user],
          ['data' => $author],
          ['data' => $registration->getSpacesReserved()],
          ['data' => $this->dateFormatter->format($registration->getCreatedTime(), 'short')],
          ['data' => $registration->getState()->label()],
          ['data' => $this->getOperations($registration)],
        ],
      ];
    }

    // The caption and table header aren't needed when there is no data.
    if (empty($rows)) {
      $caption = [];
      $header = [];
    }

    // Build the table.
    $build['registration_table'] = [
      '#type' => 'table',
      '#caption' => $caption,
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('There are no registrants for %name', [
        '%name' => $host_entity->label(),
      ]),
    ];
    $build['registration_pager'] = ['#type' => 'pager'];

    return $build;
  }

  /**
   * Builds the User Registrations data table.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user whose registrations are being displayed.
   *
   * @return array
   *   A render array as expected by drupal_render().
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  protected function buildUserDataTable(UserInterface $user): array {
    $header = [
      [
        'data' => $this->t('Id'),
        'field' => 'r.registration_id',
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      [
        'data' => $this->t('Type'),
        'field' => 'r.type',
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      [
        'data' => $this->t('Spaces'),
        'field' => 'r.count',
        'class' => [RESPONSIVE_PRIORITY_MEDIUM],
        'initial_click_sort' => 'desc',
      ],
      [
        'data' => $this->t('Created'),
        'field' => 'r.created',
        'sort' => 'desc',
        'class' => [RESPONSIVE_PRIORITY_MEDIUM],
      ],
      [
        'data' => $this->t('Status'),
        'field' => 'r.state',
        'class' => [RESPONSIVE_PRIORITY_MEDIUM],
      ],
      [
        'data' => $this->t('Operations'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
    ];

    $rows = [];

    $registration_storage = $this->entityTypeManager()->getStorage('registration');

    /** @var \Drupal\Core\Database\Query\TableSortExtender $query */
    $query = $this->database->select('registration', 'r')
      ->extend(PagerSelectExtender::class)
      ->extend(TableSortExtender::class);
    $query->fields('r', [
      'registration_id',
    ]);
    $query->condition('r.user_uid', $user->id());
    $query->addTag('registration_access');
    $result = $query
      ->limit(20)
      ->orderByHeader($header)
      ->execute();

    // Add the rows to the table.
    foreach ($result as $record) {
      /** @var \Drupal\registration\Entity\RegistrationInterface $registration */
      $registration = $registration_storage->load($record->registration_id);

      $rows[] = [
        'data' => [
          ['data' => Link::fromTextAndUrl($registration->id(), $registration->toUrl())],
          ['data' => $registration->getType()->label()],
          ['data' => $registration->getSpacesReserved()],
          ['data' => $this->dateFormatter->format($registration->getCreatedTime(), 'short')],
          ['data' => $registration->getState()->label()],
          ['data' => $this->getOperations($registration)],
        ],
      ];
    }

    // Build the table.
    $build['registration_table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
    ];
    $build['registration_pager'] = ['#type' => 'pager'];

    return $build;
  }

  /**
   * Builds the Manage Registrations data table as a simple summary.
   *
   * @param \Drupal\registration\HostEntityInterface $host_entity
   *   The host entity.
   *
   * @return array
   *   A render array as expected by drupal_render().
   */
  protected function buildSummary(HostEntityInterface $host_entity): array {
    $capacity = $host_entity->getSetting('capacity');
    $spaces = $host_entity->getActiveSpacesReserved();
    if ($capacity) {
      $caption = $this->formatPlural($capacity,
        'Registration summary for %label: @spaces of 1 space is filled.',
        'Registration summary for %label: @spaces of @count spaces are filled.', [
          '%label' => $host_entity->label(),
          '@capacity' => $capacity,
          '@spaces' => $spaces,
        ]);
    }
    else {
      $caption = $this->formatPlural($spaces,
        'Registration summary for %label: 1 space is filled.',
        'Registration summary for %label: @count spaces are filled.', [
          '%label' => $host_entity->label(),
        ]);
    }
    $build['registration_table'] = [
      '#markup' => $caption,
    ];
    return $build;
  }

  /**
   * Get the entity operations for a given registration.
   *
   * @param \Drupal\registration\Entity\RegistrationInterface $registration
   *   The registration.
   *
   * @return array
   *   A render array as expected by drupal_render().
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  protected function getOperations(RegistrationInterface $registration): array {
    $prefix = FALSE;
    $operations = [];
    if ($registration->access('view') && $registration->hasLinkTemplate('canonical')) {
      $operations['view'] = [
        '#type' => 'link',
        '#title' => $this->t('View'),
        '#url' => $this->ensureDestination($registration->toUrl()),
      ];
      $prefix = TRUE;
    }
    if ($registration->access('update') && $registration->hasLinkTemplate('edit-form')) {
      $operations['edit'] = [
        '#type' => 'link',
        '#title' => $this->t('Edit'),
        '#url' => $this->ensureDestination($registration->toUrl('edit-form')),
        '#prefix' => $prefix ? ' | ' : '',
      ];
      $prefix = TRUE;
    }
    if ($registration->access('delete') && $registration->hasLinkTemplate('delete-form')) {
      $operations['delete'] = [
        '#type' => 'link',
        '#title' => $this->t('Delete'),
        '#url' => $this->ensureDestination($registration->toUrl('delete-form')),
        '#prefix' => $prefix ? ' | ' : '',
      ];
    }
    return $operations;
  }

  /**
   * Update a URL with a destination.
   *
   * @param \Drupal\Core\Url $url
   *   The url.
   *
   * @return \Drupal\Core\Url
   *   The updated URL object.
   */
  protected function ensureDestination(Url $url): Url {
    return $url
      ->mergeOptions([
        'query' => $this
          ->getRedirectDestination()
          ->getAsArray(),
      ]);
  }

}
