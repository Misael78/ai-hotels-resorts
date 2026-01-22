<?php

namespace Drupal\registration\Plugin\Action;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Render\PlainTextOutput;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\Plugin\Action\EmailAction;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Session\AccountInterface;
use Drupal\registration\Entity\RegistrationInterface;
use Drupal\registration\Event\RegistrationDataAlterEvent;
use Drupal\registration\Event\RegistrationEvents;
use Drupal\registration\RegistrationHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Sends an email message to a registrant.
 *
 * @Action(
 *   id = "registration_send_email_action",
 *   label = @Translation("Send registration email"),
 *   type = "registration"
 * )
 */
class RegistrationEmailAction extends EmailAction {

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Contracts\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $dispatcher;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected Renderer $renderer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): RegistrationEmailAction {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->dispatcher = $container->get('event_dispatcher');
    $instance->logger = $container->get('registration.logger');
    $instance->moduleHandler = $container->get('module_handler');
    $instance->renderer = $container->get('renderer');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'log_message' => TRUE,
      'message' => [
        'value' => '',
        'format' => filter_default_format(),
      ],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['recipient']['#description'] = $this->t('Use the token [registration:mail] to send to the registrant of each selected registration.');
    $form['message'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Message'),
      '#required' => TRUE,
      '#description' => $this->t('Enter the message you want to send. Tokens are supported, e.g., [node:title].'),
      '#default_value' => $this->configuration['message']['value'],
      '#format' => $this->configuration['message']['format'],
    ];
    if ($this->moduleHandler->moduleExists('token')) {
      $form['token_tree'] = [
        '#theme' => 'token_tree_link',
        '#token_types' => [
          'registration',
          'registration_settings',
        ],
        '#global_types' => FALSE,
        '#weight' => 10,
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    /** @var \Drupal\registration\Entity\RegistrationInterface $entity */
    $host_entity = $entity->getHostEntity();
    $settings = $host_entity->getSettings();

    $params = [];
    $params['subject'] = $this->configuration['subject'];
    $params['from'] = RegistrationHelper::getMailboxHeader('From', $settings->getSetting('from_address'));
    $build = [
      '#type' => 'processed_text',
      '#text' => $this->configuration['message']['value'],
      '#format' => $this->configuration['message']['format'],
    ];
    $message = $this->renderer->renderInIsolation($build);
    $params['message'] = new FormattableMarkup($message, []);
    $params['token_entities'] = [
      $host_entity->getEntityTypeId() => $host_entity->getEntity(),
      'registration_settings' => $settings,
      'registration' => $entity,
    ];
    $recipient = PlainTextOutput::renderFromHtml($this->token->replace($this->configuration['recipient'], $params['token_entities']));

    // If the recipient is a registered user with a language preference, use
    // the recipient's preferred language. Otherwise, use the system default
    // language.
    $recipient_accounts = $this->storage->loadByProperties(['mail' => $recipient]);
    $recipient_account = reset($recipient_accounts);
    if ($recipient_account) {
      $user_langcode = $recipient_account->getPreferredLangcode();
    }
    else {
      $user_langcode = $this->languageManager->getDefaultLanguage()->getId();
    }
    $langcode = $entity->getLangcode() ?? $user_langcode;

    // Allow other modules to alter the parameters.
    // Token replacement should not be done in the event subscriber
    // because the registration_mail function already handles this.
    $event = new RegistrationDataAlterEvent($params, [
      'host_entity' => $host_entity,
      'settings' => $settings,
      'registration' => $entity,
      'data' => [
        'mail_tag' => $this->configuration['mail_tag'] ?? $this->getPluginId(),
      ],
    ]);
    $this->dispatcher->dispatch($event, RegistrationEvents::REGISTRATION_ALTER_MAIL);
    $params = $event->getData();

    // Send the email.
    $message = $this->mailManager->mail('registration', 'send_email_action', $recipient, $langcode, $params);

    // Error logging is handled by \Drupal\Core\Mail\MailManager::mail().
    if ($message['result'] && $this->configuration['log_message']) {
      $this->logger->notice('Sent email to %recipient', ['%recipient' => $recipient]);
    }

    return $message['result'];
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $entity = NULL;
    $result = NULL;
    $host_entity = NULL;

    // Allow access if the object is a registration with a valid host entity.
    if ($object instanceof RegistrationInterface) {
      if ($host_entity = $object->getHostEntity()) {
        if ($entity = $host_entity->getEntity()) {
          $result = AccessResult::allowed();
        }
      }
    }

    if (!$result) {
      $result = AccessResult::forbidden();
    }

    // Recalculate this result if the registration is updated.
    if ($object instanceof RegistrationInterface) {
      $result->addCacheableDependency($object);
    }
    // Recalculate this result if the host entity is updated.
    if ($host_entity) {
      $result->addCacheableDependency($host_entity);
    }

    return $return_as_object ? $result : $result->isAllowed();
  }

}
