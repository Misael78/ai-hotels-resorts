<?php

namespace Drupal\registration_scheduled_action\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\ActionManager;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\registration\Entity\RegistrationInterface;
use Drupal\registration_scheduled_action\Action\QueryableActionInterface;
use Drupal\registration_scheduled_action\Entity\ScheduledActionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an action to email a confirmation after a completed registration.
 *
 * @Action(
 *   id = "registration_email_confirmation_action",
 *   label = @Translation("Email registration confirmation"),
 *   type = "registration",
 * )
 */
class EmailConfirmationAction extends ConfigurableEmailActionBase implements QueryableActionInterface {

  /**
   * The action plugin manager.
   *
   * @var \Drupal\Core\Action\ActionManager
   */
  protected ActionManager $actionManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->actionManager = $container->get('plugin.manager.action');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute($object = NULL) {
    if ($object && ($registration = $this->getRegistration($object))) {
      $scheduled_action = $object->scheduled_action;
      $configuration = $scheduled_action->getPluginConfiguration();
      $configuration['recipient'] = $registration->getEmail();
      $configuration['log_message'] = FALSE;
      $configuration['mail_tag'] = $this->getPluginId();
      $action = $this->actionManager->createInstance('registration_send_email_action');
      $action->setConfiguration($configuration);
      if ($action->execute($registration)) {
        $this->logger->info('Sent registration confirmation email to %recipient', [
          '%recipient' => $configuration['recipient'],
        ]);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    if ($object && ($registration = $this->getRegistration($object))) {
      $action = $this->actionManager->createInstance('registration_send_email_action');
      return $action->access($registration);
    }

    $result = AccessResult::forbidden();
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function getAllowedPositions(): array {
    // Registration confirmation emails can only be sent after registration
    // is completed, as the field never holds a future date.
    return ['after'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDateFieldLabel(): string {
    return $this->t('Registration completed');
  }

  /**
   * {@inheritdoc}
   */
  public function getKeyValueStoreCollectionName(): string {
    return 'registration_scheduled_action.registration';
  }

  /**
   * {@inheritdoc}
   */
  public function getKeyValueStoreExpirationTime(): int {
    // Store key value entries for 48 hours to prevent re-processing of items
    // within that period. By the time entries expire, the query filter will
    // be selecting different items.
    return 60 * 60 * 48;
  }

  /**
   * {@inheritdoc}
   */
  public function getQuery(ScheduledActionInterface $scheduled_action): SelectInterface {
    $query = $this->database
      ->select('registration', 'r')
      ->fields('r');

    // Restrict to a specific language if the action specified one.
    $langcode = $scheduled_action->getTargetLangcode();
    if ($langcode != LanguageInterface::LANGCODE_NOT_SPECIFIED) {
      $query->condition('r.langcode', $langcode);
    }

    // Compare the scheduled action date criteria to the registration
    // completion date.
    $date_times = $scheduled_action->getTimestampArrayForQuery();
    $query->condition('r.completed', $date_times, 'BETWEEN');
    $query->orderBy('r.registration_id');

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function getQueryUniqueKeyColumnName(): string {
    return 'registration_id';
  }

  /**
   * Gets the registration entity for a scheduled action.
   *
   * @param mixed $object
   *   The object that the scheduled action is being executed against.
   *
   * @return \Drupal\registration\Entity\RegistrationInterface|null
   *   The registration entity, if available.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getRegistration(mixed $object): ?RegistrationInterface {
    $storage = $this->entityTypeManager->getStorage('registration');
    if ($registration = $storage->load($object->registration_id)) {
      /** @var \Drupal\registration\Entity\RegistrationInterface $registration */
      return $registration;
    }
    return NULL;
  }

}
