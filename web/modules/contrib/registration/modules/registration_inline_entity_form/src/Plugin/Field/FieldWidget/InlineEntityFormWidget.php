<?php

namespace Drupal\registration_inline_entity_form\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Field\WidgetPluginManager;
use Drupal\Core\Form\FormStateInterface;
use Drupal\inline_entity_form\Plugin\Field\FieldWidget\InlineEntityFormBase;
use Drupal\inline_entity_form\TranslationHelper;
use Drupal\registration\Entity\RegistrationSettings;
use Drupal\registration\Plugin\Field\FieldWidget\RegistrationTypeWidget;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the inline entity form widget for registration settings.
 *
 * Extends the registration type widget to allow site editors to edit
 * registration settings on the same form as the host entity. For example,
 * a node type with a registration field can have a fieldset with the
 * registration settings on the node edit form.
 *
 * @FieldWidget(
 *   id = "inline_entity_form_settings",
 *   label = @Translation("Inline entity form - Settings"),
 *   field_types = {
 *     "registration",
 *   }
 * )
 */
class InlineEntityFormWidget extends InlineEntityFormBase {

  /**
   * The widget plugin manager.
   *
   * @var \Drupal\Core\Field\WidgetPluginManager
   */
  protected WidgetPluginManager $widgetPluginManager;

  /**
   * The registration type widget.
   *
   * @var \Drupal\registration\Plugin\Field\FieldWidget\RegistrationTypeWidget
   */
  protected RegistrationTypeWidget $registrationTypeWidget;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->widgetPluginManager = $container->get('plugin.manager.field.widget');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'hide_register_tab' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $element = $this->getRegistrationTypeWidget()->settingsForm($form, $form_state);
    $element += parent::settingsForm($form, $form_state);

    // Registration settings do not have revisions.
    $element['revision']['#access'] = FALSE;

