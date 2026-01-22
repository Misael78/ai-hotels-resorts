<?php

namespace Drupal\chatgpt_plugin;

use Drupal\Component\Serialization\Json;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Drupal\Core\Config\ConfigFactory;

/**
 * Service class to call OpenAI GPT APIs.
 */
class GPTApiService {

  /**
   * The default http client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Configuration Factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * Constructor of the class.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   An http client.
   * @param Drupal\Core\Config\ConfigFactory $configFactory
   *   Configuration factory.
   */
  public function __construct(ClientInterface $http_client, ConfigFactory $configFactory) {
    $this->httpClient = $http_client;
    $this->configFactory = $configFactory;
  }

  /**
   * Function to call the ChatGPT API.
   *
   * @param string $prompt_text
   *   Prompt text to feed the GPT API.
   *
   * @return string|GuzzleException
   *   GPT response message on success or error.
   */
  public function getGptResponse($prompt_text) {
    $config = $this->configFactory->get('chatgpt_plugin.adminsettings');
    $access_token = $config->get('access_token');
    $temperature = (int) $config->get('chatgpt_temperature');
    $max_tokens = (int) $config->get('chatgpt_max_token');
    $content_moderation_endpoint = $config->get('moderation_endpoint');

    // Preparing payload for GPT API Call.
    $url = $config->get('completion_endpoint');
    $model = $config->get('model_name');
    $message[] = [
      "role" => "user",
      "content" => $prompt_text,
    ];

    $payload = [
      "model" => $model,
      "messages" => $message,
      "temperature" => $temperature,
      "max_tokens" => $max_tokens,
    ];

    $header = [
      'Content-Type' => 'application/json',
      'Authorization' => 'Bearer ' . $access_token,
    ];
    $options = [
      'headers' => $header,
      'json' => $payload,
    ];

    // Calling OpenAI completion API.
    try {
      $response = $this->httpClient->request('POST', $url, $options);
      $result = $response->getBody()->getContents();
      $decoded_data = Json::decode($result);

      // Processing success response data.
      $text = $decoded_data['choices'][0]['message']['content'];
    }
    catch (GuzzleException $exception) {
      // Error handling for OpenAI API call.
      throw $exception;
    }

    // OpenAI Content Usage Policy Validation.
    $validation_payload = [
      "input" => $text,
    ];

    $validation_options = [
      'headers' => $header,
      'json' => $validation_payload,
    ];

    // Calling OpenAI Content Moderation API.
    try {
      $validation_response = $this->httpClient->request('POST', $content_moderation_endpoint, $validation_options);
      $validation_result = $validation_response->getBody()->getContents();
      $decoded_validation_data = Json::decode($validation_result);
      $policy_violation = $decoded_validation_data['results'][0]['flagged'];
    }
    catch (GuzzleException $exception) {
      // Error handling for ChatGPT API call.
      throw $exception;
    }

    if ($policy_violation) {
      $text = "The generated content violated OpenAI Content usage policy. Please generate again.";
    }

    return $text;
  }

}
