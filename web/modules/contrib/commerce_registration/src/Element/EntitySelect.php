<?php

namespace Drupal\commerce_registration\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce\Element\EntitySelect as BaseEntitySelect;
use Drupal\commerce\EntityHelper;

/**
 * Provides a form input element for selecting commerce entities.
 *
 * Same as the base class in the commerce module, except allows the entities to
 * be selected using a custom selection handler.
 *
 * @FormElement("commerce_registration_entity_select")
 */
class EntitySelect extends BaseEntitySelect {

  /**
   * {@inheritdoc}
   */
  public function getInfo(): array {
    return [
      '#selection_handler' => 'default',
      '#selection_settings' => [],
    ] + parent::getInfo();
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    if (!is_array($input)) {
      // If the value is not an array, make it one, and fix the form state.
      // This can happen if the widget is submitted during pager use.
      $input = ['value' => $input];
      $form_state_input = $form_state->getUserInput();
      $parents = $element['#parents'];
      if (!empty($parents)) {
        // @todo Handle multiple levels of parents.
        $parent = reset($parents);
        $form_state_input[$parent] = $input;
        $form_state->setUserInput($form_state_input);
      }
    }
    return $input;
  }

  /**
   * Process callback.
   */
  public static function processEntitySelect(&$element, FormStateInterface $form_state, &$complete_form) {
    // Nothing to do if there is no target entity type.
    if (empty($element['#target_type'])) {
      throw new \InvalidArgumentException('Missing required #target_type parameter.');
    }
    $storage = \Drupal::service('entity_type.manager')->getStorage($element['#target_type']);

    // Retrieve entity IDs using the selection handler.
    $options = $element['#selection_settings'] + [
      'target_type' => $element['#target_type'],
      'handler' => $element['#selection_handler'],
    ];
    $handler = \Drupal::service('plugin.manager.entity_reference_selection')->getInstance($options);
    $entity_ids = self::getEntityIds($handler->getReferenceableEntities());
    $entity_count = count($entity_ids);

    // Build element.
    $element['#tree'] = TRUE;
    // No need to show anything, there's only one possible value.
    if ($element['#required'] && $entity_count == 1 && $element['#hide_single_entity']) {
      $element['value'] = [
        '#type' => 'hidden',
        '#value' => reset($entity_ids),
      ];

      return $element;
    }

    if ($entity_count <= $element['#autocomplete_threshold']) {
      /** @var \Drupal\Core\Entity\EntityStorageInterface $storage */
      $entities = $storage->loadMultiple($entity_ids);
      $entity_labels = EntityHelper::extractLabels($entities);
      if (!$element['#required']) {
        $options = ['' => t('- Any -')] + $entity_labels;
      }
      elseif (!empty($element['#all'])) {
        $options = ['all' => $element['#all']] + $entity_labels;
      }
      else {
        $options = $entity_labels;
      }
      $element['value'] = [
        '#type' => 'select',
        '#required' => $element['#required'],
        '#options' => $options,
      ];
      if (!empty($element['#default_value'])) {
        $element['value']['#default_value'] = $element['#default_value'];
      }
    }
    else {
      $default_value = NULL;
      if (!empty($element['#default_value'])) {
        // Upcast ids into entities, as expected by entity_autocomplete.
        if ($element['#multiple']) {
          $default_value = $storage->loadMultiple($element['#default_value']);
        }
        else {
          $default_value = $storage->load($element['#default_value']);
        }
      }

      $element['value'] = [
        '#type' => 'entity_autocomplete',
        '#selection_handler' => $element['#selection_handler'],
        '#selection_settings' => $element['#selection_settings'],
        '#target_type' => $element['#target_type'],
        '#tags' => $element['#multiple'],
        '#required' => $element['#required'],
        '#default_value' => $default_value,
        '#size' => $element['#autocomplete_size'],
        '#placeholder' => $element['#autocomplete_placeholder'],
        '#maxlength' => NULL,
      ];
    }

    // These keys only make sense on the actual input element.
    foreach (['#title', '#title_display', '#description', '#ajax'] as $key) {
      if (isset($element[$key])) {
        $element['value'][$key] = $element[$key];
        unset($element[$key]);
      }
    }

    return $element;
  }

  /**
   * Gets entity IDs from an array of entities returned by a selection plugin.
   *
   * @param array $entities
   *   The entities keyed first by bundle and second by entity ID.
   *
   * @return array
   *   A flattened array containing only entity IDs.
   */
  protected static function getEntityIds(array $entities): array {
    $flattened_entities = [];
    foreach ($entities as $entities_per_bundle) {
      $flattened_entities = array_merge($flattened_entities, array_keys($entities_per_bundle));
    }
    return $flattened_entities;
  }

}