    // Registration settings do not need override labels.
    $element['override_labels']['#access'] = FALSE;
    $element['label_singular']['#access'] = FALSE;
    $element['label_plural']['#access'] = FALSE;

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = $this->getRegistrationTypeWidget()->settingsSummary();
    if ($entity_form_mode = $this->getEntityFormMode()) {
      $form_mode_label = $entity_form_mode->label();
    }
    else {
      $form_mode_label = $this->t('Default');
    }
    $summary[] = $this->t('Form mode: @mode', ['@mode' => $form_mode_label]);
    if ($this->getSetting('collapsible')) {
      $summary[] = $this->getSetting('collapsed') ? $this->t('Collapsible, collapsed by default') : $this->t('Collapsible');
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    // Get the registration type element.
    $element = $this->getRegistrationTypeWidget()->formElement($items, $delta, $element, $form, $form_state);

    // Settings are already part of the default value widget, so only display
    // the registration type element for that case.
    if ($this->isDefaultValueWidget($form_state)) {
      return $element;
    }

    // Set the provider so the widget can be identified in a form alter hook.
    // @see registration_inline_entity_form_form_alter()
    $form_state->set('provider', 'registration_inline_entity_form');

    // Initialize the form state with the inline entity form state.
    $parents = array_merge($element['#field_parents'], [$items->getName()]);
    $ief_id = $this->makeIefId($parents);
    $this->setIefId($ief_id);
    $ief_state = $form_state->get(['inline_entity_form', $ief_id]);
    if (is_null($ief_state)) {
      $ief_state = ['entities' => []];
      $form_state->set(['inline_entity_form', $ief_id], $ief_state);
    }

    $registration_type_input = $this->getElementName($parents);

    // Build the registration settings element.
    $element['#ief_id'] = $this->getIefId();
    $element['settings'] = [
      '#type' => $this->getSetting('collapsible') ? 'details' : 'fieldset',
      '#field_title' => $this->t('Registration settings'),
      '#after_build' => [
        [get_class($this), 'removeTranslatabilityClue'],
      ],
      // Hide the settings element if the type element is currently set to
      // "Disable Registrations".
      '#states' => [
        'visible' => [
          ':input[name="' . $registration_type_input . '"]' => ['!value' => ''],
        ],
      ],
    ];
    if ($element['settings']['#type'] == 'details') {
      // If there's user input, keep the details open. Otherwise, use widget
      // settings to determine the open state.
      $element['settings']['#open'] = $form_state->getUserInput() ?: !$this->getSetting('collapsed');
    }

    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $items->getEntity();
    if ($entity->isNew()) {
      // Initialize the host entity ID so host entity settings can be loaded
      // even for a new host entity.
      $entity->set($entity->getEntityType()->getKey('id'), 0);
    }

    // Get the registration settings and create an inline form to edit them.
    $settings = $this->getSettingsEntity($items, $ief_state, $entity);
    $op = $entity->isNew() ? 'add' : 'edit';
    $langcode = $items->getEntity()->language()->getId();
    $parents = array_merge($element['#field_parents'], [
      $items->getName(),
      $delta,
      'inline_entity_form',
    ]);
    $bundle = $this->getBundle();
    $element['settings']['inline_entity_form'] = $this->getInlineEntityForm($op, $bundle, $langcode, $delta, $parents, $settings);
    $element['settings']['#access'] = $settings->access('update');
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state) {
    // Extract the registration type value.
    $this->getRegistrationTypeWidget()->extractFormValues($items, $form, $form_state);

    // Settings in the default value form are handled outside this widget.
    if ($this->isDefaultValueWidget($form_state)) {
      $items->filterEmptyItems();
      return;
    }

    // Get the settings entity from the inline form.
    $field_name = $this->fieldDefinition->getName();
    $parents = array_merge($form['#parents'], [$field_name]);
    $submitted_values = $form_state->getValue($parents);
    $values = $items->getValue();
    foreach ($items as $delta => $value) {
      if ($element = NestedArray::getValue($form, [
        $field_name, 'widget', $delta, 'settings',
      ])) {
        $entity = $element['inline_entity_form']['#entity'];
        $weight = $submitted_values[$delta]['_weight'] ?? 0;
        $values[$weight]['entity'] = $entity;
      }
    }

    // Let the widget massage the submitted values.
    $values = $this->massageFormValues($values, $form, $form_state);

    // Assign the values and remove the empty ones.
    $items->setValue($values);
    $items->filterEmptyItems();

    // Populate the IEF form state with $items so that WidgetSubmit can
    // perform the necessary saves.
    $ief_id = $this->makeIefId($parents);
    $widget_state = [
      'instance' => $this->fieldDefinition,
      'delete' => [],
      'entities' => [],
    ];
    foreach ($items as $delta => $value) {
      TranslationHelper::updateEntityLangcode($value->entity, $form_state);
      $widget_state['entities'][$delta] = [
        'entity' => $value->entity,
        'needs_save' => TRUE,
      ];
    }
    $form_state->set(['inline_entity_form', $ief_id], $widget_state);

    // Put delta mapping in $form_state, so that flagErrors() can use it.
    $field_name = $this->fieldDefinition->getName();
    $field_state = WidgetBase::getWidgetState($form['#parents'], $field_name, $form_state);
    foreach ($items as $delta => $item) {
      $field_state['original_deltas'][$delta] = $item->_original_delta ?? $delta;
      unset($item->_original_delta, $item->weight);
    }
    WidgetBase::setWidgetState($form['#parents'], $field_name, $form_state, $field_state);
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition): bool {
    return TRUE;
  }

  /**
   * Gets the name of the HTML input element.
   *
   * @param array $parents
   *   The parent elements.
   *
   * @return string
   *   The element name.
   */
  protected function getElementName(array $parents): string {
    $element_name = implode('][', $parents);
    $pos = strpos($element_name, ']');
    if ($pos !== FALSE) {
      $element_name = substr_replace($element_name, '', $pos, 1);
    }
    if (count($parents) > 1) {
      $element_name .= ']';
    }
    $element_name .= '[0][registration_type]';
    return $element_name;
  }

