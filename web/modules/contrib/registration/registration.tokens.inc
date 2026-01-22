<?php

/**
 * @file
 * Builds placeholder replacement tokens for registration-related data.
 */

use Drupal\Core\Datetime\Entity\DateFormat;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\user\Entity\User;

/**
 * Implements hook_token_info_alter().
 */
function registration_token_info_alter(&$info) {
  // If the token module is installed, add a few tokens that it doesn't already
  // provide or doesn't handle properly.
  if (\Drupal::moduleHandler()->moduleExists('token')) {
    $info['tokens']['registration']['entity'] = [
      'name' => t('Host entity (real)'),
      'description' => t('The host entity for the registration. Chains to core entity tokens.'),
    ];
    $info['tokens']['registration']['host-entity'] = [
      'name' => t('Host entity (pseudo)'),
      'description' => t('The host entity for the registration. Chains to host entity tokens.'),
      'type' => 'registration_host_entity',
    ];
    $info['tokens']['registration']['settings'] = [
      'name' => t('Settings'),
      'description' => t('The registration settings for the host entity.'),
      'type' => 'registration_settings',
    ];
    $info['tokens']['registration']['id'] = [
      'name' => t('ID'),
      'description' => t('The unique identifier for the registration.'),
    ];
    $info['tokens']['registration']['label'] = [
      'name' => t('Label'),
      'description' => t('The registration label.'),
    ];
    $info['tokens']['registration']['state'] = [
      'name' => t('Status'),
      'description' => t('The registration status.'),
    ];
    $info['tokens']['registration']['type'] = [
      'name' => t('Type ID'),
      'description' => t('The registration type ID.'),
    ];
    $info['tokens']['registration']['type-name'] = [
      'name' => t('Type name'),
      'description' => t('The registration type name.'),
    ];
    $info['tokens']['registration_settings']['id'] = [
      'name' => t('ID'),
      'description' => t('The unique ID for the registration settings entity. A new settings entity is saved if needed.'),
    ];
    $info['tokens']['registration_settings']['multiple_registrations'] = [
      'name' => t('Allow multiple registrations'),
      'description' => t('Whether registrants can create multiple registrations ("Yes" or "No").'),
    ];
    $info['tokens']['registration_settings']['confirmation'] = [
      'name' => t('Confirmation message'),
      'description' => t('The message to display when someone registers.'),
    ];
  }

  // Add host entity tokens for every registration enabled entity type.
  foreach (\Drupal::service('registration.manager')->getRegistrationEnabledEntityTypes() as $entity_type) {
    $info['tokens'][$entity_type->id()]['registration-host-entity'] = [
      'name' => t('Registration host entity'),
      'description' => t('The entity as a host entity for registration.'),
      'type' => 'registration_host_entity',
    ];
  }
}

/**
 * Implements hook_token_info().
 */
