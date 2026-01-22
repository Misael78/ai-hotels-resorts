<?php

namespace Drupal\registration_inline_entity_form;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\inline_entity_form\WidgetSubmit;
use Drupal\registration\Entity\RegistrationSettings;
use Drupal\registration\HostEntityInterface;

/**
 * Performs widget submission for registration settings.
 */
class RegistrationWidgetSubmit {

  /**
   * Attaches the widget submit functionality to the given form.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function attach(array &$form, FormStateInterface $form_state) {
    foreach ($form['#ief_element_submit'] as $index => $element) {
      $form['#ief_element_submit'][$index] = [get_called_class(), 'doSubmit'];
    }
  }

  /**
   * Submits the widget elements, saving and deleting entities where needed.
   *
   * Prepares settings for submission, then calls the inline form widget
   * submit handler to do the rest of the work.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function doSubmit(array $form, FormStateInterface $form_state) {
    $widget_states =& $form_state->get('inline_entity_form');
    foreach ($widget_states as $ief_id => &$widget_state) {
      $widget_state += ['entities' => [], 'delete' => []];
      foreach ($widget_state['entities'] as &$entity_item) {
        if (!empty($entity_item['entity'])) {
          /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
          $entity = $entity_item['entity'];
          if ($entity instanceof RegistrationSettings) {
            self::prepareSettings($entity, $form_state, $ief_id);
            $entity_item['needs_save'] = (bool) $entity->getHostEntityId();
          }
        }
      }
    }

    WidgetSubmit::doSubmit($form, $form_state);
  }

  /**
   * Prepares a settings entity for saving.
   *
   * When the host entity is new, the host is saved first, and then its ID
   * must be applied to the settings entity before it is saved. This extra
   * work is necessary because settings are not stored as a real entity
   * reference field.
   *
   * @param \Drupal\registration\Entity\RegistrationSettings $settings
   *   The settings.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $ief_id
   *   The inline entity form ID of the settings widget.
   */
  protected static function prepareSettings(RegistrationSettings $settings, FormStateInterface $form_state, string $ief_id) {
    if (!$settings->getHostEntityId()) {
      if ($host_entity = self::extractHostEntity($form_state, $ief_id)) {
        if ($host_entity->isConfiguredForRegistration()) {
          $settings->set('entity_id', $host_entity->id());
          $existing_settings = $host_entity->getSettings();
          if ($existing_settings && !$existing_settings->isNew()) {
            // The host entity could have a post save event that creates the
            // settings. Do not attempt to create another new settings entity,
            // to avoid a duplicate key exception. Instead, save to the
            // existing entity.
            $settings->set('settings_id', $existing_settings->id());
            $settings->enforceIsNew(FALSE);
          }
        }
      }
    }
  }

  /**
   * Extracts a host entity from the form state.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $ief_id
   *   The inline entity form ID of the settings widget.
   *
   * @return \Drupal\registration\HostEntityInterface|null
   *   The host entity, if available.
   */
  protected static function extractHostEntity(FormStateInterface $form_state, string $ief_id): ?HostEntityInterface {
    if (preg_match('/(.*)-([0-9]+)-(.*)/', $ief_id, $matches)) {
      $widget_states =& $form_state->get('inline_entity_form');
      $form_id = $matches[1];
      $delta = $matches[2];
      $entity = NestedArray::getValue($widget_states, [
        $form_id,
        'entities',
        $delta,
        'entity',
      ]);
    }
    else {
      $entity = $form_state->getFormObject()->getEntity();
    }

    if ($entity) {
      return \Drupal::entityTypeManager()
        ->getHandler($entity->getEntityTypeId(), 'registration_host_entity')
        ->createHostEntity($entity);
    }

    return NULL;
  }

}
