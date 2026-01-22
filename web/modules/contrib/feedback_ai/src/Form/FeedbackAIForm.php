<?php

namespace Drupal\feedback_ai\Form;

use Drupal\Component\Utility\EmailValidator;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\feedback_ai\FeedbackOpenAIClient;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Class FeedbackAIForm.
 *
 * Provides User submit feedback to sentiment analyzer.
 */
class FeedbackAIForm extends FormBase {
  /**
   * The openAiClient property.
   *
   * This property holds some information of api key.
   *
   * @var string
   */
  protected $openAiClient;
  /**
   * The messenger property.
   *
   * This property holds some sentiment text information.
   *
   * @var int
   */
  protected $messenger;
  /**
   * The email validate instance.
   *
   * @var \Drupal\Component\Utility\EmailValidator
   */
  protected $emailValidator;
  /**
   * The database instance.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;
  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;
  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * FeedbackAIForm constructor.
   *
   * @param string $openAiClient
   *   The value for openAiClient.
   * @param int $messenger
   *   The value for messenger.
   * @param \Drupal\Component\Utility\EmailValidator $emailValidator
   *   A email validator instance.
   * @param \Drupal\Core\Database\Connection $connection
   *   A database instance.
   * @param \Drupal\Core\Session\AccountProxy $currentUser
   *   The current user.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger factory.
   */
  public function __construct(FeedbackOpenAIClient $openAiClient, MessengerInterface $messenger, EmailValidator $emailValidator, Connection $connection, AccountProxy $currentUser, LoggerChannelFactory $loggerFactory) {
    $this->openAiClient = $openAiClient;
    $this->messenger = $messenger;
    $this->emailValidator = $emailValidator;
    $this->connection = $connection;
    $this->currentUser = $currentUser;
    $this->loggerFactory = $loggerFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('feedback_ai.openai_client'),
      $container->get('messenger'),
      $container->get('email.validator'),
      $container->get('database'),
      $container->get('current_user'),
      $container->get('logger.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'feedback_ai_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['#prefix'] = '<div class="sentiment-feedback-content">';
    $form['#suffix'] = '</div>';

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Your Name'),
      '#default_value' => '',
      '#required' => TRUE,
    ];

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Your Email'),
      '#default_value' => '',
      '#required' => TRUE,
    ];

    $form['feedback'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message'),
      '#required' => TRUE,
      '#attributes' => [
        'class' => ['comment-analysis'],
        'placeholder' => $this->t('Tap here to enter text...'),
      ],
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send Message'),
      '#attributes' => ['class' => ['feedback-submit-btn']],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    // Validate email field.
    $email = $form_state->getValue('email');
    if (!$this->emailValidator->isValid($email)) {
      $form_state->setErrorByName('email', $this->t('Please enter a valid email address.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $feedback_text = trim($form_state->getValue('feedback'));
    $name = $form_state->getValue('name');
    $email = $form_state->getValue('email');
    $uid = $this->currentUser->id();

    $messages = [
          [
            "role" => "system",
            "content" => "Do not return any other output other than Positive, Negative or Neutral",
          ],
            [
              "role" => "user",
              "content" => $feedback_text,
            ],
    ];

    try {
      // Performing the sentiment analysis.
      $result = $this->openAiClient->analyzeSentiment($messages);
      $sentiment = $result['data'];
      $statusCode = $result['status_code'];

      if ($statusCode === 200) {
        // Check for a successful API response.
        if (isset($sentiment['choices'][0]['message']['content'])) {
          // Access the nested message content from the assistant.
          $assistantMessage = $sentiment['choices'][0]['message']['content'];

          // Insert feedback and sentiment result into the database.
          $this->connection->insert('feedback_ai')
            ->fields([
              'uid' => $uid,
              'sentiment_text' => $feedback_text,
              'sentiment_result' => $assistantMessage,
              'created' => time(),
              'name' => $name,
              'email' => $email,
            ])
            ->execute();

          // Display the Thank you message.
          $this->messenger->addMessage($this->t('Thank you for your feedback!'));
        }
        else {
          // Display an error message if the sentiment analysis failed.
          $this->loggerFactory->get('feedback_ai_invalid_response')->error('Invalid API response: @response', ['@response' => json_encode($sentiment)]);
          $this->messenger->addError($this->t('Invalid response from sentiment analysis.'));
        }
      }
      else {
        // Handle non-200 status codes.
        $errMessage = $sentiment['error']['message'] ?? 'Unknown error';
        $errorMessage = ($statusCode === 429) ? "You exceeded your current quota, please check your plan and billing details" : $errMessage;
        $this->messenger->addError($this->t('<h4 class="ai-errormsg">Error:</h4> <br> @message', ['@message' => $errorMessage]));
      }
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('feedback_ai_store_feedback')->error('Database insert failed: @error', ['@error' => $e->getMessage()]);
      $this->messenger->addError($this->t('Failed to store feedback, please try again.'));
    }

  }

}
