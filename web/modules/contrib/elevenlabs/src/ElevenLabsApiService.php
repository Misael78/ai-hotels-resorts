<?php

namespace Drupal\elevenlabs;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\elevenlabs\Form\ElevenLabsSettingsForm;
use Drupal\key\KeyRepositoryInterface;
use GuzzleHttp\Client;

/**
 * Takes care of the API calls to ElevenLabs.
 *
 * @phpstan-consistent-constructor
 */
class ElevenLabsApiService {

  /**
   * The API base path.
   */
  protected string $basePath = 'https://api.elevenlabs.io/v1/';

  /**
   * The default model.
   */
  public static string $defaultModel = 'eleven_multilingual_v2';

  /**
   * The api key.
   */
  protected string $apiKey;

  /**
   * The config for ElevenLabs.
   */
  protected ImmutableConfig $config;

  /**
   * The guzzle client.
   */
  protected Client $client;

  /**
   * The cache backend.
   */
  protected CacheBackendInterface $cache;

  /**
   * The config factory.
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The key repository.
   */
  protected KeyRepositoryInterface $keyRepository;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $loggerChannel;

  /**
   * Constructs the ElevenLabsApiService.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \GuzzleHttp\Client $client
   *   The Guzzle HTTP client.
   * @param \Drupal\key\KeyRepositoryInterface $keyRepository
   *   The key repository.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cacheBackend
   *   The cache backend.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    Client $client,
    KeyRepositoryInterface $keyRepository,
    CacheBackendInterface $cacheBackend,
    LoggerChannelFactoryInterface $logger_factory,
  ) {
    $this->configFactory = $configFactory;
    $this->client = $client;
    $this->keyRepository = $keyRepository;
    $this->cache = $cacheBackend;
    $this->loggerChannel = $logger_factory->get('elevenlabs');
    $apiKey = $this->configFactory->get(ElevenLabsSettingsForm::CONFIG_NAME)->get('api_key');
    if ($apiKey) {
      $this->apiKey = $this->keyRepository->getKey($apiKey)->getKeyValue();
    }
  }

  /**
   * Is the API setup and working.
   *
   * @return bool
   *   Is it working or not.
   */
  public function isSetup() {
    try {
      $this->getUserInfo();
      return TRUE;
    }
    catch (\Exception $e) {
      return FALSE;
    }
  }

  /**
   * Set the api key.
   *
   * @param string $apiKey
   *   The api key.
   */
  public function setApiKey(string $apiKey) {
    $this->apiKey = $apiKey;
  }

  /**
   * Get voices.
   *
   * @return array
   *   An array of voices.
   */
  public function getVoices(): array {
    $data = $this->cache->get('elevenlabs_voices_list');
    if ($data) {
      return $data->data;
    }
    $voices = json_decode($this->call('voices'), TRUE);

    $this->cache->set('elevenlabs_voices_list', $voices, time() + 3600);
    return $voices;
  }

  /**
   * Get models.
   *
   * @return array
   *   An array of models.
   */
  public function getModels(): array {
    $data = $this->cache->get('elevenlabs_models_list');
    if ($data) {
      return $data->data;
    }
    $models = json_decode($this->call('models'), TRUE);

    $this->cache->set('elevenlabs_models_list', $models, time() + 3600);
    return $models;
  }

  /**
   * Get user info.
   *
   * @return array
   *   The user info.
   */
  public function getUserInfo(): array {
    return json_decode($this->call('user'), TRUE);
  }

  /**
   * Generate voice.
   *
   * @param string $text
   *   Text.
   * @param string $voice_id
   *   The voice id.
   * @param string $model_id
   *   The model id.
   * @param array $options
   *   Extra options to send.
   *
   * @return string
   *   The binary.
   */
  public function textToSpeech($text, $voice_id, $model_id = '', array $options = []) {
    // Build voice settings with module defaults (differ from API defaults).
    // Per https://elevenlabs.io/docs/api-reference/text-to-speech/convert
    $voice_settings = [
      'stability' => $options['stability'] ?? 0,
      'similarity_boost' => $options['similarity_boost'] ?? 0,
      'style' => $options['style'] ?? 0.5,
    ];

    // Add optional voice settings if provided.
    if (isset($options['speed'])) {
      $voice_settings['speed'] = $options['speed'];
    }
    if (isset($options['use_speaker_boost'])) {
      $voice_settings['use_speaker_boost'] = $options['use_speaker_boost'];
    }

    // Build base payload.
    $payload = [
      'text' => $text,
      'model_id' => $model_id,
      'voice_settings' => $voice_settings,
    ];

    // Add root-level API parameters if provided.
    // Per https://elevenlabs.io/docs/api-reference/text-to-speech/convert
    $root_level_params = [
      'apply_text_normalization',
      'apply_language_text_normalization',
      'pronunciation_dictionary_locators',
      'seed',
      'previous_text',
      'next_text',
      'previous_request_ids',
      'next_request_ids',
      'use_pvc_as_ivc',
      'language_code',
    ];

    foreach ($root_level_params as $param) {
      if (isset($options[$param])) {
        $payload[$param] = $options[$param];
      }
    }

    return $this->call('text-to-speech/' . $voice_id, 'POST', $payload);
  }

