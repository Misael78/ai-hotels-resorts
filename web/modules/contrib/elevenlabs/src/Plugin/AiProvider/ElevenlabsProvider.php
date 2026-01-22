<?php

namespace Drupal\elevenlabs\Plugin\AiProvider;

use Drupal\ai\Attribute\AiProvider;
use Drupal\ai\Base\AiProviderClientBase;
use Drupal\ai\Exception\AiBadRequestException;
use Drupal\ai\OperationType\AudioToAudio\AudioToAudioInput;
use Drupal\ai\OperationType\AudioToAudio\AudioToAudioInterface;
use Drupal\ai\OperationType\AudioToAudio\AudioToAudioOutput;
use Drupal\ai\OperationType\GenericType\AudioFile;
use Drupal\ai\OperationType\SpeechToSpeech\SpeechToSpeechInput;
use Drupal\ai\OperationType\SpeechToSpeech\SpeechToSpeechInterface;
use Drupal\ai\OperationType\SpeechToSpeech\SpeechToSpeechOutput;
use Drupal\ai\OperationType\TextToSpeech\TextToSpeechInput;
use Drupal\ai\OperationType\TextToSpeech\TextToSpeechInterface;
use Drupal\ai\OperationType\TextToSpeech\TextToSpeechOutput;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\elevenlabs\ElevenLabsApiService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Plugin implementation of the 'ElevenLabs' provider.
 */
