<?php

namespace Drupal\registration_scheduled_action;

use Drupal\Core\Config\Entity\DraggableListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the list builder for scheduled registration actions.
 */
class ScheduledActionListBuilder extends DraggableListBuilder {

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    $instance = parent::createInstance($container, $entity_type);
    $instance->languageManager = $container->get('language_manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'registration_scheduled_action_list';
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['label'] = $this->t('Label');
    $header['action'] = $this->t('Action');
    $header['when'] = $this->t('When');
    $header['date'] = $this->t('Date');
    if ($this->languageManager->isMultilingual()) {
      $header['language'] = $this->t('Language');
    }
    $header['status'] = $this->t('Status');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\registration_scheduled_action\Entity\ScheduledActionInterface $entity */
    $row['label'] = $entity->label();

    // If a weight key is set, the list is actually a form for adjusting entity
    // weights, and the 'markup' element is required. This is a quirk of the
    // draggable list builder.
    if (!empty($this->weightKey)) {
      $row['action']['#markup'] = $entity->getPlugin()->getPluginDefinition()['label'];
      $row['when']['#markup'] = $entity->getDateTimeForDisplay();
      $row['date']['#markup'] = $entity->getPlugin()->getDateFieldLabel();
      if ($this->languageManager->isMultilingual()) {
        $row['language']['#markup'] = $this->getLanguageName($entity->getTargetLangcode());
      }
      $row['status']['#markup'] = $entity->isEnabled() ? $this->t('Enabled') : $this->t('Disabled');
    }
    // Dragging is disabled, use the standard setup for row elements.
    else {
      $row['action'] = $entity->getPlugin()->getPluginDefinition()['label'];
      $row['when'] = $entity->getDateTimeForDisplay();
      $row['date'] = $entity->getPlugin()->getDateFieldLabel();
      if ($this->languageManager->isMultilingual()) {
        $row['language'] = $this->getLanguageName($entity->getTargetLangcode());
      }
      $row['status'] = $entity->isEnabled() ? $this->t('Enabled') : $this->t('Disabled');
    }

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    $entities = $this->load();
    // If there are less than 2 items in the list, disable dragging.
    if (count($entities) <= 1) {
      unset($this->weightKey);
    }
    return parent::render();
  }

  /**
   * Gets the language name for a given language code.
   *
   * @param string $langcode
   *   The language code.
   *
   * @return string
   *   The language name, or 'All' if language code is 'und' (not specified).
   */
  protected function getLanguageName(string $langcode): string {
    $language_name = $this->t('- All -');

    if ($langcode != LanguageInterface::LANGCODE_NOT_SPECIFIED) {
      if ($language = $this->languageManager->getLanguage($langcode)) {
        $language_name = $language->getName();
      }
    }

    return $language_name;
  }

}
