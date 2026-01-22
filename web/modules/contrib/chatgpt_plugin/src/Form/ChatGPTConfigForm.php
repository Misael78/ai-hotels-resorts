<?php

namespace Drupal\chatgpt_plugin\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Function to create the ChatGPT Config Form.
 */
class ChatGPTConfigForm extends ConfigFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructor for the class.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
        $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'chatgpt_plugin.adminsettings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'chatgpt_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('chatgpt_plugin.adminsettings');
    $content_types = [];

    // Fetching all the node content types.
    $node_types = $this->entityTypeManager
      ->getStorage('node_type')
      ->loadMultiple();

    foreach ($node_types as $type) {
      $content_types[$type->id()] = $type->label();
    }

    $form['chatgpt_setting_compare_tool'] = [
      '#type' => 'markup',
      '#markup' => '<a id="chatgpt-tool" href="https://gpttools.com/comparisontool" target="_blank">Click here to access the ChatGPT API setting comparison tool</a>',
    ];

    $gpt_model_options = [
      'chatgpt' => 'GPT-3.5 or ChatGPT',
      'gpt4' => 'GPT-4',
      'gpt4-mini' => 'GPT-4o-mini',
    ];

    $form['gpt_model_version'] = [
      '#type' => 'select',
      '#title' => $this->t('Select GPT Model Version'),
      '#description' => $this->t('Select the version of the GPT model you want to leverage.'),
      '#options' => $gpt_model_options,
      '#default_value' => $config->get('gpt_model_version'),
      '#required' => TRUE,
    ];

    $form['completion_endpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('OpenAI API Endpoint'),
      '#description' => $this->t('Please provide the OpenAI Chat Completion API Endpoint here.'),
      '#default_value' => $config->get('completion_endpoint') ? $config->get('completion_endpoint') : 'https://api.openai.com/v1/chat/completions',
    ];

    $form['model_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('OpenAI Model Name'),
      '#description' => $this->t('Please provide the OpenAI GPT Model name here like gpt-4o-mini. List of all models can be found
                        <a href="https://platform.openai.com/docs/models" target="_blank"><b>Here</b></a>'),
      '#default_value' => $config->get('model_name'),
    ];

    $form['dalle_endpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('DALL.E API Endpoint'),
      '#description' => $this->t('Please provide the DAll.E API Endpoint here.'),
      '#default_value' => $config->get('dalle_endpoint') ? $config->get('dalle_endpoint') : 'https://api.openai.com/v1/images/generations',
      '#required' => TRUE,
    ];

    $form['moderation_endpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Content Moderation API Endpoint'),
      '#description' => $this->t('Please provide the Content Moderation API Endpoint here.'),
      '#default_value' => $config->get('moderation_endpoint') ? $config->get('moderation_endpoint') : 'https://api.openai.com/v1/moderations',
      '#required' => TRUE,
    ];

    $form['access_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('OpenAI API Access Token'),
      '#description' => $this->t('Please provide the OpenAI API Access Token here.'),
      '#default_value' => $config->get('access_token'),
      '#required' => TRUE,
    ];

    $form['chatgpt_max_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('OpenAI API Max Token'),
      '#description' => $this->t('Please provide the OpenAI API max token here to limit the output words. Max token is the
                        limit <br>of tokens combining both input prompt and output text. 1 token is approx 4 chars in English.
                        <br>You can use this <a href="https://platform.openai.com/tokenizer" target="_blank"><b>Tokenizer Tool</b></a> 
                        to count number of tokens for your text.'),
      '#default_value' => $config->get('chatgpt_max_token'),
      '#required' => TRUE,
    ];

    $form['chatgpt_temperature'] = [
      '#type' => 'textfield',
      '#title' => $this->t('OpenAI API Temperature'),
      '#description' => $this->t('Please provide the Temperature value here. Please set it between 0.5 to 0.9 for most creative output.'),
      '#default_value' => $config->get('chatgpt_temperature') ? $config->get('chatgpt_temperature') : '0',
    ];

    $form['content_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Choose Content types for AI assistance.'),
      '#description' => $this->t('Please choose content types to enable AI content assistance.'),
      '#options' => $content_types,
      '#default_value' => $config->get('content_types', []),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);

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
    parent::submitForm($form, $form_state);

    $this->config('chatgpt_plugin.adminsettings')
      ->set('gpt_model_version', $form_state->getValue('gpt_model_version'))
      ->set('completion_endpoint', $form_state->getValue('completion_endpoint'))
      ->set('model_name', $form_state->getValue('model_name'))
      ->set('dalle_endpoint', $form_state->getValue('dalle_endpoint'))
      ->set('moderation_endpoint', $form_state->getValue('moderation_endpoint'))
      ->set('access_token', $form_state->getValue('access_token'))
      ->set('chatgpt_temperature', $form_state->getValue('chatgpt_temperature'))
      ->set('chatgpt_max_token', $form_state->getValue('chatgpt_max_token'))
      ->set('content_types', $form_state->getValue('content_types'))
      ->save();
  }

}
