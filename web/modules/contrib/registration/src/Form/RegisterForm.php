<?php

namespace Drupal\registration\Form;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Drupal\Core\Utility\Token;
use Drupal\registration\Entity\RegistrationInterface;
use Drupal\registration\Event\RegistrationEvents;
use Drupal\registration\Event\RegistrationFormEvent;
use Drupal\registration\Event\RegistrationSaveEvent;
use Drupal\registration\HostEntityInterface;
use Drupal\registration\RegistrationHelper;
use Drupal\registration\RegistrationManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Defines the Register form.
 */
class RegisterForm extends ContentEntityForm {

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Contracts\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * The registration manager.
   *
   * @var \Drupal\registration\RegistrationManagerInterface
   */
  protected RegistrationManagerInterface $registrationManager;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected Token $token;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): RegisterForm {
    $instance = parent::create($container);
    $instance->dateFormatter = $container->get('date.formatter');
    $instance->eventDispatcher = $container->get('event_dispatcher');
    $instance->languageManager = $container->get('language_manager');
    $instance->logger = $container->get('registration.logger');
    $instance->registrationManager = $container->get('registration.manager');
    $instance->token = $container->get('token');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    // Initialize host entity needed by the form.
    $this->setHostEntity($form_state);

    /** @var \Drupal\registration\Entity\RegistrationInterface $registration */
    $registration = $this->getEntity();
    $form_state->set('registration', $registration);

    // Make sure registration is still allowed. Although access control often
    // prevents access to the register route when registration would fail, the
    // passing of time can make the access control cache "stale" if the close
    // date is reached just prior to the form being displayed, and in other
    // edge cases. The ability to register, or update an existing registration,
    // is checked again here. If there are errors, registration is prevented by
    // disabling the registration form. This keeps the user from wasting time
    // filling out a form that would never succeed on submit.
    $host_entity = $form_state->get('host_entity');
    if ($registration->isNew()) {
      $validation_result = $host_entity->isAvailableForRegistration(TRUE);
    }
    else {
      $validation_result = $host_entity->isEditableRegistration($registration, NULL, TRUE);
    }

    // Display any errors.
    if (!$validation_result->isValid()) {
      foreach ($validation_result->getViolations() as $violation) {
        $form['notice'][] = [
          '#markup' => '<p class="registration-error">' . $violation->getMessage() . '</p>',
        ];
      }
    }

    // Initialize the form with fields.
    $form = parent::form($form, $form_state);

    // Alter the form.
    self::alterRegisterForm($form, $form_state);

    // If an admin is editing an existing registration use the advanced form.
    $admin_theme = $this->currentUser()->hasPermission('view the administration theme');
    if (!$registration->isNew() && $admin_theme) {
      $this->useAdvancedForm($form);
    }

    // Hide all fields if registration is disabled.
    if (!empty($form['notice'])) {
      foreach (Element::children($form) as $key) {
        if ($key != 'notice') {
          $form[$key]['#access'] = FALSE;
        }
      }
    }

    // Get the current form cacheability.
    $form_metadata = CacheableMetadata::createFromRenderArray($form);
    // Allow the form to be cached by default.
    $form_metadata->setCacheMaxAge(Cache::PERMANENT);
    // Add the cacheability of the validation result.
    $form_metadata->addCacheableDependency($validation_result);

    // The registrant options depend on user permissions or anonymous session.
    if ($this->currentUser()->isAnonymous()) {
      $form_metadata->addCacheContexts(['session']);
    }
    else {
      $form_metadata->addCacheContexts(['user.permissions']);
    }

    // Apply the calculated cacheability to the form.
    $form_metadata->applyTo($form);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Cleanup the form state if the person registering has access to
    // different types of registrations and had toggled both into the
    // form at different points in time before submitting.
    switch ($form_state->getValue('who_is_registering')) {
      case RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_ANON:
        // Anonymous email. Clear the user if present.
        if ($form_state->hasValue('user_uid')) {
          $form_state->unsetValue('user_uid');
          $this->entity->set('user_uid', NULL);
        }
        break;

      case RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_ME:
        // Self registration. Clear both anonymous email and user account.
        if ($form_state->hasValue('anon_mail')) {
          $form_state->unsetValue('anon_mail');
          $this->entity->set('anon_mail', NULL);
        }
        if ($form_state->hasValue('user_uid')) {
          $form_state->unsetValue('user_uid');
        }
        // Validate the current user.
        $this->entity->set('user_uid', $this->currentUser()->id());
        break;

      case RegistrationInterface:: REGISTRATION_REGISTRANT_TYPE_USER:
        // User account. Clear the anonymous email if present.
        if ($form_state->hasValue('anon_mail')) {
          $form_state->unsetValue('anon_mail');
          $this->entity->set('anon_mail', NULL);
        }
        break;
    }

    // The parent validation includes entity validation, which handles most of
    // the validation checks through a constraint.
    // @see \Drupal\registration\Plugin\Validation\Constraint\RegistrationConstraintValidator
    parent::validateForm($form, $form_state);

    // Validate according to who is registering.
    switch ($form_state->getValue('who_is_registering')) {
      case RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_ANON:
        if (!$form_state->hasValue('anon_mail')) {
          // The site admin may need to add the email field to the form display.
          $form_state->setError($form, $this->t('Email address is required.'));
        }
        break;

      case RegistrationInterface:: REGISTRATION_REGISTRANT_TYPE_USER:
        if ($form_state->hasValue('user_uid')) {
          $uid = $form_state->getValue('user_uid')[0]['target_id'];
          /** @var \Drupal\user\UserInterface $user */
          $user = $this->entityTypeManager->getStorage('user')->load($uid);
          if (!$user) {
            if ($this->currentUser()->hasPermission('access user profiles')) {
              // The user may have been deleted just before saving this
              // registration.
              $form_state->setError($form['user_uid'], $this->t('The selected user is no longer available.'));
            }
            else {
              // General failure. Possible permissions issue.
              $form_state->setError($form['user_uid'], $this->t('Registration Failed.'));
            }
          }
        }
        else {
          // The site admin may need to add the user field to the form display.
          $form_state->setError($form, $this->t('User name is required.'));
        }
        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    /** @var \Drupal\registration\Entity\RegistrationInterface $registration */
    $registration = $this->getEntity();
    $host_entity = $form_state->get('host_entity');

    // Set the user when self-registering.
    if ($form_state->getValue('who_is_registering') == RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_ME) {
      $registration->set('user_uid', $this->currentUser()->id());
    }

    // Ensure either user or anonymous email is set, but not both.
    if ($form_state->getValue('who_is_registering') == RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_ANON) {
      $registration->set('user_uid', NULL);
    }
    else {
      $registration->set('anon_mail', NULL);
    }

    // Track if new for logging.
    $is_new = $registration->isNew();

    // Track the desired target state in case it changes during save.
    $target_state_id = $registration->getState()->id();

    // Save the registration.
    $return = $registration->save();

    // Allow other modules to override the logging.
    $event = new RegistrationSaveEvent([
      'is_new' => $is_new,
      'registration' => $registration,
      'host_entity' => $host_entity,
    ]);
    $this->eventDispatcher->dispatch($event, RegistrationEvents::REGISTRATION_SAVE_LOG);

    // Log it unless a different module already logged it.
    if (!$event->wasHandled()) {
      if ($is_new) {
        if ($user = $registration->getUser()) {
          $this->logger->info('@name registered for %label (ID #@id).', [
            '@name' => $user->getDisplayName(),
            '%label' => $host_entity->label(),
            '@id' => $registration->id(),
          ]);
        }
        else {
          $this->logger->info('@email registered for %label (ID #@id).', [
            '@email' => $registration->getEmail(),
            '%label' => $host_entity->label(),
            '@id' => $registration->id(),
          ]);
        }
      }
      else {
        $this->logger->info('The registration for %label (ID #@id) was saved.', [
          '%label' => $host_entity->label(),
          '@id' => $registration->id(),
        ]);
      }
    }

    // Confirmation message.
    $settings = $host_entity->getSettings();
    $confirmation = $settings->getSetting('confirmation');
    if (!$confirmation) {
      $confirmation = $this->t('The registration was saved.');
    }

    // Allow other modules to override the confirmation message.
    $event = new RegistrationSaveEvent([
      'is_new' => $is_new,
      'registration' => $registration,
      'host_entity' => $host_entity,
      'confirmation' => $confirmation,
      'target_state_id' => $target_state_id,
    ]);
    $this->eventDispatcher->dispatch($event, RegistrationEvents::REGISTRATION_SAVE_CONFIRMATION);
    if (!$event->wasHandled()) {
      $this->messenger()->addStatus($confirmation);
    }

    // Redirect.
    $redirect = $settings->getSetting('confirmation_redirect');
    if ($redirect) {
      // Replace tokens in the redirect field if there are any.
      $redirect = $this->replaceTokens($host_entity, $registration, $redirect);

      // Custom redirect in the settings.
      // Check for external first.
      if (UrlHelper::isExternal($redirect)) {
        // To be considered external, the URL helper checks
        // for dangerous protocols, so the redirect must be safe.
        // Sanitize and use it.
        $redirect = Html::escape($redirect);
        $response = new TrustedRedirectResponse($redirect);
        $form_state->setResponse($response);
      }
      else {
        // Potentially unsafe URL. Try for an internal redirect.
        $redirect = UrlHelper::stripDangerousProtocols($redirect);
        $redirect = Html::escape($redirect);
        $form_state->setRedirectUrl(Url::fromUserInput($redirect));
      }
    }
    else {
      // No redirect in the settings.
      if ($registration->access('view', $this->currentUser())) {
        // User has permission to view their registration. Redirect to the
        // registration page. Must be explicit about language here, otherwise
        // would redirect to a page in the wrong language.
        $form_state->setRedirectUrl($registration->toUrl('canonical', [
          'language' => $this->languageManager->getCurrentLanguage(),
        ]));
      }
      else {
        // Fallback to redirecting to the host entity.
        // The user should have permission to view the host
        // entity, otherwise it is unlikely they would be
        // able to reach the register page for that entity.
        $form_state->setRedirectUrl($host_entity->getEntity()->toUrl());
      }
    }

    // Must return the result from the entity save.
    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityFromRouteMatch(RouteMatchInterface $route_match, $entity_type_id) {
    if ($route_match->getRawParameter($entity_type_id) !== NULL) {
      $entity = $route_match->getParameter($entity_type_id);
    }
    else {
      $values = [];
      // Fetch initial values from the host entity.
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
      $host_entity = $this->registrationManager->getEntityFromParameters($route_match->getParameters(), TRUE);
      $values['entity_type_id'] = $host_entity->getEntityTypeId();
      $values['entity_id'] = $host_entity->id();
      if ($bundle_key = $entity_type->getKey('bundle')) {
        $values[$bundle_key] = $host_entity->getRegistrationTypeBundle();
      }
      // Set the current language to record which language was used to register.
      // This is better than using the content language since a translation
      // might not be available for the host entity yet.
      $values['langcode'] = $this->languageManager->getCurrentLanguage()->getId();

      $entity = $this->entityTypeManager->getStorage($entity_type_id)->create($values);
    }

    return $entity;
  }

  /**
   * Alter the register form.
   */
  public static function alterRegisterForm(array &$form, FormStateInterface $form_state) {
    $registration_manager = \Drupal::service('registration.manager');
    $current_user = \Drupal::currentUser();

    $registration = $form_state->get('registration');
    $host_entity = $form_state->get('host_entity');
    $settings = $host_entity->getSettings();

    // Get the registrant options and the default option.
    $registrant_options = $registration_manager->getRegistrantOptions($registration, $settings);
    $default = $registration->getRegistrantType($current_user);
    if (count($registrant_options) == 1) {
      $keys = array_keys($registrant_options);
      $default = reset($keys);
    }

    // Show a message if there's one option as we're going to hide the field.
    if ((count($registrant_options) == 1) && !$current_user->isAnonymous()) {
      if (isset($registrant_options[RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_ME])) {
        $registrant_options[RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_ME] = t('Yourself');
      }
      $message = t('You are registering: %who', ['%who' => current($registrant_options)]);
      $form['who_message'] = [
        '#markup' => '<div class="registration-who-msg">' . $message . '</div>',
        '#weight' => -1,
      ];
    }

    // Add the "Who is registering" field.
    $form['who_is_registering'] = [
      '#type' => 'select',
      '#title' => t('This registration is for:'),
      '#options' => $registrant_options,
      '#default_value' => $default,
      '#required' => TRUE,
      '#access' => (count($registrant_options) > 1),
      '#weight' => -1,
    ];

    // Determine the field name for visibility.
    $name = 'who_is_registering';
    if (!empty($form['#parents'])) {
      $parent_name = '';
      foreach ($form['#parents'] as $index => $parent) {
        $append = $index ? "[$parent]" : $parent;
        $parent_name = $parent_name . $append;
      }
      $name = $parent_name . "[$name]";
    }

    // The following checks for empty form fields, since the site admin
    // may have hidden certain fields on the form via the form display.
    // Set the User field visibility and required states.
    if (!empty($form['user_uid'])) {
      $form['user_uid']['#access'] = isset($registrant_options[RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_USER]);
      $form['user_uid']['#states'] = [
        'visible' => [
          ":input[name='$name']" => ['value' => RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_USER],
        ],
      ];
      // @see https://www.drupal.org/project/drupal/issues/2855139
      $form['user_uid']['widget'][0]['target_id']['#states'] = [
        'required' => [
          ":input[name='$name']" => ['value' => RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_USER],
        ],
      ];
    }

    // Set the Email field visibility and required states.
    if (!empty($form['anon_mail'])) {
      $anonymous_allowed = isset($registrant_options[RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_ANON]);
      $form['anon_mail']['#access'] = $anonymous_allowed;
      $form['anon_mail']['#states'] = [
        'visible' => [
          ":input[name='$name']" => ['value' => RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_ANON],
        ],
      ];
      if ((count($registrant_options) == 1) && $anonymous_allowed) {
        $form['anon_mail']['widget'][0]['value']['#required'] = TRUE;
      }
      else {
        // @see https://www.drupal.org/project/drupal/issues/2855139
        $form['anon_mail']['widget'][0]['value']['#states'] = [
          'required' => [
            ":input[name='$name']" => ['value' => RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_ANON],
          ],
        ];
      }
    }

    // Update the created field.
    $admin_theme = $current_user->hasPermission('view the administration theme');
    if (!empty($form['created'])) {
      // Hide for new registrations or non-admins.
      if ($registration->isNew() || !$admin_theme) {
        $form['created']['#access'] = FALSE;
      }
    }

    // Allow other modules to override the form. This is provided as an
    // alternative to a hook_form_alter since the form ID of the register
    // form may be difficult to determine in all contexts.
    $event = new RegistrationFormEvent($form, $form_state);
    \Drupal::service('event_dispatcher')->dispatch($event, RegistrationEvents::REGISTRATION_ALTER_REGISTER_FORM);
    $form = $event->getForm();
  }

  /**
   * Returns an array of supported actions for the current entity form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   An array of supported Form API action elements keyed by name.
   */
  protected function actions(array $form, FormStateInterface $form_state): array {
    $actions = [];

    // Only display the action buttons if there are no errors.
    if (empty($form['notice'])) {
      $host_entity = $form_state->get('host_entity');
      /** @var \Drupal\registration\Entity\RegistrationInterface $registration */
      $registration = $this->getEntity();
      $count = $registration->getSpacesReserved();
      if (!$registration->isNew() || $host_entity->isAvailableForRegistration()) {
        // Override the button label for the Save button.
        $actions = parent::actions($form, $form_state);
        $actions['submit']['#value'] = $this->t('Save Registration');

        // Ensure language is taken into account for multilingual.
        RegistrationHelper::applyInterfaceLanguageToLinks($actions);

        // Add a Cancel link for new registrations when using the Register tab.
        $host_entity_url = $host_entity->getEntity()->toUrl()->toString();
        $current_url = Url::fromRoute('<current>')->toString();
        if ($registration->isNew() && ($host_entity_url != $current_url)) {
          $actions['cancel'] = [
            '#type' => 'link',
            '#title' => $this->t('Cancel'),
            '#url' => $host_entity->getEntity()->toUrl(),
            '#weight' => 20,
          ];
        }
      }
    }

    return $actions;
  }

  /**
   * Replaces tokens in a string.
   *
   * @param \Drupal\registration\HostEntityInterface $host_entity
   *   The host entity.
   * @param \Drupal\registration\Entity\RegistrationInterface $registration
   *   The registration entity.
   * @param string $input
   *   The input string that may have tokens.
   *
   * @return string
   *   The input string with any tokens replaced.
   */
  protected function replaceTokens(HostEntityInterface $host_entity, RegistrationInterface $registration, string $input): string {
    $entities = [
      $host_entity->getEntityTypeId() => $host_entity->getEntity(),
      'registration' => $registration,
      'registration_settings' => $host_entity->getSettings(),
    ];
    return $this->token->replace($input, $entities, ['clear' => TRUE]);
  }

  /**
   * Ensure the host entity is set.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  protected function setHostEntity(FormStateInterface $form_state) {
    $host_entity = $form_state->get('host_entity');
    if (!isset($host_entity)) {
      $parameters = $this->getRouteMatch()->getParameters();
      $host_entity = $this->registrationManager->getEntityFromParameters($parameters, TRUE);
      if ($host_entity instanceof RegistrationInterface) {
        // Editing a registration. Get the host entity from the registration.
        $host_entity = $host_entity->getHostEntity();
      }
      $form_state->set('host_entity', $host_entity);
    }
  }

  /**
   * Modify the form to use the Advanced interface.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   */
  protected function useAdvancedForm(array &$form) {
    /** @var \Drupal\registration\Entity\RegistrationInterface $registration */
    $registration = $this->entity;

    $form['#tree'] = TRUE;
    $form['#theme'] = ['registration_form'];
    $form['#attached']['library'][] = 'registration/form';
    // Changed must be sent to the client, for later overwrite error checking.
    $form['changed'] = [
      '#type' => 'hidden',
      '#default_value' => $registration->getChangedTime(),
    ];
    $form['state']['#group'] = 'footer';

    $last_saved = $this->t('Not saved yet');
    if (!$registration->isNew()) {
      $last_saved = $this->dateFormatter->format($registration->getChangedTime(), 'short');
    }
    $completed = $this->t('Not completed yet');
    if (!$registration->isNew() && $registration->isComplete()) {
      $completed = $this->dateFormatter->format($registration->getCompletedTime(), 'short');
    }
    $form['meta'] = [
      '#attributes' => ['class' => ['entity-meta__header']],
      '#type' => 'container',
      '#group' => 'advanced',
      '#weight' => -100,
      'state' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $registration->getState()->label(),
        '#access' => !$registration->isNew(),
        '#attributes' => [
          'class' => ['entity-meta__title'],
        ],
      ],
      'completed' => [
        '#type' => 'item',
        '#wrapper_attributes' => [
          'class' => ['entity-meta__last-saved', 'container-inline'],
        ],
        '#markup' => '<h4 class="label inline">' . $this->t('Completed') . '</h4> ' . $completed,
        '#access' => !$registration->isNew() && $registration->isComplete() && ($registration->getChangedTime() != $registration->getCompletedTime()),
      ],
      'changed' => [
        '#type' => 'item',
        '#wrapper_attributes' => [
          'class' => ['entity-meta__last-saved', 'container-inline'],
        ],
        '#markup' => '<h4 class="label inline">' . $this->t('Last saved') . '</h4> ' . $last_saved,
      ],
      'author' => [
        '#type' => 'item',
        '#access' => $registration->getAuthorDisplayName(),
        '#wrapper_attributes' => [
          'class' => ['author', 'container-inline'],
        ],
        '#markup' => '<h4 class="label inline">' . $this->t('Author') . '</h4> ' . $registration->getAuthorDisplayName(),
      ],
    ];
    $form['advanced'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['entity-meta']],
      '#weight' => 99,
    ];
    $form['author'] = [
      '#type' => 'details',
      '#title' => $this->t('Authoring information'),
      '#group' => 'advanced',
      '#attributes' => [
        'class' => ['registration-form-author'],
      ],
      '#weight' => 90,
      '#optional' => TRUE,
    ];
    if (isset($form['author_uid'])) {
      $form['author_uid']['#group'] = 'author';
    }
    if (isset($form['created'])) {
      $form['created']['#group'] = 'author';
    }
  }

}