  /**
   * Returns the value of a field setting.
   *
   * @param string $setting_name
   *   The setting name.
   *
   * @return mixed
   *   The setting value.
   */
  protected function getFieldSetting($setting_name): mixed {
    // Registration settings are not a real entity reference field, so the
    // "target_type" field setting does not exist. Hard code the return for
    // that case.
    if ($setting_name == 'target_type') {
      return 'registration_settings';
    }
    return parent::getFieldSetting($setting_name);
  }

  /**
   * Gets a registration type ID.
   *
   * Retrieves the first registration type ID. This function is called in a
   * context in which any registration type is sufficient.
   *
   * @return string
   *   The machine name of a registration type.
   */
  protected function getAnyRegistrationTypeId(): string {
    $types = $this->entityTypeManager
      ->getStorage('registration_type')
      ->loadMultiple();
    if (!empty($types)) {
      $type = reset($types);
      return $type->id();
    }
    throw new \InvalidArgumentException("No registration types are defined");
  }

  /**
   * Gets the registration type widget plugin.
   *
   * @return \Drupal\registration\Plugin\Field\FieldWidget\RegistrationTypeWidget
   *   The registration type widget.
   */
  protected function getRegistrationTypeWidget(): RegistrationTypeWidget {
    if (!isset($this->registrationTypeWidget)) {
      /** @var \Drupal\registration\Plugin\Field\FieldWidget\RegistrationTypeWidget $instance */
      $instance = $this->widgetPluginManager->getInstance([
        'field_definition' => $this->fieldDefinition,
        'form_mode' => $this->getSetting('form_mode'),
        'prepare' => FALSE,
        'configuration' => [
          'type' => 'registration_type',
          'settings' => $this->getSettings(),
          'third_party_settings' => $this->getThirdPartySettings(),
        ],
      ]);
      $this->registrationTypeWidget = $instance;
    }
    return $this->registrationTypeWidget;
  }

  /**
   * Gets the settings entity for the inline form.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The field item list.
   * @param array $ief_state
   *   The inline entity form state.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The host entity.
   *
   * @return \Drupal\registration\Entity\RegistrationSettings
   *   The settings entity.
   */
  protected function getSettingsEntity(FieldItemListInterface $items, array $ief_state, EntityInterface $entity): RegistrationSettings {
    // First attempt to retrieve the settings from the current state.
    $settings = NestedArray::getValue($ief_state, ['entities', 0, 'entity']);
    if (!$settings) {
      // Settings are not in the current state, so retrieve from the host
      // entity directly.
      $handler = $this->entityTypeManager->getHandler($entity->getEntityTypeId(), 'registration_host_entity');
      $host_entity = $handler->createHostEntity($entity);
      if (!$host_entity->isConfiguredForRegistration()) {
        // Configure the host entity for registration for the purposes of
        // getting default settings. This is a temporary copy and is not saved.
        // The registration type assigned does not matter here, since default
        // registration settings do not vary per registration type, but instead
        // default per registration field.
        $new_entity = clone $entity;
        /** @var \Drupal\Core\Entity\ContentEntityInterface $new_entity */
        $new_entity->set($items->getName(), $this->getAnyRegistrationTypeId());
        $host_entity = $handler->createHostEntity($new_entity);
      }
      $settings = $host_entity->getSettings();
    }

    return $settings;
  }

  /**
   * Checks whether we can build entity form at all.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   *
   * @return bool
   *   TRUE if we are able to proceed with form build and FALSE if not.
   */
  protected function canBuildForm(FormStateInterface $form_state) {
    if (!$this->inlineFormHandler) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Gets the bundle for the inline entity.
   *
   * This is an override of the parent class that supports many bundles.
   * Registration settings do not have bundles, so simply return the
   * entity type name.
   *
   * @return string|null
   *   The bundle, or NULL if not known.
   */
  protected function getBundle(): ?string {
    return 'registration_settings';
  }

  /**
   * Gets the target bundles for the current field.
   *
   * This is an override of the parent class that supports many bundles.
   * Registration settings do not have bundles, so simply return the
   * entity type name.
   *
   * @return string[]
   *   A list of bundles.
   */
  protected function getTargetBundles(): array {
    return ['registration_settings'];
  }

}