  /**
   * Speech to speech.
   *
   * @param string $audio
   *   The audio binary.
   * @param string $voiceId
   *   The voice id.
   * @param string $modelId
   *   The model id.
   * @param array $options
   *   Extra options to send.
   *
   * @return string
   *   The binary.
   */
  public function speechToSpeech($audio, $voiceId, $modelId, array $options = []) {
    /** @var array $options */
    $options['model_id'] = $modelId;
    $options['stability'] = $options['stability'] ?? 0;
    $options['similarity_boost'] = $options['similarity_boost'] ?? 0;
    $options['style'] = $options['style'] ?? 0.5;
    $options['use_speaker_boost'] = $options['use_speaker_boost'] ?? TRUE;

    // Upload file.
    $guzzleOptions['multipart'] = [
      [
        'name' => 'audio',
        'contents' => $audio,
        'filename' => 'tmp.mp3',
      ],
    ];

    // Add extra options.
    foreach ($options as $key => $value) {
      if (is_array($value)) {
        foreach ($value as $subValue) {
          $guzzleOptions['multipart'][] = [
            'name' => $key,
            'contents' => $subValue,
          ];
        }
      }
      else {
        $guzzleOptions['multipart'][] = [
          'name' => $key,
          'contents' => $value,
        ];
      }
    }
    return $this->call('speech-to-speech/' . $voiceId, 'POST', [], [], $guzzleOptions);
  }

  /**
   * Isolate audio.
   *
   * @param string $audio
   *   The audio binary.
   *
   * @return string
   *   The binary.
   */
  public function isolate($audio) {
    // Upload file.
    $guzzleOptions['multipart'] = [
      [
        'name' => 'audio',
        'contents' => $audio,
        'filename' => 'tmp.mp3',
      ],
    ];

    return $this->call('audio-isolation', 'POST', [], [], $guzzleOptions);
  }

  /**
   * Get history listing.
   *
   * @param int $pageSize
   *   How many objects to get.
   *
   * @return array
   *   The history.
   */
  public function getHistoryListing($pageSize = 10) {
    return json_decode($this->call('history', 'GET', [], ['page_size' => $pageSize]), TRUE);
  }

  /**
   * Make the API call.
   *
   * @param string $endpoint
   *   The endpoint to use.
   * @param string $method
   *   The method to use.
   * @param array $payload
   *   Any type of payload to attach.
   * @param array $queryString
   *   Any extra query string objects.
   * @param array $options
   *   Any extra Guzzle options.
   *
   * @return string
   *   The response unencoded.
   */
  private function call(string $endpoint, string $method = "GET", array $payload = [], array $queryString = [], array $options = []): string {
    if (empty($this->apiKey)) {
      $this->loggerChannel->error('API request failed: No API Key set.');
      throw new \Exception("No API Key set.");
    }

    // Set initial options.
    $guzzleOptions = [
      'connect_timeout' => 5,
      'timeout' => 120,
      'headers' => [
        'xi-api-key' => $this->apiKey,
      ],
    ];

    if (!isset($options['multipart'])) {
      $guzzleOptions['headers']['Content-Type'] = 'application/json';
    }

    // Overwrite if needed.
    $guzzleOptions = array_merge_recursive($options, $guzzleOptions);

    // Set payload if needed.
    if (!empty($payload)) {
      $guzzleOptions['json'] = $payload;
    }

    $url = $this->basePath . $endpoint;
    if (count($queryString)) {
      $url .= '?' . http_build_query($queryString);
    }

    try {
      // Log the request for debugging purposes.
      if (function_exists('drush_print')) {
        drush_print("Making request to ElevenLabs API: " . $url);
      }
      $this->loggerChannel->notice('Making API request to @endpoint',
        ['@endpoint' => $endpoint]);

      $res = $this->client->request($method, $url, $guzzleOptions);
      return $res->getBody();
    }
    catch (\Exception $e) {
      // Log detailed error information.
      $message = 'ElevenLabs API request failed: ' . $e->getMessage();
      $this->loggerChannel->error('@message', ['@message' => $message]);

      if (function_exists('drush_print')) {
        drush_print("ERROR: " . $message);
      }

      // Re-throw the exception to be handled by the caller.
      throw new \Exception($message, 0, $e);
    }
  }

}
