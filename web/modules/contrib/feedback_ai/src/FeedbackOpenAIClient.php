<?php

namespace Drupal\feedback_ai;

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Utility\Error;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * Provides a description of the FeedbackOpenAIClient class.
 */
class FeedbackOpenAIClient {
  /**
   * The HTTP client used for API requests.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;
  /**
   * The API key for accessing the OpenAI API.
   *
   * @var string
   */
  protected $apiKey;

  /**
   * The endpoint URL for the OpenAI API.
   *
   * @var string
   */
  protected $endpoint;

  /**
   * The API model used for sentiment analysis.
   *
   * @var string
   */
  protected $apiModel;
  /**
   * The maximum number of tokens allowed for API requests.
   *
   * @var int
   */
  protected $apiMaxToken;

  public function __construct($config_factory) {
    $this->httpClient = new Client();
    $this->apiKey = $config_factory->get('feedback_openai.settings')->get('feedbackai_secret_key');
    $this->endpoint = $config_factory->get('feedback_openai.settings')->get('feedbackai_endpoint');
    $this->apiModel = $config_factory->get('feedback_openai.settings')->get('feedbackai_api_model');
    $this->apiMaxToken = $config_factory->get('feedback_openai.settings')->get('feedbackai_api_max_token');
  }

  /**
   * Accepts and returns the result in array.
   *
   * @param string $messages
   *   The messages to be processed.
   *
   * @return array
   *   Array with positive, negative or neutral feedback senntiment analyzer .
   */
  public function analyzeSentiment($messages) {
    try {
      $response = $this->httpClient->post($this->endpoint, [
        'headers' => [
          'Content-Type' => 'application/json',
          'Authorization' => 'Bearer ' . $this->apiKey,
        ],
        'json' => [
          'model' => $this->apiModel,
          'messages' => $messages,
          'temperature' => 2,
          'max_tokens' => (int) $this->apiMaxToken,
          'top_p' => 1,
          'frequency_penalty' => 0,
          'presence_penalty' => 0,
        ],
      ]);
      $statusCode = $response->getStatusCode();
      $responseData = json_decode($response->getBody()->getContents(), TRUE);

      return ['data' => $responseData, 'status_code' => $statusCode];

    }
    catch (RequestException $e) {
      DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '10.1.0', fn() => Error::logException(\Drupal::logger('feedback_ai_sentiment_response'), $e), fn() => watchdog_exception('feedback_ai_sentiment_response', $e));
      return ['data' => NULL, 'status_code' => $e->getCode()];
    }
  }

}
