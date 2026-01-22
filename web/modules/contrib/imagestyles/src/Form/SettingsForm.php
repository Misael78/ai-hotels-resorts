<?php

namespace Drupal\imagestyles\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\image\Entity\ImageStyle;

/**
 * Class SettingsForm.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'imagestyles.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'imagestyles_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $styles = ImageStyle::loadMultiple();
    $options = [];
    foreach ($styles as $style_name => $style) {
      $options[$style_name] = $style->label();
    }
    $options['original'] = $this->t('Original');

    $config = $this->config('imagestyles.settings');
    $defaults = $config->get('expanded_styles') ?? [];

    $form['expanded_styles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Image styles to show as expanded by default on the media entity page'),
      '#options' => $options,
      '#default_value' => $defaults,
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('imagestyles.settings')
      ->set('expanded_styles', $form_state->getValue('expanded_styles'))
      ->save();
  }

}
