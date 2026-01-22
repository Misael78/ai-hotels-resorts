<?php

namespace Drupal\registration\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\Exception\UndefinedLinkTemplateException;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'registration_host_entity' formatter.
 *
 * An adaptation of the core entity reference formatter.
 * Only for use with a computed host entity item field.
 * See the comments in the HostEntityItem class for more information.
 *
 * @FieldFormatter(
 *   id = "registration_host_entity",
 *   label = @Translation("Host entity"),
 *   description = @Translation("Display the host entity for a registration."),
 *   field_types = {
 *     "registration_host_entity"
 *   }
 * )
 *
 * @see \Drupal\registration\Plugin\Field\FieldType\HostEntityItem
 * @see \Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceLabelFormatter
 */
class RegistrationHostEntityFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'link' => TRUE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements['link'] = [
      '#title' => $this->t('Link label to the host entity'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('link'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = [];
    $summary[] = $this->getSetting('link') ? $this->t('Link to the host entity') : $this->t('No link');
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    $output_as_link = $this->getSetting('link');

    foreach ($items as $delta => $item) {
      /** @var \Drupal\registration\HostEntityInterface $host_entity */
      $entity = $item->get('entity')->getValue();
      if (is_null($entity)) {
        continue;
      }
      // Get the translated entity if it has one. Let the host entity handler
      // do the heavy lifting since the entity type may not be translatable
      // and calling translation functions on it would throw exceptions.
      $handler = \Drupal::entityTypeManager()->getHandler($entity->getEntityTypeId(), 'registration_host_entity');
      $host_entity = $handler->createHostEntity($entity, $langcode);
      $entity = $host_entity->getEntity();

      // Link title.
      $label = $entity->label();
      // If the link should be displayed and the entity has a uri, display it.
      if ($output_as_link && !$entity->isNew() && $entity->access('view')) {
        try {
          $uri = $entity->toUrl();
        }
        catch (UndefinedLinkTemplateException) {
          // This exception is thrown by \Drupal\Core\Entity\Entity::urlInfo()
          // and it means that the entity type doesn't have a link template nor
          // a valid "uri_callback", so don't bother trying to output a link for
          // the rest of the referenced entities.
          $output_as_link = FALSE;
        }
      }

      if ($output_as_link && isset($uri) && !$entity->isNew()) {
        $elements[$delta] = [
          '#type' => 'link',
          '#title' => $label,
          '#url' => $uri,
          '#options' => $uri->getOptions(),
        ];

        if (!empty($items[$delta]->_attributes)) {
          $elements[$delta]['#options'] += ['attributes' => []];
          $elements[$delta]['#options']['attributes'] += $items[$delta]->_attributes;
          // Unset field item attributes since they have been included in the
          // formatter output and shouldn't be rendered in the field template.
          unset($items[$delta]->_attributes);
        }
      }
      elseif ($entity->access('view label')) {
        $elements[$delta] = ['#plain_text' => $label];
      }
      $elements[$delta]['#cache']['tags'] = $entity->getCacheTags();
    }

    return $elements;
  }

}
