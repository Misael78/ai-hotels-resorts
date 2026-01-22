<?php

namespace Drupal\chatgpt_plugin\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use GuzzleHttp\Exception\GuzzleException;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\ClientInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\chatgpt_plugin\GPTApiService;

/**
 * Defines a ChatGPT Translate Controller.
 */
class ChatGPTTranslateController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * A guzzle http client instance.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpclient;

  /**
   * The currently active request object.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $request;

  /**
   * The custom GPT API service.
   *
   * @var \Drupal\chatgpt_plugin\GPTApiService
   */
  protected $gptApi;

  /**
   * Creates an ContentTranslationPreviewController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   A guzzle http client instance.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The current request object.
   * @param \Drupal\chatgpt_plugin\GPTApiService $gpt_api
   *   Our custom GPT API service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager,
  EntityFieldManagerInterface $entity_field_manager,
  ClientInterface $http_client,
  RequestStack $requestStack,
  GPTApiService $gpt_api
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->httpclient = $http_client;
    $this->request = $requestStack;
    $this->gptApi = $gpt_api;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('http_client'),
      $container->get('request_stack'),
      $container->get('chatgpt_plugin.gpt_api'),
    );
  }

  /**
   * Add requested node translation to the original content.
   *
   * @param string $lang_code
   *   Target language code.
   * @param string $lang_name
   *   Target language name.
   * @param int $node_id
   *   ID of the entity or node.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   the function will return a RedirectResponse to the translate
   *   overview page by showing a success or error message.
   */
  public function translate($lang_code, $lang_name, $node_id) {
    $node = $this->entityTypeManager->getStorage('node')->load($node_id);
    $entity_type_id = 'node';
    $bundle = $node->bundle();
    $bundleFields['title']['label'] = 'Title';

    $allowed_values = [
      'text',
      'text_with_summary',
      'text_long',
      'string',
      'string_long',
    ];
    foreach ($this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle) as $field_name => $field_definition) {
      if (!empty($field_definition->getTargetBundle()) && in_array($field_definition->getType(), $allowed_values)) {
        $bundleFields[$field_name]['label'] = $field_definition->getLabel();
      }
    }

    foreach ($bundleFields as $field_name => $label) {
      $field = $node->get($field_name);
      $field_value = $field->value;
      if ($field_value) {
        $translated_text = $this->chatgptTranslateContent($field_value, $lang_name);
        $bundleFields[$field_name]['translation'] = $translated_text;
      }
    }
    $insertTranslation = $this->insertTranslation($node, $lang_code, $bundleFields);

    $refererUrl = $this->request->getCurrentRequest()->server->get('HTTP_REFERER');
    $response = new RedirectResponse($refererUrl);
    $response->send();
    $messenger = $this->messenger();

    if ($insertTranslation) {
      $messenger->addStatus($this->t('Content translated successfully.'));
    }
    else {
      $messenger->addError($this->t('There was some issue with content translation.'));
    }
    return $response;
  }

  /**
   * Get the trnslated content by making API call to GPT API.
   *
   * @param string $input_text
   *   Input prompt for the GPT API.
   * @param string $lang_name
   *   The target language name.
   *
   * @return string
   *   The text translation received from GPT API.
   */
  public function chatgptTranslateContent($input_text, $lang_name) {
    $prompt_text = "Translate this into " . $lang_name . " - " . $input_text;

    // Calling our custom GPT API service..
    try {
      $response = $this->gptApi->getGptResponse($prompt_text);
    }
    catch (GuzzleException $exception) {
      // Error handling for ChatGPT API call.
      $error_msg = $exception->getMessage();
      return $error_msg;
    }

    return $response;
  }

  /**
   * Adding the translation in database and linking it to the original node.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $node
   *   The entity object.
   * @param string $target_language
   *   The target language.
   * @param array $bundleFields
   *   An array of field name and their translation.
   */
  public function insertTranslation(ContentEntityInterface $node, $target_language, array $bundleFields) {
    if (!$node->hasTranslation($target_language)) {
      $node_translation = $node->addTranslation($target_language);
      $status = TRUE;

      foreach ($bundleFields as $field_name => $val) {
        if ($field_name != 'moderation_state') {
          $node_translation->$field_name->value = $val['translation'] ?? NULL;
          $node_translation->$field_name->format = 'full_html';
        }
      }

      try {
        $node_translation->save();
      }
      catch (EntityStorageException $e) {
        $status = FALSE;
      }
      return $status;
    }
  }

}
