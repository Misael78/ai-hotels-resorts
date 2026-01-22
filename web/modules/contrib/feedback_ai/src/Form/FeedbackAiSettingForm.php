<?php

namespace Drupal\feedback_ai\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure OpenAI client settings for this site.
 */
class FeedbackAiSettingForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'feedback_openai_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['feedback_openai.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['feedbackai_secret_key'] = [
      '#required' => TRUE,
      '#type' => 'textfield',
      '#title' => $this->t('Secret Key'),
      '#default_value' => $this->config('feedback_openai.settings')->get('feedbackai_secret_key'),
      '#description' => $this->t('The API key is required to interface with OpenAI services. Get your API key by signing up on the <a href=":link" target="_blank">OpenAI website</a>.', [':link' => 'https://openai.com/api']),
    ];

    $form['feedbackai_endpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Endpoint'),
      '#description' => $this->t('Please provide the OpenAI API Endpoint here.'),
      '#default_value' => $this->config('feedback_openai.settings')->get('feedbackai_endpoint') ?: 'https://api.openai.com/v1/chat/completions',
    ];

    $form['feedbackai_api_model'] = [
      '#type' => 'select',
      '#title' => $this->t('Model Name'),
      '#options' => [
        'gpt-3.5-turbo' => $this->t('gpt-3.5-turbo'),
        'gpt-4' => $this->t('gpt-4'),
        'gpt-4-turbo' => $this->t('gpt-4-turbo'),
        'gpt-4o' => $this->t('gpt-4o'),
      ],
      '#description' => $this->t('Please provide the OpenAI API Model name here.'),
      '#default_value' => $this->config('feedback_openai.settings')->get('feedbackai_api_model') ?: 'gpt-3.5-turbo',
    ];

    $form['feedbackai_api_max_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Maximum Limit'),
      '#description' => $this->t('Please provide the Max token here to limit the output words. Max token is the limit of tokens combining both input prompt and output text.'),
      '#default_value' => $this->config('feedback_openai.settings')->get('feedbackai_api_max_token') ?: 256,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $feedbackai_secret_key = $form_state->getValue('feedbackai_secret_key');
    $feedbackai_api_max_token = $form_state->getValue('feedbackai_api_max_token');
    $feedbackai_endpoint = $form_state->getValue('feedbackai_endpoint');

    // Check if the endpoint is a valid URL.
    if (!empty($feedbackai_endpoint) &&!preg_match('/^https:\/\/api\.openai\.com\/v1\/chat\/completions$/', $feedbackai_endpoint)) {
      $form_state->setErrorByName('feedbackai_endpoint', $this->t('Please enter a valid OpenAI API Endpoint.'));
    }

    if (!empty($feedbackai_secret_key) && !preg_match('/^[A-Za-z0-9-_]+$/', $feedbackai_secret_key)) {
      $form_state->setErrorByName('secret_key', $this->t('Secret Key contains invalid characters. Only alphanumeric characters, hyphens, and underscores are allowed.'));
    }

    if (!empty($feedbackai_api_max_token) && !is_numeric($feedbackai_api_max_token)) {
      $form_state->setErrorByName('feedbackai_api_max_token', $this->t('Feedback OpenAI API Max Token must be a number.'));
    }

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('feedback_openai.settings')
      ->set('feedbackai_secret_key', $form_state->getValue('feedbackai_secret_key'))
      ->set('feedbackai_api_model', $form_state->getValue('feedbackai_api_model'))
      ->set('feedbackai_api_max_token', $form_state->getValue('feedbackai_api_max_token'))
      ->set('feedbackai_endpoint', $form_state->getValue('feedbackai_endpoint'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
