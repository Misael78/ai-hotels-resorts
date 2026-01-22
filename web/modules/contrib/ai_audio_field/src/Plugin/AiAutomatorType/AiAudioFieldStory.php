<?php

namespace Drupal\ai_audio_field\Plugin\AiAutomatorType;

use Drupal\Component\Serialization\Json;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\OperationType\TextToSpeech\TextToSpeechInput;
use Drupal\ai_automators\Attribute\AiAutomatorType;
use Drupal\ai_automators\PluginBaseClasses\RuleBase;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;

/**
 * The rules for an ai_audio_file field.
 */
#[AiAutomatorType(
  id: 'ai_audio_file',
  label: new TranslatableMarkup('AI Audio Field: Generate story'),
  field_rule: 'ai_audio_file',
  target: 'file',
)]
class AiAudioFieldStory extends RuleBase implements AiAutomatorTypeInterface, ContainerFactoryPluginInterface {

  use DependencySerializationTrait;

  /**
   * The instance loaded so far.
   *
   * @var array
   */
  protected array $providerInstances = [];

  /**
   * {@inheritDoc}
   */
  public $title = 'AI Audio Field: Generate story';

  /**
   * {@inheritDoc}
   */
  public function placeholderText() {
    return $this->t("Based on the context text create a dialogue between podcast interviewer Daniel Ayoade who is a fun loving character and Julia Majors who is a serious guest expert on the topic and answering the questions. Start with an introduction and created 10 dialogues back and forth and then end with a thank you to the guest.

The voices to use are:
Daniel Ayoade = {{ daniel }}
Julia Major = {{ julia }}

Context:
{{ context }}
");
  }

  /**
   * {@inheritDoc}
   */
  public function extraFormFields(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, FormStateInterface $formState, array $defaultValues = []) {
    $form = parent::extraFormFields($entity, $fieldDefinition, $formState, $defaultValues);

    $form['automator_voice_placeholders'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Voice placeholders'),
      '#description' => $this->t('These are the placeholders that can be used in the text field in both base and advanced mode. You have to add one at least.'),
      '#weight' => 20,
      '#prefix' => '<div id="voice-placeholders">',
      '#suffix' => '</div>',
      '#tree' => TRUE,
    ];

    $count = $formState->get('voices_count') ?? count($defaultValues['automator_voice_placeholders'] ?? []) ?? 1;
    $voices = $formState->getValue('voices') ?? $defaultValues['automator_voice_placeholders'] ?? [];
    $formState->set('voices_count', $count);

    $defaults = $this->aiPluginManager->getDefaultProviderForOperationType('text_to_speech');

    $instances = [];

    $providers = [];
    foreach ($this->aiPluginManager->getProvidersForOperationType('text_to_speech') as $id => $provider) {
      $providers[$id] = $provider['label'];
    }

    for ($i = 0; $i < $count; $i++) {
      $form['automator_voice_placeholders']['voice_' . $i] = [
        '#type' => 'fieldset',
        '#prefix' => '<div id="voice-' . $i . '">',
        '#suffix' => '</div>',
      ];
      $form['automator_voice_placeholders']['voice_' . $i]['placeholder'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Voice placeholder'),
        '#description' => $this->t('The placeholder that can be used in the text field to replace all things said by that specific person.'),
        '#attributes' => [
          'placeholder' => $this->t('daniel'),
        ],
        '#default_value' => $voices['voice_' . $i]['placeholder'] ?? '',
      ];

      $provider = $formState->getValue('voice_placeholders')['voice_' . $i]['provider'] ?? $voices['voice_' . $i]['provider'] ?? $defaults['provider_id'] ?? '';

      $form['automator_voice_placeholders']['voice_' . $i]['provider'] = [
        '#type' => 'select',
        '#title' => $this->t('Provider'),
        '#description' => $this->t('The provider that should be used for this voice placeholder.'),
        '#options' => $providers,
        '#default_value' => $provider,
        '#attributes' => [
          'data-voice-placeholder' => $i,
        ],
        '#submit' => [[AiAudioFieldStory::class, 'setProviderInstance']],
        '#element_validate' => [],
        '#ajax' => [
          'callback' => [$this, 'getModelsForProvider'],
          'wrapper' => 'voice-' . $i,
          'event' => 'change',
        ],
      ];

      $model = $formState->getValue('voice_placeholders')['voice_' . $i]['model'] ?? $voices['voice_' . $i]['model'] ?? $defaults['model_id'] ?? '';

      if ($provider) {
        $models = [];
        if (!isset($instances[$provider])) {
          $instances[$provider] = $this->aiPluginManager->createInstance($provider);
        }
        foreach ($instances[$provider]->getConfiguredModels('text_to_speech') as $id => $model_name) {
          $models[$id] = $model_name;
        }

        $form['automator_voice_placeholders']['voice_' . $i]['model'] = [
          '#type' => 'select',
          '#title' => $this->t('Model'),
          '#description' => $this->t('The model that should be used for this voice placeholder.'),
          '#options' => $models,
          '#default_value' => $model,
        ];

        // We need the configurations, since voices often are there.
        if ($model) {
          $form['automator_voice_placeholders']['voice_' . $i]['configuration'] = [
            '#type' => 'fieldset',
            '#tree' => TRUE,
          ];
          $schema = $instances[$provider]->getAvailableConfiguration('text_to_speech', $model);
          foreach ($schema as $key => $definition) {
            $form['automator_voice_placeholders']['voice_' . $i]['configuration'][$key]['#type'] = $this->formHelper->mapSchemaTypeToFormType($definition);
            $form['automator_voice_placeholders']['voice_' . $i]['configuration'][$key]['#required'] = $definition['required'] ?? FALSE;
            $form['automator_voice_placeholders']['voice_' . $i]['configuration'][$key]['#title'] = $definition['label'] ?? $key;
            $form['automator_voice_placeholders']['voice_' . $i]['configuration'][$key]['#description'] = $definition['description'] ?? '';
            $form['automator_voice_placeholders']['voice_' . $i]['configuration'][$key]['#default_value'] = $voices['voice_' . $i]['configuration'][$key] ?? $definition['default'] ?? NULL;
            if (isset($definition['constraints'])) {
              foreach ($definition['constraints'] as $form_key => $value) {
                if ($form_key == 'options') {
                  $options = array_combine($value, $value);
                  if (empty($definition['required'])) {
                    $options = ['' => 'Select an option'] + $options;
                  }
                  $form['automator_voice_placeholders']['voice_' . $i]['configuration'][$key]['#options'] = $options;
                  continue;
                }
                $form['automator_voice_placeholders']['voice_' . $i]['configuration'][$key]['#' . $form_key] = $value;
              }
            }
          }
        }
      }

    }

    $form['automator_voice_placeholders']['add'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add voice placeholder'),
      '#submit' => [[AiAudioFieldStory::class, 'addVoicePlaceholder']],
      '#ajax' => [
        'callback' => [$this, 'addVoicePlaceholderCallback'],
        'wrapper' => 'voice-placeholders',
        'event' => 'click',
        'prevent' => 'click',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function validateConfigValues($form, FormStateInterface $formState) {
    $found = FALSE;
    foreach ($formState->getValue('automator_voice_placeholders') as $key => $voice) {
      if ($key == 'add') {
        continue;
      }
      if (!empty($voice['placeholder']) && !empty($voice['provider']) && !empty($voice['model'])) {
        $found = TRUE;
        break;
      }
    }
    if (!$found) {
      $formState->setErrorByName('automator_voice_placeholders', $this->t('You have to add at least one voice placeholder.'));
    }
  }

  /**
   * {@inheritDoc}
   */
  public function generate(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    $prompts = parent::generate($entity, $fieldDefinition, $automatorConfig);
    $total = [];
    // Add to get functional output.
    foreach ($prompts as $key => $prompt) {
      $prompt .= "\n\nDo not include any explanations, only provide a RFC8259 compliant JSON response following this format without deviation.

[
  {\"value\": {\"speaker\": \"The name of the speaker\",\"speaker_id\": \"The id of the speaker\",\"text\": \"The dialogue\"}},
  {\"value\": {\"speaker\": \"The name of the speaker\",\"speaker_id\": \"The id of the speaker\",\"text\": \"The dialogue\"}}
]

An example of a 3 dialogue output would be:
[
  {\"value\": {\"speaker\": \"Jonas Nilsson\",\"speaker_id\": \"daniel\",\"text\": \"Hello and welcome to the show Jenny!\"}},
  {\"value\": {\"speaker\": \"Jenny Moore\",\"speaker_id\": \"grete\",\"text\": \"Thank you Jonas, it's nice to be here.\"}},
  {\"value\": {\"speaker\": \"Jonas Nilsson\",\"speaker_id\": \"daniel\",\"text\": \"I would like to start with asking you about tomorrows weather?\"}}
]";

      $prompts[$key] = $prompt;
    }

    $instance = $this->prepareLlmInstance('chat', $automatorConfig);
    foreach ($prompts as $prompt) {
      $values = $this->runChatMessage($prompt, $automatorConfig, $instance, $entity);
      if (!empty($values)) {
        $total = array_merge_recursive($total, $values);
      }
    }

    return $total;
  }

  /**
   * {@inheritDoc}
   */
  public function verifyValue(ContentEntityInterface $entity, $value, FieldDefinitionInterface $fieldDefinition, $automatorConfig) {
    // Has to have a alt text.
    if (empty($value['speaker']) || empty($value['speaker_id']) || empty($value['text'])) {
      return FALSE;
    }

    // Otherwise it is ok.
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function storeValues(ContentEntityInterface $entity, array $values, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    $items = [];
    $voices = $this->getVoicePlaceholders($automatorConfig);
    foreach ($values as $value) {
      $data = $voices[$value['speaker_id']];
      if (!isset($data['provider']) || !isset($data['model'])) {
        continue;
      }
      $target_id = $this->generateAudio($value['text'], $data['provider'], $data['model'], $data['configuration']);
      $item = [
        'target_id' => $target_id,
        'text' => $value['text'],
        'provider' => $data['provider'],
        'model' => $data['model'],
        'configuration' => Json::encode($data['configuration']),
      ];
      $items[] = $item;
    }
    $entity->set($fieldDefinition->getName(), $items);
  }

  /**
   * Generate the audio file.
   *
   * @param string $text
   *   The text to generate audio from.
   * @param string $provider
   *   The provider to use.
   * @param string $model
   *   The model to use.
   * @param array $configuration
   *   The configuration to use.
   *
   * @return int
   *   The file id.
   */
  private function generateAudio(string $text, string $provider, string $model, array $configuration): int {
    $provider = $this->aiPluginManager->createInstance($provider);
    $input = new TextToSpeechInput($text);
    $output = $provider->textToSpeech($input, $model, $configuration);
    $file = $output->getNormalized()[0]->getAsFileEntity('public://', 'test.mp3');
    return $file->id();
  }

  /**
   * Get the voice placeholders based on keys.
   *
   * @param array $automatorConfig
   *   The automator configuration.
   *
   * @return array
   *   The voice placeholders.
   */
  public function getVoicePlaceholders(array $automatorConfig) {
    $placeholders = [];
    foreach ($automatorConfig['voice_placeholders'] as $voice) {
      $placeholders[$voice['placeholder']] = $voice;
    }
    return $placeholders;
  }

  /**
   * Add a voice placeholder.
   */
  public static function addVoicePlaceholder(array &$form, FormStateInterface $formState) {
    $count = $formState->get('voices_count') ?? count($formState->get('voices') ?? []) ?? 0;
    $newCount = $count + 1;
    $formState->set('voices_count', $newCount);
    $formState->setRebuild(TRUE);
  }

  /**
   * Get models for provider.
   */
  public function getModelsForProvider(array &$form, FormStateInterface $formState) {
    // Get the trigger element.
    $trigger = $formState->getTriggeringElement();
    $voicePlaceholder = $trigger['#attributes']['data-voice-placeholder'];
    // Get the provider.
    $formState->setRebuild(TRUE);
    return $form['automator_container']['automator_voice_placeholders']['voice_' . $voicePlaceholder];
  }

  /**
   * Add a voice placeholder callback.
   */
  public function addVoicePlaceholderCallback(array &$form, FormStateInterface $formState) {
    return $form['automator_container']['automator_voice_placeholders'];
  }

}
