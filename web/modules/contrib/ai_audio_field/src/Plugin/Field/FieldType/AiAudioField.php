<?php

namespace Drupal\ai_audio_field\Plugin\Field\FieldType;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Random;
use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\ai\OperationType\TextToSpeech\TextToSpeechInput;
use Drupal\file\Plugin\Field\FieldType\FileItem;

/**
 * Defines the AI Audio field type.
 */
#[FieldType(
  id: "ai_audio_file",
  label: new TranslatableMarkup("AI Audio File"),
  description: [
    new TranslatableMarkup("For generating audio files from text"),
  ],
  category: "file_upload",
  default_widget: "ai_audio_field_widget",
  default_formatter: "file_default",
  list_class: AiAudioFieldItem::class,
  constraints: ["ReferenceAccess" => []],
  column_groups: [
    'target_id' => [
      'label' => new TranslatableMarkup('File'),
      'translatable' => TRUE,
    ],
    'display' => [
      'label' => new TranslatableMarkup('Display'),
      'translatable' => TRUE,
    ],
    'description' => [
      'label' => new TranslatableMarkup('Description'),
      'translatable' => TRUE,
    ],
    'text' => [
      'label' => new TranslatableMarkup('Text'),
      'translatable' => TRUE,
      'description' => new TranslatableMarkup('The text to generate the audio from.'),
    ],
    'provider' => [
      'label' => new TranslatableMarkup('Provider'),
      'translatable' => TRUE,
      'description' => new TranslatableMarkup('The provider to generate the audio from.'),
    ],
    'model' => [
      'label' => new TranslatableMarkup('Model'),
      'translatable' => TRUE,
      'description' => new TranslatableMarkup('The model to generate the audio from.'),
    ],
    'configuration' => [
      'label' => new TranslatableMarkup('Configuration'),
      'translatable' => TRUE,
      'description' => new TranslatableMarkup('The AI configuration to generate the audio from.'),
    ],
  ],
)]
class AiAudioField extends FileItem {

  /**
   * {@inheritdoc}
   */
  public static function mainPropertyName() {
    return 'target_id';
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    $storageSettings = parent::defaultStorageSettings();
    $storageSettings['target_type'] = 'file';
    unset($storageSettings['display_field']);
    unset($storageSettings['display_default']);
    return $storageSettings;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    $settings = parent::defaultFieldSettings();
    $settings['file_name'] = 'generated-audio.mp3';
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    if (empty($this->text)) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function hasNewEntity() {
    return !$this->isEmpty() && $this->target_id === NULL;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = parent::propertyDefinitions($field_definition);
    unset($properties['display']);
    unset($properties['description']);
    $properties['text'] = DataDefinition::create('string')
      ->setLabel(t('Text'));
    $properties['provider'] = DataDefinition::create('string')
      ->setLabel(t('Provider'));
    $properties['model'] = DataDefinition::create('string')
      ->setLabel(t('Model'));
    $properties['configuration'] = DataDefinition::create('string')
      ->setLabel(t('Configuration'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    if ($this->target_id) {
      parent::getValue();
      $file = \Drupal::entityTypeManager()->getStorage('file')->load($this->target_id);
      $this->set('entity', $file);
      return [
        'target_id' => $this->target_id,
        'text' => $this->text,
        'provider' => $this->provider,
        'model' => $this->model,
        'configuration' => $this->configuration,
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    // Check if target id is empty.
    if (empty($this->target_id) && !empty($this->text)) {
      // We need to generate audio.
      $this->target_id = $this->generateAudio();
    }

  }

  /**
   * Generate the audio file.
   *
   * @return int
   *   The file id.
   */
  private function generateAudio(): int {
    $provider = \Drupal::service('ai.provider')->createInstance($this->provider);
    $input = new TextToSpeechInput($this->text);
    $configuration = $this->configuration ? Json::decode($this->configuration) : [];
    $output = $provider->textToSpeech($input, $this->model, $configuration);
    $file = $output->getNormalized()[0]->getAsFileEntity('public://', 'test.mp3');
    return $file->id();
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {

    $columns = [
      'target_id' => [
        'description' => 'The ID of the file entity.',
        'type' => 'int',
        'unsigned' => TRUE,
      ],
      'display' => [
        'description' => 'Flag to control whether this file should be displayed when viewing content.',
        'type' => 'int',
        'size' => 'tiny',
        'unsigned' => TRUE,
        'default' => 1,
      ],
      'description' => [
        'description' => 'A description of the file.',
        'type' => 'text',
      ],
      'text' => [
        'type' => 'text',
        'size' => 'big',
      ],
      'provider' => [
        'type' => 'varchar',
        'length' => 255,
      ],
      'model' => [
        'type' => 'varchar',
        'length' => 255,
      ],
      'configuration' => [
        'type' => 'blob',
        'size' => 'big',
      ],
    ];

    $schema = [
      'columns' => $columns,
      'indexes' => [
        'target_id' => ['target_id'],
      ],
      'foreign keys' => [
        'target_id' => [
          'table' => 'file_managed',
          'columns' => ['target_id' => 'fid'],
        ],
      ],
    ];

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {

    $random = new Random();

    $values['text'] = $random->paragraphs(5);

    $values['provider'] = 'google';
    $values['model'] = 'standard';
    $values['configuration'] = [
      'language' => 'en-US',
      'sample_rate_hertz' => 16000,
      'encoding' => 'LINEAR16',
    ];

    $dirname = 'public://audio';
    \Drupal::service('file_system')->prepareDirectory($dirname, FileSystemInterface::CREATE_DIRECTORY);
    $destination = $dirname . '/' . $random->name(10, TRUE) . '.mp4';
    $data = $random->paragraphs(3);
    /** @var \Drupal\file\FileRepositoryInterface $file_repository */
    $file_repository = \Drupal::service('file.repository');
    $file = $file_repository->writeData($data, $destination, FileSystemInterface::EXISTS_ERROR);
    $values['target_id'] = $file->id();

    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    $element = [];

    // We need the field-level 'default_image' setting, and $this->getSettings()
    // will only provide the instance-level one, so we need to explicitly fetch
    // the field.
    $settings = $this->getFieldDefinition()->getFieldStorageDefinition()->getSettings();

    $scheme_options = \Drupal::service('stream_wrapper_manager')->getNames(StreamWrapperInterface::WRITE_VISIBLE);
    $element['uri_scheme'] = [
      '#type' => 'radios',
      '#title' => $this->t('Upload destination'),
      '#options' => $scheme_options,
      '#default_value' => $settings['uri_scheme'],
      '#description' => $this->t('Select where the final files should be stored. Private file storage has significantly more overhead than public files, but allows restricted access to files within this field.'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    $element = [];
    $settings = $this->getSettings();

    $element['file_directory'] = [
      '#type' => 'textfield',
      '#title' => $this->t('File directory'),
      '#default_value' => $settings['file_directory'],
      '#description' => $this->t('Optional subdirectory within the upload destination where files will be stored. Do not include preceding or trailing slashes.'),
      '#weight' => -1,
    ];

    $element['file_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('File name'),
      '#default_value' => $settings['file_name'],
      '#description' => $this->t('Optional file name for the mp3 files created.'),
      '#weight' => 0,
    ];

    return $element;
  }

}