#[AiProvider(
  id: 'elevenlabs',
  label: new TranslatableMarkup('ElevenLabs'),
)]
class ElevenlabsProvider extends AiProviderClientBase implements
  ContainerFactoryPluginInterface,
  AudioToAudioInterface,
  SpeechToSpeechInterface,
  TextToSpeechInterface {

  /**
   * The ElevenLabs Client.
   *
   * @var \Drupal\elevenlabs\ElevenLabsApiService
   */
  protected $client;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The temporary files.
   *
   * @var array
   */
  protected $temporaryFiles = [];

  /**
   * Destructor.
   */
  public function __destruct() {
    foreach ($this->temporaryFiles as $file) {
      $file->delete();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->client = $container->get('elevenlabs.api');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguredModels(?string $operation_type = NULL, array $capabilities = []): array {
    $models = [];
    $model_data = $this->client->getModels();

    if (is_null($operation_type) || $operation_type == 'text_to_speech') {
      foreach ($model_data as $model) {
        if ($model['can_do_text_to_speech']) {
          $models[$model['model_id']] = $model['name'];
        }
      }
    }

    if (is_null($operation_type) || $operation_type == 'speech_to_speech') {
      foreach ($model_data as $model) {
        if ($model['can_do_voice_conversion']) {
          $models[$model['model_id']] = $model['name'];
        }
      }
    }

    if (is_null($operation_type) || $operation_type == 'audio_to_audio') {
      $models['default'] = 'Default';
    }

    return $models;
  }

  /**
   * {@inheritdoc}
   */
  public function isUsable(?string $operation_type = NULL, array $capabilities = []): bool {
    // If its not configured, it is not usable.
    if (!$this->getConfig()->get('api_key')) {
      return FALSE;
    }
    // If its one of the bundles that Mistral supports its usable.
    if ($operation_type) {
      return in_array($operation_type, $this->getSupportedOperationTypes());
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedOperationTypes(): array {
    return [
      'text_to_speech',
      'speech_to_speech',
      'audio_to_audio',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(): ImmutableConfig {
    return $this->configFactory->get('elevenlabs.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function getApiDefinition(): array {
    // Load the configuration.
    $definition = Yaml::parseFile($this->moduleHandler->getModule('elevenlabs')->getPath() . '/definitions/api_defaults.yml');
    return $definition;
  }

  /**
   * {@inheritdoc}
   */
  public function getModelSettings(string $model_id, array $generalConfig = []): array {
    // Map voices to modules.
    $voices = $this->client->getVoices()['voices'];
    $voice_options = [];
    $voice_descriptions = '';
    foreach ($voices as $voice) {
      if (in_array($model_id, $voice['high_quality_base_model_ids'])) {
        $voice_id = $voice['name'] . ' :: ' . $voice['voice_id'];
        $voice_options[$voice_id] = $voice_id;
        $description = '<p><strong>' . $voice['name'] . '</strong></p>';
        $description .= '<ul>';
        $description .= '<li>voice id: ' . $voice['voice_id'] . '</li>';
        foreach ($voice['labels'] as $key => $label) {
          $description .= '<li>' . $key . ':' . $label . '</li>';
        }
        $description .= '<li><a href="' . $voice['preview_url'] . '" target="_blank">Voice preview</a></li>';
        $description .= '</ul>';
        $voice_descriptions .= $description;
      }
    }
    if (!empty($voice_descriptions)) {
      $generalConfig['voice'] = [
        'label' => 'Voice',
        'description' => $voice_descriptions,
        'type' => 'text',
        'constraints' => [
          'options' => $voice_options,
        ],
        'required' => TRUE,
      ];
    }
    else {
      unset($generalConfig['voice']);
    }
    return $generalConfig;
  }

  /**
   * {@inheritdoc}
   */
  public function setAuthentication(mixed $authentication): void {
    // Set the new API key and reset the client.
    $this->client->setApiKey($authentication);
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-ignore-next-line
   */
  public function textToSpeech(string|TextToSpeechInput $input, string $model_id, array $tags = []): TextToSpeechOutput {

    // Normalize the input.
    $text = $input;
    if ($input instanceof TextToSpeechInput) {
      $text = $input->getText();
    }
    $text_hash = hash('md5', $text);
    $chunk_length = 5000;
    $wrappedText = wordwrap($text, $chunk_length, "\n", FALSE);
    $chunks = explode("\n", $wrappedText);

    // Clean up carriage returns in chunks and rebuild so they are closer to
    // max chunk length.
    $chunks_rebuilt = [];
    $chunk_index = 0;

    // Initialize the first chunk.
    $chunks_rebuilt[$chunk_index] = '';

    foreach ($chunks as $key => $chunk) {
      $chunks[$key] = rtrim($chunk);
      $updated_rebuilt = $chunks_rebuilt[$chunk_index] . $chunk;
      if (strlen($updated_rebuilt) < $chunk_length) {
        $chunks_rebuilt[$chunk_index] = $updated_rebuilt;
      }
      else {
        $chunk_index++;
        $chunks_rebuilt[$chunk_index] = $chunk;
      }
    }
    // Set chunks to the rebuilt version.
    $chunks = $chunks_rebuilt;

    // Start generating a file.
    $model_id = $this->configuration['model'];
    $voice_id = explode(' :: ', $this->configuration['voice'])[1];
    unset($this->configuration['model']);
    unset($this->configuration['voice']);
    // Pass configuration directly - ElevenLabsApiService will separate
    // voice_settings from root-level parameters.
    $configuration = $this->configuration;

    $audio_files = [];

    $this->loggerFactory->get('elevenlabs')->info('Text:<br><pre>' . var_export($chunks, TRUE) . '</pre> ');

    // Loop through chunks if we need too.
    if (strlen($text) > $chunk_length) {
      $num_chunks = count($chunks);
      foreach ($chunks as $key => $chunk) {
        // Add previous text.
        if ($key > 0) {
          $previous_index = $key - 1;
          $configuration['previous_text'] = $chunks[$previous_index];
        }
        $next_index = $key + 1;
        if (isset($chunks[$next_index])) {
          $configuration['next_text'] = $chunks[$next_index];
        }
        $response = $this->client->textToSpeech($chunk, $voice_id, $model_id, $configuration);
        if (empty($response)) {
          throw new AiBadRequestException('No audio found');
        }
        $filename = "elevenlabs-tts-$text_hash-$key-$num_chunks.mp3";
        $audio_file = new AudioFile($response, 'audio/mpeg', $filename);
        $audio_files[] = $audio_file;
      }
    }
    else {
      // Pass the whole string in one go.
      $response = $this->client->textToSpeech($text, $voice_id, $model_id, $configuration);
      if (empty($response)) {
        throw new AiBadRequestException('No audio found');
      }
      $filename = "elevenlabs-tts-{$text_hash}.mp3";
      $audio_file = new AudioFile($response, 'audio/mpeg', $filename);
      $audio_files[] = $audio_file;
    }

    if (count($audio_files) > 1) {
      // Concat files.
      $audio_binary_data = NULL;
      foreach ($audio_files as $audio_file) {
        $audio_binary_data .= $audio_file->getBinary();
      }
      $filename = "elevenlabs-tts-{$text_hash}.mp3";
      $input = new AudioFile($audio_binary_data, 'audio/mpeg', $filename);
    }
    else {
      $input = $audio_files[0];
    }

    return new TextToSpeechOutput([$input], $response, []);
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-ignore-next-line
   */
  public function speechToSpeech(string|array|SpeechToSpeechInput $input, string $model_id, array $tags = []): SpeechToSpeechOutput {
    // Normalize the input.
    $audio = $input;
    if ($input instanceof SpeechToSpeechInput) {
      $audio = $input->getAudioFile()->getBinary();
    }
    // Start generating a file.
    $model = $this->configuration['model'];
    unset($this->configuration['model']);
    $configuration = $this->configuration;
    $voice_id = explode(' :: ', $this->configuration['voice'])[1];
    $response = $this->client->speechToSpeech($audio, $voice_id, $model, $configuration);
    if (empty($response)) {
      throw new AiBadRequestException('No audio found');
    }
    $input = new AudioFile($response, 'audio/mpeg', 'elevenlabs-speech-to-speech.mp3');
    return new SpeechToSpeechOutput([$input], $response, []);
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-ignore-next-line
   */
  public function audioToAudio(string|array|AudioToAudioInput $input, string $model_id, array $tags = []): AudioToAudioOutput {
    // Normalize the input.
    $audio = $input;
    if ($input instanceof AudioToAudioInput) {
      $audio = $input->getAudioFile()->getBinary();
    }
    // Start generating a file.
    $response = $this->client->isolate($audio);
    if (empty($response)) {
      throw new AiBadRequestException('No audio found');
    }
    /** @var \Drupal\ai\OperationType\GenericType\AudioFile $input */
    $input = new AudioFile($response, 'audio/mpeg', 'elevenlabs.mp3');
    return new AudioToAudioOutput([$input], $response, []);
  }

  /**
   * Gets the raw client.
   *
   * This is the client for inference.
   *
   * @return \Drupal\elevenlabs\ElevenLabsApiService
   *   The Elevenlabs Client.
   */
  public function getClient(): ElevenLabsApiService {
    return $this->client;
  }

}
