<?php

namespace Drupal\elevenlabs\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure ElevenLabs Field settings for this site.
 */
class ElevenLabsSettingsForm extends ConfigFormBase {

  /**
   * The configuration name.
   */
  const CONFIG_NAME = 'elevenlabs.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'elevenlabs_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [static::CONFIG_NAME];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::CONFIG_NAME);

    $form['api_key'] = [
      '#type' => 'key_select',
      '#title' => $this->t('ElevenLabs API Key'),
      '#default_value' => $config->get('api_key'),
      '#required' => TRUE,
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config(static::CONFIG_NAME)
      ->set('api_key', $form_state->getValue('api_key'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
