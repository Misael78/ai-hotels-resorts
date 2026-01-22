<?php

namespace Drupal\chatgpt_plugin\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\node\Entity\Node;
use Drupal\chatgpt_plugin\GPTApiService;
use Drupal\chatgpt_plugin\DallEApiService;

/**
 * Function to create the ChatGPT Search popup.
 */
class ChatGPTAssistToolForm extends FormBase {

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
   * The custom GPT API service.
   *
   * @var \Drupal\chatgpt_plugin\DallEApiService
   */
  protected $dallEApi;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client'),
      $container->get('chatgpt_plugin.gpt_api'),
      $container->get('chatgpt_plugin.dalle_api'),
    );
  }

  /**
   * Constructor of the class.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   An http client.
   * @param \Drupal\chatgpt_plugin\GPTApiService $gpt_api
   *   Our custom GPT API service.
   * @param \Drupal\chatgpt_plugin\DallEApiService $dallE_api
   *   Our custom dalle API service.
   */
  public function __construct(ClientInterface $http_client, GPTApiService $gpt_api, DallEApiService $dallE_api) {
    $this->httpClient = $http_client;
    $this->gptApi = $gpt_api;
    $this->dallEApi = $dallE_api;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'chatgpt_assist_tool_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $options = [
      "image_generation" => $this->t('Generate images from text using DALL.E model'),
      "seo_generation" => $this->t('Generate SEO keywords from text content'),
      "article_creation" => $this->t('Create article using OpenAI GPT model'),
    ];
    $image_size_options = [
      "256x256" => '256x256',
      "512x512" => '512x512',
      "1024x1024" => '1024x1024',
    ];

    $form['chatgpt_assist_operation'] = [
      '#type' => 'select',
      '#title' => $this->t('OpenAI assistance tool operation.'),
      '#description' => $this->t('Select the operation which will be performed using OpenAI assistance tool.'),
      '#options' => $options,
    ];

    $form['image_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Number of Images to be generated.'),
      '#description' => $this->t('Provide the number of images to be generated.'),
      '#size' => 2,
      '#states' => [
        // Only show this field when the value is image_generation.
        'visible' => [
          ':input[name="chatgpt_assist_operation"]' => ['value' => 'image_generation'],
        ],
        'required' => [
          ':input[name="chatgpt_assist_operation"]' => ['value' => 'image_generation'],
        ],
      ],
    ];

    $form['image_size'] = [
      '#type' => 'select',
      '#title' => $this->t('Image size.'),
      '#description' => $this->t('Select the size of images to be generated.'),
      '#options' => $image_size_options,
      '#states' => [
        // Only show this field when the value is image_generation.
        'visible' => [
          ':input[name="chatgpt_assist_operation"]' => ['value' => 'image_generation'],
        ],
        'required' => [
          ':input[name="chatgpt_assist_operation"]' => ['value' => 'image_generation'],
        ],
      ],
    ];

    $form['article_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Preferred Article title.'),
      '#description' => $this->t('Provide the preferred title for your article.'),
      '#size' => 100,
      '#states' => [
        // Only show this field when the value is article_creation.
        'visible' => [
          ':input[name="chatgpt_assist_operation"]' => ['value' => 'article_creation'],
        ],
        'required' => [
          ':input[name="chatgpt_assist_operation"]' => ['value' => 'article_creation'],
        ],
      ],
    ];

    $form['word_limit'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Article word limit.'),
      '#description' => $this->t('Specify the number of words for the article to be generated.'),
      '#size' => 2,
      '#states' => [
        // Only show this field when the value is article_creation.
        'visible' => [
          ':input[name="chatgpt_assist_operation"]' => ['value' => 'article_creation'],
        ],
        'required' => [
          ':input[name="chatgpt_assist_operation"]' => ['value' => 'article_creation'],
        ],
      ],
    ];

    $form['chatgpt_assist_input'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Assistance tool input.'),
      '#description' => $this->t('Provide your prompt for OpenAI, like blockchain article for article creation option.'),
      '#maxlength' => 1024,
    ];

    $form['chatgpt_submit'] = [
      '#type' => 'button',
      '#value' => 'Generate Requested Content',
      '#ajax' => [
        'callback' => '::chatgptAssistanceSearch',
        'effect' => 'fade',
        'event' => 'click',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Getting Result from OpenAI...'),
        ],
      ],
    ];

    $form['chatgpt_result'] = [
      '#type' => 'markup',
      '#title' => $this->t('ChatGPT Assistance Tool Result'),
      '#markup' => '<div id="chatgpt-result"></div>',
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
  public function chatgptAssistanceSearch(array &$form, FormStateInterface $form_state) {
    $ajax_response = new AjaxResponse();
    $input = $form_state->getValue('chatgpt_assist_input');
    $selected_operation = $form_state->getValue('chatgpt_assist_operation');

    // Input payload preparation.
    if ($selected_operation == 'image_generation') {
      $model = "dalle";
      $image_count = (int) $form_state->getValue('image_number');
      $image_size = $form_state->getValue('image_size');
      $prompt_text = "Generate a creative image of - " . $input;
    }
    elseif ($selected_operation == 'seo_generation') {
      $model = "gpt";
      $prompt_text = "Extract SEO keywords from the following text - " . $input;
    }
    elseif ($selected_operation == 'article_creation') {
      $model = "gpt";
      $word_limit = $form_state->getValue('word_limit');
      $title = $form_state->getValue('article_title');
      $prompt_text = "Generate an engaging article based on the following text within " . $word_limit . " words -" . $input;
    }

    // Calling OpenAI APIs.
    try {
      if ($model == "gpt") {
        // Calling our custom GPT API service.
        $response = $this->gptApi->getGptResponse($prompt_text);
      }
      elseif ($model == "dalle") {
        // Calling our custom dalle api service.
        $response = $this->dallEApi->getDalleResponse($prompt_text, $image_count, $image_size);
      }
    }
    catch (GuzzleException $exception) {
      // Error handling for ChatGPT API call.
      $error_msg = $exception->getMessage();
      $ajax_response->addCommand(new HtmlCommand('#chatgpt-result', $error_msg));
      return $ajax_response;
    }

    // Displaying the data returned from OpenAI APIs.
    $numCol = 3;
    if ($selected_operation == 'image_generation') {
      $result = '<table><tr>';
      $count = 1;
      foreach ($response as $url) {
        $label = '<td><img src="' . $url['url'] . '"></td>';
        $result = $result . $label;
        $count++;
        if ($count > $numCol) {
          $result = $result . "</tr><tr>";
          $count = 1;
        }
      }
      $result = $result . "</tr></table>";

      // Displaying the image url.
      $ajax_response->addCommand(new HtmlCommand('#chatgpt-result', $result));
      return $ajax_response;
    }
    elseif ($selected_operation == 'article_creation') {
      $article_content = $response;

      $node = Node::create([
        'type'        => 'article',
        'title'       => $title,
        'body' => $article_content,
      ]);
      $node->save();

      $ajax_response->addCommand(new HtmlCommand('#chatgpt-result', "<b>Your article has been created successfully, please visit the content listing page.<b>"));
      return $ajax_response;
    }
    else {
      // Processing success response data.
      $ajax_response->addCommand(new HtmlCommand('#chatgpt-result', nl2br($response)));
      return $ajax_response;

    }

  }

}
