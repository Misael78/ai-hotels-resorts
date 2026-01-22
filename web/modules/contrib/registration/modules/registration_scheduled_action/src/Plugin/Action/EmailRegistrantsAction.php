<?php

namespace Drupal\registration_scheduled_action\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\registration\HostEntityInterface;
use Drupal\registration_scheduled_action\Action\QueryableActionInterface;
use Drupal\registration_scheduled_action\Entity\ScheduledActionInterface;

/**
 * Provides an action to send email to host entity registrants.
 *
 * @Action(
 *   id = "registration_email_registrants_action",
 *   label = @Translation("Email host entity registrants"),
 *   type = "registration",
 * )
 */
class EmailRegistrantsAction extends ConfigurableEmailActionBase implements QueryableActionInterface {

  /**
   * {@inheritdoc}
   */
  public function execute($object = NULL) {
    if ($object && ($host_entity = $this->getHostEntity($object))) {
      $registration_type = $host_entity->getRegistrationType();
      $scheduled_action = $object->scheduled_action;
      $configuration = $scheduled_action->getPluginConfiguration();
      $data = [
        'message' => $configuration['message'],
        'subject' => $configuration['subject'],
        'langcode' => $object->langcode,
        'states' => array_keys($registration_type->getActiveStates()),
        'mail_tag' => $this->getPluginId(),
      ];
      $success_count = $this->registrationMailer->notify($host_entity, $data);
      if ($success_count) {
        $this->logger->info('Sent email %email to @count recipients of %label.', [
          '@count' => $success_count,
          '%email' => $scheduled_action->label(),
          '%label' => $host_entity->label(),
        ]);
      }
      else {
        $this->logger->warning('%email for %label had no recipients.', [
          '%email' => $scheduled_action->label(),
          '%label' => $host_entity->label(),
        ]);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    // Allow the action to execute if there is at least one registrant.
    $entity = NULL;
    $result = NULL;
    $host_entity = NULL;
    if ($object && ($host_entity = $this->getHostEntity($object))) {
      if ($entity = $host_entity->getEntity()) {
        $result = AccessResult::allowedIf(($host_entity->getRegistrationCount() > 0));
      }
    }

    if (!$result) {
      $result = AccessResult::forbidden();
    }

    // Recalculate this result if the host entity is updated.
    if ($host_entity) {
      $result->addCacheableDependency($host_entity);
    }

    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function getAllowedPositions(): array {
    // Registrants can be emailed before or after the settings close date.
    return ['before', 'after'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDateFieldLabel(): string {
    return $this->t('Registration settings close date');
  }

  /**
   * {@inheritdoc}
   */
  public function getKeyValueStoreCollectionName(): string {
    return 'registration_scheduled_action.registration_settings';
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
      ->select('registration_settings_field_data', 'r')
      ->fields('r');

    // Restrict to a specific language if the action specified one.
    $langcode = $scheduled_action->getTargetLangcode();
    if ($langcode != LanguageInterface::LANGCODE_NOT_SPECIFIED) {
      $query->condition('r.langcode', $langcode);
    }

    // Compare the scheduled action date criteria to the registration settings
    // close date.
    $date_times = $scheduled_action->getDateTimeArrayForQuery();
    $query->condition('r.close', $date_times, 'BETWEEN');
    $query->orderBy('r.settings_id');

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function getQueryUniqueKeyColumnName(): string {
    return 'settings_id';
  }

  /**
   * Gets the host entity for a scheduled action.
   *
   * @param mixed $object
   *   The object that the scheduled action is being executed against.
   *
   * @return \Drupal\registration\HostEntityInterface|null
   *   The host entity, if available.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getHostEntity(mixed $object): ?HostEntityInterface {
    $storage = $this->entityTypeManager->getStorage('registration_settings');
    if ($settings = $storage->load($object->settings_id)) {
      return $settings->getHostEntity();
    }
    return NULL;
  }

}
