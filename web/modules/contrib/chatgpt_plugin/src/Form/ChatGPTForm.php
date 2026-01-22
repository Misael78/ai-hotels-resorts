<?php

namespace Drupal\chatgpt_plugin\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\chatgpt_plugin\GPTApiService;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Function to create the ChatGPT Search popup.
 */
class ChatGPTForm extends FormBase {

  /**
   * The default http client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The custom GPT API service.
   *
   * @var \Drupal\chatgpt_plugin\GPTApiService
   */
  protected $gptApi;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client'),
      $container->get('chatgpt_plugin.gpt_api'),
      $container->get('language_manager'),
    );
  }

  /**
   * Constructor of the class.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   An http client.
   * @param \Drupal\chatgpt_plugin\GPTApiService $gpt_api
   *   Our custom GPT API service.
   * @param Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(ClientInterface $http_client, GPTApiService $gpt_api, LanguageManagerInterface $language_manager) {
    $this->httpClient = $http_client;
    $this->gptApi = $gpt_api;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'chatgpt_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $fieldName = NULL) {
    $form['chatgpt_search'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Generate content using ChatGPT'),
      '#description' => $this->t('Provide your prompt here like - Use of AI in Finance.'),
      '#maxlength' => 1024,
    ];

    $form['chatgpt_submit'] = [
      '#type' => 'button',
      '#value' => 'Generate',
      '#ajax' => [
        'callback' => '::chatgptSearchResult',
        'effect' => 'fade',
        'event' => 'click',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Getting Result from OpenAI ChatGPT...'),
        ],
      ],
    ];

    $form['chatgpt_result'] = [
      '#type' => 'markup',
      '#title' => $this->t('ChatGPT Result'),
      '#markup' => '<div id="chatgpt-result"></div>',
    ];

    $form['copy_content'] = [
      '#type' => 'submit',
      '#tag' => 'input',
      '#attributes' => [
        'type' => 'button',
        'value' => $this->t("Use this content"),
        'class' => ['copybutton button'],
        'name' => $fieldName,
      ],
      '#ajax' => [
        'callback' => '::copyGeneratedContent',
        'event' => 'click',
        'wrapper' => 'translate-form-wrapper',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * Ajax callback function to call the ChatGPT API.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function chatgptSearchResult(array &$form, FormStateInterface $form_state) {
    $ajax_response = new AjaxResponse();
    $site_default_lang = $this->languageManager->getDefaultLanguage()->getName();
    $input_text = $form_state->getValue('chatgpt_search');
    $prompt_text = "Write an article on this topic in " . $site_default_lang . " - " . $input_text;

    // Calling our custom GPT API Service..
    try {
      $response = $this->gptApi->getGptResponse($prompt_text);
    }
    catch (GuzzleException $exception) {
      // Error handling for GPT Service call.
      $error_msg = $exception->getMessage();
      $ajax_response->addCommand(new HtmlCommand('#chatgpt-result', $error_msg));
      return $ajax_response;
    }

    // Processing success response data.
    $ajax_response->addCommand(new HtmlCommand('#chatgpt-result', nl2br($response)));
    return $ajax_response;
  }

  /**
   * Ajax callback to close the dialog modal.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function copyGeneratedContent(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    // Close the modal.
    $response->addCommand(new CloseDialogCommand());

    return $response;
  }

}
