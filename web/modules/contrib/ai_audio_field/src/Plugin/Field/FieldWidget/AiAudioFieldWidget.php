<?php

namespace Drupal\ai_audio_field\Plugin\Field\FieldWidget;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\ElementInfoManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\TextToSpeech\TextToSpeechInput;
use Drupal\ai\Service\AiProviderFormHelper;
use Drupal\file\Plugin\Field\FieldWidget\FileWidget;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * Defines the 'ai audio field' field widget.
 */
#[FieldWidget(
  id: 'ai_audio_field_widget',
  label: new TranslatableMarkup('AI Audio Field Form'),
  field_types: ['ai_audio_file', 'file'],
)]
class AiAudioFieldWidget extends FileWidget {

  /**
   * Wrapper id.
   */
  public string $fieldWrapperId = '';

  /**
   * Values and defaults.
   */
  public array $fieldData = [
    'text' => '',
    'speaker' => '',
    'start_time' => 0,
    'playing_time' => 0,
    'target_id' => NULL,
    'provider' => '',
    'model' => '',
    'configuration' => '',
  ];

  /**
   * ElevenLabs voices.
   */
  public array $voices = [];

  /**
   * ElevenLabs models.
   */
  public array $models = [];

  /**
   * {@inheritDoc}
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    ElementInfoManagerInterface $elementInfo,
    protected AiProviderPluginManager $providerManager,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings, $elementInfo);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('element_info'),
      $container->get('ai.provider'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'show_start_time' => FALSE,
      'show_advanced' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {

    $settings = $this->getSettings();

    $element['show_start_time'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show Start Time'),
      '#default_value' => $settings['show_start_time'],
    ];

    $element['show_advanced'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show Advanced Fields'),
      '#default_value' => $settings['show_advanced'],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $settings = $this->getSettings();
    $summary[] = $this->t("Show Start Time: @show_start_time<br>Show Advanced Fields: @show_advanced", [
      '@show_start_time' => $settings['show_start_time'] ? 'Yes' : 'No',
      '@show_advanced' => $settings['show_advanced'] ? 'Yes' : 'No',
    ]);
    return $summary;
  }

  /**
   * {@inheritDoc}
   */
  public function formMultipleElements(FieldItemListInterface $items, array &$form, FormStateInterface $formState) {
    $field_name = $this->fieldDefinition->getName();
    $cardinality = $this->fieldDefinition->getFieldStorageDefinition()->getCardinality();
    $parents = $form['#parents'];

    $id_prefix = implode('-', array_merge($parents, [$field_name]));
    $this->fieldWrapperId = Html::getUniqueId($id_prefix . '-add-more-wrapper');

    // Determine the number of widgets to display.
    $field_state = static::getWidgetState($parents, $field_name, $formState);
    switch ($cardinality) {
      case FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED:
        $max = $field_state['items_count'];
        $is_multiple = TRUE;
        break;

      default:
        $max = $cardinality - 1;
        $is_multiple = ($cardinality > 1);
        break;
    }

    $title = $this->fieldDefinition->getLabel();
    $description = $this->getFilteredDescription();

    $elements = [];

    for ($delta = 0; $delta <= $max; $delta++) {
      // Add a new empty item if it doesn't exist yet at this delta.
      if (!isset($items[$delta])) {
        $items->appendItem();
      }

      // For multiple fields, title and description are handled by the wrapping
      // table.
      if ($is_multiple) {
        $element = [
          '#title' => $this->t('@title (value @number)', [
            '@title' => $title,
            '@number' => $delta + 1,
          ]),
          '#title_display' => 'invisible',
          '#description' => '',
        ];
      }
      else {
        $element = [
          '#title' => $title,
          '#title_display' => 'before',
          '#description' => $description,
        ];
      }

      $element = $this->formSingleElement($items, $delta, $element, $form, $formState);

      if ($element) {
        // Input field for the delta (drag-n-drop reordering).
        if ($is_multiple) {
          // We name the element '_weight' to avoid clashing with elements
          // defined by widget.
          $element['_weight'] = [
            '#type' => 'weight',
            '#title' => $this->t('Weight for row @number', ['@number' => $delta + 1]),
            '#title_display' => 'invisible',
            // Note: this 'delta' is the FAPI #type 'weight' element's property.
            '#delta' => $max,
            '#default_value' => $items[$delta]->_weight ?: $delta,
            '#weight' => 100,
          ];
        }

        $elements[$delta] = $element;
      }
    }

    if ($elements) {
      $elements += [
        '#theme' => 'field_multiple_value_form',
        '#field_name' => $field_name,
        '#cardinality' => $cardinality,
        '#cardinality_multiple' => $this->fieldDefinition->getFieldStorageDefinition()->isMultiple(),
        '#required' => $this->fieldDefinition->isRequired(),
        '#title' => $title,
        '#description' => $description,
        '#max_delta' => $max,
      ];

      $elements['#prefix'] = '<div id="' . $this->fieldWrapperId . '">';
      $elements['#suffix'] = '</div>';
      // Add 'add more' button, if not working with a programmed form.
      if ($cardinality == FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED && !$formState->isProgrammed()) {
        $elements['add_more'] = [
          '#type' => 'submit',
          '#name' => strtr($id_prefix, '-', '_') . '_add_more',
          '#value' => $this->t('Add another item'),
          '#attributes' => ['class' => ['field-add-more-submit']],
          '#limit_validation_errors' => [array_merge($parents, [$field_name])],
          '#submit' => [[static::class, 'addMoreSubmit']],
          '#ajax' => [
            'callback' => [static::class, 'addMoreAjax'],
            'wrapper' => $this->fieldWrapperId,
            'effect' => 'fade',
          ],
        ];
      }
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $formState) {
    $fieldName = $this->fieldDefinition->getName();
    $widgetState = static::getWidgetState($form['#parents'], $fieldName, $formState);
    foreach ($this->fieldData as $id => $default) {
      if (!isset($widgetState['audios'][$delta][$id])) {
        $widgetState['audios'][$delta][$id] = $items[$delta]->{$id} ?? $default;
      }
    }
    static::setWidgetState($form['#parents'], $fieldName, $formState, $widgetState);

    // Set an update wrapper.
    $element['text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Text'),
      '#default_value' => $widgetState['audios'][$delta]['text'],
    ];

    // Get all providers for text to speech.
    $providers = $this->providerManager->getProvidersForOperationType('text_to_speech');
    $defaults = $this->providerManager->getDefaultProviderForOperationType('text_to_speech');
    $provider_id = $defaults['provider_id'] ?? '';
    if ($widgetState['audios'][$delta]['text']) {
      $provider_id = $widgetState['audios'][$delta]['provider'];
    }

    $options = [];
    foreach ($providers as $id => $provider_definition) {
      $options[$id] = $provider_definition['label'];
    }
    $element['provider'] = [
      '#type' => 'select',
      '#required' => TRUE,
      '#title' => $this->t('Provider'),
      '#options' => $options,
      '#default_value' => $provider_id,
    ];

    // If there is set a provider, get the voices.
    $model_options = [];
    if (!empty($provider_id)) {
      $provider_instance = $this->providerManager->createInstance($provider_id);
      $model_options = $provider_instance->getConfiguredModels('text_to_speech', []);
    }

    $model_id = $defaults['model_id'] ?? '';
    if ($widgetState['audios'][$delta]['text']) {
      $model_id = $widgetState['audios'][$delta]['model'];
    }
    $configuration = Json::decode($widgetState['audios'][$delta]['configuration']) ?? [];
    $element['model'] = [
      '#type' => 'select',
      '#required' => TRUE,
      '#title' => $this->t('Model'),
      '#options' => $model_options,
      '#default_value' => $model_id,
    ];

    if ($model_id) {
      $schema = $provider_instance->getAvailableConfiguration('text_to_speech', $model_id);
      $tmp_form = [];
      $this->generateFormElements('ai', $tmp_form, AiProviderFormHelper::FORM_CONFIGURATION_FULL, $schema, $configuration);
      foreach ($tmp_form as $key => $value) {
        $element[$key] = $value;
      }
    }

    if (!empty($widgetState['audios'][$delta]['target_id'])) {
      /** @var \Drupal\file\Entity\File $file */
      $file = \Drupal::entityTypeManager()->getStorage('file')->load($widgetState['audios'][$delta]['target_id']);
      $element['play'] = [
        '#type' => 'inline_template',
        '#template' => '{{ somecontent|raw }}',
        '#context' => [
          'somecontent' => '<div><audio controls><source src="' . $file->createFileUrl(TRUE) . '" type="audio/mpeg"></div>',
        ],
        '#weight' => 2,
      ];
    }

    $element['generate_' . $delta] = [
      '#type' => 'submit',
      '#value' => !empty($widgetState['audios'][$delta]['target_id']) ? $this->t('Regenerate audio') : $this->t('Generate audio'),
      '#ajax' => [
        'callback' => [$this, 'generateForm'],
        'event' => 'click',
        'wrapper' => $this->fieldWrapperId,
      ],
      '#name' => 'generate_' . $delta,
      '#attributes' => [
        'data-delta' => $delta,
      ],
      '#weight' => 3,
      '#access' => TRUE,
    ];

    $element['target_id'] = [
      '#type' => 'hidden',
      '#default_value' => $widgetState['audios'][$delta]['target_id'],
      '#value' => $widgetState['audios'][$delta]['target_id'],
    ];

    $element['#theme_wrappers'] = ['container', 'form_element'];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function errorElement(array $element, ConstraintViolationInterface $violation, array $form, FormStateInterface $form_state) {
    return isset($violation->arrayPropertyPath[0]) ? $element[$violation->arrayPropertyPath[0]] : $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    foreach ($values as $delta => $value) {
      if ($value['text'] === '') {
        $values[$delta]['text'] = NULL;
      }
      if ($value['provider'] === '') {
        $values[$delta]['provider'] = NULL;
      }
      if ($value['model'] === '') {
        $values[$delta]['model'] = NULL;
      }
      if (!empty($value['ai'])) {
        $values[$delta]['configuration'] = Json::encode($value['ai']);
      }
    }
    return $values;
  }

  /**
   * Callback to generate the form again.
   */
  public function generateForm(&$form, FormStateInterface $formState) {
    $this->generateSubmit($form, $formState);
    $trigger = $formState->getTriggeringElement();
    $field = array_slice($trigger['#array_parents'], 0, -2);
    $elements = NestedArray::getValue($form, array_slice($trigger['#array_parents'], 0, -2));
    foreach ($elements as $key => $value) {
      if (is_int($key)) {
        $target_id = $formState->getValue($field[0])[$key]['target_id'];
        $elements[$key]['target_id']['#value'] = $target_id;
        if ($target_id) {
          /** @var \Drupal\file\Entity\File $file */
          $file = \Drupal::entityTypeManager()->getStorage('file')->load($target_id);
          // A randomized string to avoid caching.
          $randomized = md5(uniqid());
          $elements[$key]['play'] = [
            '#type' => 'inline_template',
            '#template' => '{{ somecontent|raw }}',
            '#context' => [
              'somecontent' => '<div><audio controls><source src="' . $file->createFileUrl(TRUE) . '?rand=' . $randomized . '" type="audio/mpeg"></div>',
            ],
            '#weight' => 2,
          ];
        }
      }
    }
    $formState->setRebuild(TRUE);
    return $elements;
  }

  /**
   * Callback to generate audio.
   */
  public function generateSubmit(&$form, FormStateInterface $formState) {
    $trigger = $formState->getTriggeringElement();
    $element = NestedArray::getValue($form, array_slice($trigger['#array_parents'], 0, -2));
    $parents = $element['#field_parents'];
    $fieldName = $element['#field_name'];
    $widgetState = static::getWidgetState($parents, $fieldName, $formState);
    $data = $formState->getValue($fieldName);
    $delta = $trigger['#attributes']['data-delta'];
    if (is_numeric($delta)) {
      $oldData = $formState->getValue($fieldName);
      $key = $delta--;
      $fid = $oldData[$key]['target_id'] ?? NULL;
      $provider = $this->providerManager->createInstance($data[$key]['provider']);
      $configuration = [];
      foreach ($data[$key]['ai'] as $config_key => $value) {
        $real_key = str_replace('ai_configuration_', '', $config_key);
        $configuration[$real_key] = $value;
      }
      $input = new TextToSpeechInput($data[$key]['text']);
      $output = $provider->textToSpeech($input, $data[$key]['model'], $configuration);
      $file = $output->getNormalized()[0]->getAsFileEntity('public://', 'test.mp3');
      if (!empty($file)) {
        // Update both widget state and form state.
        $widgetState['audios'][$key]['target_id'] = $file->id();
        $data[$key]['target_id'] = $file->id();
        $data[$key]['should_change'] = FALSE;
        if ($fid) {
          /** @var \Drupal\file\Entity\File $oldFile */
          $oldFile = \Drupal::entityTypeManager()->getStorage('file')->load($fid);
          if ($oldFile) {
            $oldFile->set('status', 0);
            $oldFile->save();
          }
        }
        $formState->setValue($fieldName, $data);
      }
      $formState->setRebuild(TRUE);
      static::setWidgetState($parents, $fieldName, $formState, $widgetState);
    }
  }

  /**
   * Helper function to generate form elements from schema.
   *
   * @param string $prefix
   *   Prefix for the form elements.
   * @param array $form
   *   The form.
   * @param int $config_level
   *   The config level to return.
   * @param array $schema
   *   Configuration schema of the provider.
   * @param array $values
   *   Values of the configuration.
   */
  private function generateFormElements(string $prefix, array &$form, int $config_level, array $schema, array $values): void {
    // If there isn't a configuration or shouldn't be, return.
    if (empty($schema) || $config_level == AiProviderFormHelper::FORM_CONFIGURATION_NONE) {
      return;
    }
    foreach ($schema as $key => $definition) {
      // We skip it if it's not required and we only want required.
      if ($config_level == AiProviderFormHelper::FORM_CONFIGURATION_REQUIRED && empty($definition['required'])) {
        continue;
      }
      $set_key = $key;
      $form[$prefix][$set_key]['#type'] = $this->mapSchemaTypeToFormType($definition);
      $form[$prefix][$set_key]['#required'] = $definition['required'] ?? FALSE;
      $form[$prefix][$set_key]['#title'] = $definition['label'] ?? $key;
      $form[$prefix][$set_key]['#description'] = $definition['description'] ?? '';
      $form[$prefix][$set_key]['#default_value'] = $values[$set_key] ?? $definition['default'] ?? NULL;
      if (isset($definition['constraints'])) {
        foreach ($definition['constraints'] as $form_key => $value) {
          if ($form_key == 'options') {
            $options = array_combine($value, $value);
            if (empty($definition['required'])) {
              $options = ['' => 'Select an option'] + $options;
            }
            $form[$prefix][$set_key]['#options'] = $options;
            continue;
          }
          $form[$prefix][$set_key]['#' . $form_key] = $value;
        }
      }
    }
  }

  /**
   * Maps schema data types to form element types.
   *
   * @param array $definition
   *   Data type of a configuration value.
   *
   * @return string
   *   Type of widget.
   */
  public function mapSchemaTypeToFormType(array $definition): string {
    // Check first for settings constraints.
    if (isset($definition['constraints']['options'])) {
      return 'select';
    }
    switch ($definition['type']) {
      case 'boolean':
        return 'checkbox';

      case 'int':
      case 'float':
        return 'textfield';

      case 'string_long':
        return 'textarea';

      case 'string':
      default:
        return 'textfield';
    }
  }

}
