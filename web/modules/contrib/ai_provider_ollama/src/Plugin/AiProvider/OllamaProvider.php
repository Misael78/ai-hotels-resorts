<?php

namespace Drupal\ai_provider_ollama\Plugin\AiProvider;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\AiProvider;
use Drupal\ai\Base\OpenAiBasedProviderClientBase;
use Drupal\ai\Exception\AiRequestErrorException;
use Drupal\ai\OperationType\Moderation\ModerationInput;
use Drupal\ai\OperationType\Moderation\ModerationOutput;
use Drupal\ai\OperationType\Moderation\ModerationResponse;
use Drupal\ai\Traits\OperationType\ChatTrait;
use Drupal\ai_provider_ollama\Models\Moderation\LlamaGuard3;
use Drupal\ai_provider_ollama\Models\Moderation\ShieldGemma;
use Drupal\ai_provider_ollama\OllamaControlApi;
use GuzzleHttp\Client as GuzzleClient;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'ollama' provider.
 */
#[AiProvider(
  id: 'ollama',
  label: new TranslatableMarkup('Ollama'),
)]
class OllamaProvider extends OpenAiBasedProviderClientBase {

  use StringTranslationTrait;
  use ChatTrait;

  /**
   * The Ollama Control API for configuration calls.
   *
   * @var \Drupal\ai_provider_ollama\OllamaControlApi
   */
  protected OllamaControlApi $controlApi;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected MessengerInterface $messenger;

  /**
   * Dependency Injection for the Ollama Control API.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->controlApi = $container->get('ai_provider_ollama.control_api');
    $instance->controlApi->setConnectData($instance->getBaseHost());
    $instance->currentUser = $container->get('current_user');
    $instance->messenger = $container->get('messenger');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguredModels(?string $operation_type = NULL, array $capabilities = []): array {
    // Graceful failure.
    try {
      $response = $this->controlApi->getModels();
    }
    catch (\Exception $e) {
      if ($this->currentUser->hasPermission('administer ai providers')) {
        $this->messenger->addError($this->t('Failed to get models from Ollama: @error', ['@error' => $e->getMessage()]));
      }
      $this->loggerFactory->get('ai_provider_ollama')->error('Failed to get models from Ollama: @error', ['@error' => $e->getMessage()]);
      return [];
    }
    $models = [];
    if (isset($response['models'])) {
      foreach ($response['models'] as $model) {
        $root_model = explode(':', $model['model'])[0];
        if ($operation_type == 'moderation') {
          if (in_array($root_model, [
            'shieldgemma',
            'llama-guard3',
          ])) {
            $models[$model['model']] = $model['name'];
          }
        }
        else {
          $models[$model['model']] = $model['name'];
        }
      }
    }
    return $models;
  }

  /**
   * {@inheritdoc}
   */
  public function isUsable(?string $operation_type = NULL, array $capabilities = []): bool {
    // If its one of the bundles that Ollama supports its usable.
    if (!$this->getBaseHost()) {
      return FALSE;
    }
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
      'chat',
      'embeddings',
      'moderation',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getModelSettings(string $model_id, array $generalConfig = []): array {
    return $generalConfig;
  }

  /**
   * {@inheritdoc}
   */
  public function setAuthentication(mixed $authentication): void {
    // Doesn't do anything.
    $this->client = NULL;
  }

  /**
   * Get control client.
   *
   * This is the client for controlling the Ollama API.
   *
   * @return \Drupal\ai_provider_ollama\OllamaControlApi
   *   The control client.
   */
  public function getControlClient(): OllamaControlApi {
    return $this->controlApi;
  }

  /**
   * {@inheritdoc}
   */
  protected function loadClient(): void {
    if (empty($this->client)) {
      // Set custom endpoint from host config.
      $host = $this->getBaseHost();
      $this->setEndpoint($host . '/v1');

      // Override the HTTP client with longer timeout.
      $this->setHttpClient(new GuzzleClient(['timeout' => 600]));

      // Use parent's createClient method without authentication.
      $this->client = $this->createClient();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function moderation(string|ModerationInput $input, ?string $model_id = NULL, array $tags = []): ModerationOutput {
    $this->loadClient();
    // Normalize the input if needed.
    $chat_input[] = [
      'role' => 'user',
      'content' => $input instanceof ModerationInput ? $input->getPrompt() : $input,
    ];

    $payload = [
      'model' => $model_id,
      'messages' => $chat_input,
    ] + $this->configuration;

    $response = $this->client->chat()->create($payload)->toArray();
    if (!isset($response['choices'][0]['message']['content'])) {
      throw new AiRequestErrorException('No content in moderation response.');
    }
    $message = $response['choices'][0]['message']['content'];

    $moderation_response = new ModerationResponse(FALSE);
    $root_model_id = explode(':', $model_id)[0];
    switch ($root_model_id) {
      case 'llama-guard3':
        $moderation_response = LlamaGuard3::moderationRules($message);
        break;

      case 'shieldgemma':
        $moderation_response = ShieldGemma::moderationRules($message);
        break;

      default:
        throw new AiRequestErrorException('Model not supported for moderation.');

    }

    return new ModerationOutput($moderation_response, $message, $response);
  }

  /**
   * {@inheritdoc}
   */
  public function embeddingsVectorSize(string $model_id): int {
    $this->loadClient();
    $data = $this->controlApi->embeddingsVectorSize($model_id);
    if ($data) {
      return $data;
    }
    // Fallback to parent method.
    return parent::embeddingsVectorSize($model_id);
  }

  /**
   * Gets the base host.
   *
   * @return string
   *   The base host.
   */
  protected function getBaseHost(): string {
    $host = rtrim($this->getConfig()->get('host_name'), '/');
    if ($this->getConfig()->get('port')) {
      $host .= ':' . $this->getConfig()->get('port');
    }
    return $host;
  }

  /**
   * {@inheritdoc}
   */
  public function maxEmbeddingsInput($model_id = ''): int {
    $this->loadClient();
    return $this->controlApi->embeddingsContextSize($model_id);
  }

}
