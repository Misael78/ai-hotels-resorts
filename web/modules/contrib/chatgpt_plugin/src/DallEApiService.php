<?php

namespace Drupal\chatgpt_plugin;

use Drupal\Component\Serialization\Json;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Drupal\Core\Config\ConfigFactory;

/**
 * Service class to call OpenAI DAll.E APIs.
 */
class DallEApiService {

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
   * Function to call the OpenAI DALL.E. API.
   *
   * @param string $prompt_text
   *   Prompt text to feed the DALL.E. API.
   * @param int $image_count
   *   Number of images to be generated.
   * @param string $image_size
   *   Size of the image like 25x256.
   *
   * @return string|GuzzleException
   *   An array contianing URLs of the generated images or exception.
   */
  public function getDalleResponse($prompt_text, $image_count, $image_size) {
    $config = $this->configFactory->get('chatgpt_plugin.adminsettings');
    $url = $config->get('dalle_endpoint');
    $access_token = $config->get('access_token');

    $payload = [
      "prompt" => $prompt_text,
      "n" => $image_count,
      "size" => $image_size,
    ];

    $header = [
      'Content-Type' => 'application/json',
      'Authorization' => 'Bearer ' . $access_token,
    ];
    $options = [
      'headers' => $header,
      'json' => $payload,
    ];

    // Calling ChatGPT completion API.
    try {
      $response = $this->httpClient->request('POST', $url, $options);
      $result = $response->getBody()->getContents();
      $decoded_data = Json::decode($result);
    }
    catch (GuzzleException $exception) {
      // Error handling for ChatGPT API call.
      throw $exception;
    }

    // Processing success response data.
    $image_urls = $decoded_data['data'];
    return $image_urls;
  }

}