function registration_token_info() {
  $types['registration_host_entity'] = [
    'name' => t('Registration host entity'),
    'description' => t('Tokens related to registration host entities.'),
    'needs-data' => 'registration_host_entity',
  ];
  $host_entity['id'] = [
    'name' => t('ID'),
    'description' => t('The host entity ID.'),
  ];
  $host_entity['type'] = [
    'name' => t('Type ID'),
    'description' => t('The host entity type ID, e.g. "node".'),
  ];
  $host_entity['is-after-close'] = [
    'name' => t('Is after close'),
    'description' => t('Whether it is after the close date, if one is set in registration settings ("Yes" or "No").'),
  ];
  $host_entity['is-before-open'] = [
    'name' => t('Is before open'),
    'description' => t('Whether it is before the open date, if one is set in registration settings ("Yes" or "No").'),
  ];
  $host_entity['is-available'] = [
    'name' => t('Is available for registration'),
    'description' => t('Whether new registrations are allowed for the host entity ("Yes" or "No").'),
  ];
  $host_entity['is-configured'] = [
    'name' => t('Is configured for registration'),
    'description' => t('Whether the host entity is configured for registration ("Yes" or "No").'),
  ];
  $host_entity['registration-count'] = [
    'name' => t('Registration count'),
    'description' => t('The total number of registrations for the host entity, regardless of status.'),
  ];
  $host_entity['registration-list'] = [
    'name' => t('Registration list'),
    'description' => t('The list of registrations for the host entity.'),
  ];
  $host_entity['spaces-reserved'] = [
    'name' => t('Spaces reserved'),
    'description' => t('The number of spaces reserved by active registrations.'),
  ];
  $host_entity['spaces-remaining'] = [
    'name' => t('Spaces remaining'),
    'description' => t('The number of spaces remaining if there is a capacity limit.'),
  ];
  $host_entity['settings'] = [
    'name' => t('Registration settings'),
    'description' => t('The registration settings.'),
    'type' => 'registration_settings',
  ];

  // When the token module is installed, most of these tokens are already
  // defined, and the ones that aren't are defined by the alter hook above.
  if (!\Drupal::moduleHandler()->moduleExists('token')) {
    $types['registration'] = [
      'name' => t('Registration'),
      'description' => t('Tokens related to registrations.'),
      'needs-data' => 'registration',
    ];
    $registration['id'] = [
      'name' => t('ID'),
      'description' => t('The unique identifier for the registration.'),
    ];
    $registration['count'] = [
      'name' => t('Spaces'),
      'description' => t('The number of spaces reserved by the registration.'),
    ];
    $registration['label'] = [
      'name' => t('Label'),
      'description' => t('The registration label.'),
    ];
    $registration['mail'] = [
      'name' => t('Email'),
      'description' => t('The email address for the registration.'),
    ];
    $registration['state'] = [
      'name' => t('Status'),
      'description' => t('The registration status.'),
    ];
    $registration['type'] = [
      'name' => t('Type ID'),
      'description' => t('The registration type ID.'),
    ];
    $registration['type-name'] = [
      'name' => t('Type name'),
      'description' => t('The registration type name.'),
    ];

    // Chained tokens for registrations.
    $registration['author'] = [
      'name' => t('Author'),
      'type' => 'user',
    ];
    $registration['created'] = [
      'name' => t("Date created"),
      'type' => 'date',
    ];
    $registration['changed'] = [
      'name' => t("Date changed"),
      'description' => t("The date the registration was most recently updated."),
      'type' => 'date',
    ];
    $registration['completed'] = [
      'name' => t("Date completed"),
      'description' => t("The date the registration was completed."),
      'type' => 'date',
    ];
    $registration['entity'] = [
      'name' => t('Host entity (real)'),
      'description' => t('The host entity for the registration. Chains to core entity tokens.'),
    ];
    $registration['host-entity'] = [
      'name' => t('Host entity (pseudo)'),
      'description' => t('The host entity for the registration. Chains to host entity tokens.'),
    ];
    $registration['settings'] = [
      'name' => t('Registration settings'),
      'type' => 'registration_settings',
    ];
    $registration['user'] = [
      'name' => t('User'),
      'type' => 'user',
    ];

    // Registration settings.
    $types['registration_settings'] = [
      'name' => t('Registration settings'),
      'description' => t('Tokens related to registration settings.'),
      'needs-data' => 'registration_settings',
    ];
    $registration_settings['id'] = [
      'name' => t('ID'),
      'description' => t('The unique ID for the registration settings entity. A new settings entity is saved if needed.'),
    ];
    $registration_settings['status'] = [
      'name' => t('Enabled'),
      'description' => t('The status ("Enabled" or "Disabled").'),
    ];
    $registration_settings['open'] = [
      'name' => t('Open date'),
      'description' => t('The open date.'),
    ];
    $registration_settings['close'] = [
      'name' => t('Close date'),
      'description' => t('The close date.'),
    ];
    $registration_settings['send_reminder'] = [
      'name' => t('Send reminder'),
      'description' => t('Whether a reminder will be sent to registrants on the reminder date ("Yes" or "No").'),
    ];
    $registration_settings['reminder_date'] = [
      'name' => t('Reminder date'),
      'description' => t('The reminder date.'),
    ];
    $registration_settings['capacity'] = [
      'name' => t('Capacity'),
      'description' => t('The total capacity, if a capacity limit is set.'),
    ];
    $registration_settings['maximum_spaces'] = [
      'name' => t('Maximum spaces'),
      'description' => t('The maximum number of spaces allowed for each registration.'),
    ];
    $registration_settings['multiple_registrations'] = [
      'name' => t('Allow multiple registrations'),
      'description' => t('Whether registrants can create multiple registrations ("Yes" or "No").'),
    ];
    $registration_settings['from_address'] = [
      'name' => t('From address'),
      'description' => t('From email address to use for confirmations, reminders, and broadcast emails.'),
    ];
    $registration_settings['confirmation'] = [
      'name' => t('Confirmation message'),
      'description' => t('The message to display when someone registers.'),
    ];
    $registration_settings['confirmation_redirect'] = [
      'name' => t('Confirmation redirect path'),
      'description' => t('The path to redirect to when someone registers.'),
    ];

    return [
      'types' => $types,
      'tokens' => [
        'registration' => $registration,
        'registration_settings' => $registration_settings,
        'registration_host_entity' => $host_entity,
      ],
    ];
  }

  // The token module is installed, so return just the host entity tokens.
  // Registration tokens are handled by the token module in concert with
  // the alter hook above.
  return [
    'types' => $types,
    'tokens' => [
      'registration_host_entity' => $host_entity,
    ],
  ];
}

