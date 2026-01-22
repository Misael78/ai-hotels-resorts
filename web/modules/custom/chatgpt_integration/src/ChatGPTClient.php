<?php

namespace Drupal\chatgpt_integration;

use GuzzleHttp\ClientInterface;
use Drupal\key\KeyRepository;

class ChatGPTClient {

  protected ClientInterface $httpClient;
  protected KeyRepository $keyRepository;

  public function __construct(ClientInterface $http_client, KeyRepository $key_repository) {
    $this->httpClient = $http_client;
    $this->keyRepository = $key_repository;
  }

  /**
   * Envía un prompt a ChatGPT y devuelve la respuesta.
   *
   * @param string $prompt
   *   El mensaje que se envía a ChatGPT.
   *
   * @return string|null
   *   La respuesta de ChatGPT o NULL si falla.
   */
  public function ask(string $prompt): ?string {
    // Reemplaza 'chatgpt_api' con el Machine Name de tu Key en Drupal Keys
    $api_key = $this->keyRepository->getKey('drupal_docker_localhost_dubon')->getKeyValue();

    $response = $this->httpClient->post('https://api.openai.com/v1/chat/completions', [
      'headers' => [
        'Authorization' => 'Bearer ' . $api_key,
        'Content-Type' => 'application/json',
      ],
      'json' => [
        'model' => 'gpt-4o',
        'messages' => [
          ['role' => 'user', 'content' => $prompt]
        ],
      ],
    ]);

    $data = json_decode($response->getBody(), TRUE);

    return $data['choices'][0]['message']['content'] ?? NULL;
  }

}