/**
 * Implements hook_tokens().
 */
function registration_tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
  $token_service = \Drupal::token();

  if (isset($options['langcode'])) {
    $langcode = $options['langcode'];
  }
  else {
    $langcode = \Drupal::languageManager()
      ->getCurrentLanguage()
      ->getId();
  }

  $replacements = [];

  // The registration tokens.
  if ($type == 'registration' && !empty($data['registration'])) {
    /** @var \Drupal\registration\Entity\RegistrationInterface $registration */
    $registration = $data['registration'];

    // Simple tokens.
    foreach ($tokens as $name => $original) {
      switch ($name) {
        case 'id':
          $replacements[$original] = $registration->id();
          break;

        case 'count':
          // Defer to the token module when installed.
          if (!\Drupal::moduleHandler()->moduleExists('token')) {
            $replacements[$original] = $registration->getSpacesReserved();
          }
          break;

        case 'label':
          $replacements[$original] = $registration->label();
          break;

        case 'mail':
          // Defer to the token module when installed.
          if (!\Drupal::moduleHandler()->moduleExists('token')) {
            $replacements[$original] = $registration->getEmail();
          }
          break;

        case 'state':
          $replacements[$original] = $registration->getState()?->label();
          break;

        case 'type':
          $replacements[$original] = $registration->getType()->id();
          break;

        case 'type-name':
          $replacements[$original] = $registration->getType()->label();
          break;

        // Default values for the chained tokens handled below.
        case 'author':
          // Defer to the token module when installed.
          if (!\Drupal::moduleHandler()->moduleExists('token')) {
            $account = $registration->getAuthor() ? $registration->getAuthor() : User::load(0);
            $bubbleable_metadata->addCacheableDependency($account);
            $replacements[$original] = $account->label();
          }
          break;

        case 'created':
          // Defer to the token module when installed.
          if (!\Drupal::moduleHandler()->moduleExists('token')) {
            $date_format = DateFormat::load('medium');
            $bubbleable_metadata->addCacheableDependency($date_format);
            $replacements[$original] = \Drupal::service('date.formatter')->format($registration->getCreatedTime(), 'medium', '', NULL, $langcode);
          }
          break;

        case 'changed':
          // Defer to the token module when installed.
          if (!\Drupal::moduleHandler()->moduleExists('token')) {
            $date_format = DateFormat::load('medium');
            $bubbleable_metadata->addCacheableDependency($date_format);
            $replacements[$original] = \Drupal::service('date.formatter')->format($registration->getChangedTime(), 'medium', '', NULL, $langcode);
          }
          break;

        case 'completed':
          // Defer to the token module when installed.
          if (!\Drupal::moduleHandler()->moduleExists('token')) {
            if ($registration->isComplete()) {
              $date_format = DateFormat::load('medium');
              $bubbleable_metadata->addCacheableDependency($date_format);
              $replacements[$original] = \Drupal::service('date.formatter')->format($registration->getCompletedTime(), 'medium', '', NULL, $langcode);
            }
          }
          break;

        case 'entity':
          if ($entity = $registration->getHostEntity()?->getEntity()) {
            $bubbleable_metadata->addCacheableDependency($entity);
            $replacements[$original] = $entity->label();
          }
          break;

        case 'host-entity':
          if ($host_entity = $registration->getHostEntity()) {
            $bubbleable_metadata->addCacheableDependency($host_entity);
            $replacements[$original] = $host_entity->label();
          }
          break;

        case 'user':
          // Defer to the token module when installed.
          if (!\Drupal::moduleHandler()->moduleExists('token')) {
            $account = $registration->getUser() ? $registration->getUser() : User::load(0);
            $bubbleable_metadata->addCacheableDependency($account);
            $replacements[$original] = $account->label();
          }
          break;
      }
    }

    // Defer to the token module when installed.
    if (!\Drupal::moduleHandler()->moduleExists('token')) {
      // Author tokens at [registration:author:*].
      if ($author_tokens = $token_service->findWithPrefix($tokens, 'author')) {
        $data = ['user' => $registration->getAuthor()];
        $replacements += $token_service->generate('user', $author_tokens, $data, $options, $bubbleable_metadata);
      }

      // Date created tokens at [registration:created:*].
      if ($created_tokens = $token_service->findWithPrefix($tokens, 'created')) {
        $data = ['date' => $registration->getCreatedTime()];
        $replacements += $token_service->generate('date', $created_tokens, $data, $options, $bubbleable_metadata);
      }

      // Date changed tokens at [registration:changed:*].
      if ($changed_tokens = $token_service->findWithPrefix($tokens, 'changed')) {
        $data = ['date' => $registration->getChangedTime()];
        $replacements += $token_service->generate('date', $changed_tokens, $data, $options, $bubbleable_metadata);
      }

      // Date completed tokens at [registration:completed:*].
      if ($completed_tokens = $token_service->findWithPrefix($tokens, 'completed')) {
        $data = ['date' => $registration->getCompletedTime()];
        $replacements += $token_service->generate('date', $completed_tokens, $data, $options, $bubbleable_metadata);
      }

      // User tokens at [registration:user:*].
      if ($user_tokens = $token_service->findWithPrefix($tokens, 'user')) {
        $data = ['user' => $registration->getUser()];
        $replacements += $token_service->generate('user', $user_tokens, $data, $options, $bubbleable_metadata);
      }
    }

    // Registration settings tokens at [registration:settings:*].
    if ($settings_tokens = $token_service->findWithPrefix($tokens, 'settings')) {
      if ($settings = $registration->getHostEntity()?->getSettings()) {
        $data = ['registration_settings' => $settings];
        $replacements += $token_service->generate('registration_settings', $settings_tokens, $data, $options, $bubbleable_metadata);
      }
    }

    // Host entity tokens at [registration:entity:*].
    if ($entity_tokens = $token_service->findWithPrefix($tokens, 'entity')) {
      if ($entity = $registration->getHostEntity()?->getEntity()) {
        $entity_type_id = $entity->getEntityTypeId();
        $data = [$entity_type_id => $entity];
        $replacements += $token_service->generate($entity_type_id, $entity_tokens, $data, $options, $bubbleable_metadata);
      }
    }

    // Host entity (pseudo) tokens at [registration:host-entity:*].
    if ($host_entity_tokens = $token_service->findWithPrefix($tokens, 'host-entity')) {
      if ($host_entity = $registration->getHostEntity()) {
        $data = ['registration_host_entity' => $host_entity];
        $replacements += $token_service->generate('registration_host_entity', $host_entity_tokens, $data, $options, $bubbleable_metadata);
      }
    }
  }

  // The registration settings tokens.
  if ($type == 'registration_settings' && !empty($data['registration_settings'])) {
    /** @var \Drupal\registration\Entity\RegistrationSettings $registration_settings */
    $registration_settings = $data['registration_settings'];

    // Simple tokens.
    foreach ($tokens as $name => $original) {
      switch ($name) {
        case 'id':
          // Ensure the ID token is storage backed, so that the ECA module can
          // load the entity from the token.
          if ($registration_settings->isNew()) {
            $registration_settings->save();
          }
          $replacements[$original] = $registration_settings->id();
          break;

        case 'capacity':
          // Defer to the token module when installed.
          if (!\Drupal::moduleHandler()->moduleExists('token')) {
            $replacements[$original] = $registration_settings->getSetting('capacity');
          }
          break;

        case 'open':
          // Defer to the token module when installed.
          if (!\Drupal::moduleHandler()->moduleExists('token')) {
            if ($open = $registration_settings->getHostEntity()?->getOpenDate()) {
              $date_format = DateFormat::load('medium');
              $bubbleable_metadata->addCacheableDependency($date_format);
              $replacements[$original] = $open->format($date_format->getPattern(), ['langcode' => $langcode]);
            }
          }
          break;

        case 'close':
          // Defer to the token module when installed.
          if (!\Drupal::moduleHandler()->moduleExists('token')) {
            if ($close = $registration_settings->getHostEntity()?->getCloseDate()) {
              $date_format = DateFormat::load('medium');
              $bubbleable_metadata->addCacheableDependency($date_format);
              $replacements[$original] = $close->format($date_format->getPattern(), ['langcode' => $langcode]);
            }
          }
          break;

        case 'send_reminder':
          // Defer to the token module when installed.
          if (!\Drupal::moduleHandler()->moduleExists('token')) {
            $replacements[$original] = $registration_settings->getSetting('send_reminder') ? t('Yes') : t('No');
          }
          break;

        case 'reminder_date':
          // Defer to the token module when installed.
          if (!\Drupal::moduleHandler()->moduleExists('token')) {
            if ($reminder_date = $registration_settings->getHostEntity()?->getReminderDate()) {
              $date_format = DateFormat::load('medium');
              $bubbleable_metadata->addCacheableDependency($date_format);
              $replacements[$original] = $reminder_date->format($date_format->getPattern(), ['langcode' => $langcode]);
            }
          }
          break;

        case 'reminder_template':
          // Defer to the token module when installed.
          if (!\Drupal::moduleHandler()->moduleExists('token')) {
            if ($reminder_template = $registration_settings->getSetting('reminder_template')) {
              $replacements[$original] = $reminder_template;
            }
          }
          break;

        case 'maximum_spaces':
          // Defer to the token module when installed.
          if (!\Drupal::moduleHandler()->moduleExists('token')) {
            $replacements[$original] = $registration_settings->getSetting('maximum_spaces');
          }
          break;

        case 'multiple_registrations':
          $replacements[$original] = $registration_settings->getSetting('multiple_registrations') ? t('Yes') : t('No');
          break;

        case 'status':
          // Defer to the token module when installed.
          if (!\Drupal::moduleHandler()->moduleExists('token')) {
            $replacements[$original] = (bool) $registration_settings->getSetting('status') ? t('Enabled') : t('Disabled');
          }
          break;

        case 'from_address':
          // Defer to the token module when installed.
          if (!\Drupal::moduleHandler()->moduleExists('token')) {
            if ($from_address = $registration_settings->getHostEntity()?->getSetting('from_address')) {
              $replacements[$original] = $from_address;
            }
          }
          break;

        case 'confirmation':
          $replacements[$original] = t('The registration was saved.');
          if ($confirmation = $registration_settings->getHostEntity()?->getSetting('confirmation')) {
            $replacements[$original] = $confirmation;
          }
          elseif ($default_settings = $registration_settings->getHostEntity()?->getDefaultSettings()) {
            if ($confirmation = ($default_settings['confirmation'] ?? NULL)) {
              $replacements[$original] = $confirmation;
            }
          }
          break;

        case 'confirmation_redirect':
          // Defer to the token module when installed.
          if (!\Drupal::moduleHandler()->moduleExists('token')) {
            if ($redirect = $registration_settings->getHostEntity()?->getSetting('confirmation_redirect')) {
              $replacements[$original] = $redirect;
            }
          }
          break;
      }
    }
  }

  // The host entity tokens.
  if ($type == 'registration_host_entity' && !empty($data['registration_host_entity'])) {
    /** @var \Drupal\registration\HostEntityInterface $host_entity */
    $host_entity = $data['registration_host_entity'];

    foreach ($tokens as $name => $original) {
      switch ($name) {
        case 'id':
          $replacements[$original] = $host_entity->id();
          break;

        case 'type':
          $replacements[$original] = $host_entity->getEntityTypeId();
          break;

        case 'is-after-close':
          $replacements[$original] = $host_entity->isAfterClose() ? t('Yes') : t('No');
          break;

        case 'is-before-open':
          $replacements[$original] = $host_entity->isBeforeOpen() ? t('Yes') : t('No');
          break;

        case 'is-available':
          $validation_result = $host_entity->isAvailableForRegistration(TRUE);
          $replacements[$original] = $validation_result->isValid() ? t('Yes') : t('No');
          $bubbleable_metadata->addCacheableDependency($validation_result);
          break;

        case 'is-configured':
          $replacements[$original] = $host_entity->isConfiguredForRegistration() ? t('Yes') : t('No');
          break;

        case 'registration-count':
          $replacements[$original] = $host_entity->getRegistrationCount();
          break;

        case 'registration-list':
          $replacements[$original] = $host_entity->getRegistrationList([], $langcode);
          break;

        case 'spaces-reserved':
          $replacements[$original] = $host_entity->getActiveSpacesReserved();
          break;

        case 'spaces-remaining':
          $replacements[$original] = $host_entity->getSpacesRemaining();
          break;
      }
    }

    // Host entity settings tokens at [host-entity:settings:*].
    if ($settings_tokens = $token_service->findWithPrefix($tokens, 'settings')) {
      if ($settings = $host_entity->getSettings()) {
        $data = ['registration_settings' => $settings];
        $replacements += $token_service->generate('registration_settings', $settings_tokens, $data, $options, $bubbleable_metadata);
      }
    }
  }

  // Host entity tokens at [<entity_type_id>:registration-host-entity:*].
  // For example, [node:registration-host-entity:*], if any node bundles are
  // enabled for registration.
  if ($host_entity_tokens = $token_service->findWithPrefix($tokens, 'registration-host-entity')) {
    foreach (\Drupal::service('registration.manager')->getRegistrationEnabledEntityTypes() as $entity_type) {
      $entity_type_id = $entity_type->id();
      if ($type == $entity_type_id && !empty($data[$entity_type_id])) {
        /** @var \Drupal\Core\Entity\EntityInterface $entity */
        $entity = $data[$entity_type_id];
        $host_entity = \Drupal::entityTypeManager()
          ->getHandler($entity->getEntityTypeId(), 'registration_host_entity')
          ->createHostEntity($entity, $langcode);
        $data = ['registration_host_entity' => $host_entity];
        $replacements += $token_service->generate('registration_host_entity', $host_entity_tokens, $data, $options, $bubbleable_metadata);
      }
    }
  }

  return $replacements;
}
