<?php

namespace Drupal\hotel_ai\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class HotelAISettingsForm extends ConfigFormBase {

  protected function getEditableConfigNames() {
    return ['hotel_ai.settings'];
  }

  public function getFormId() {
    return 'hotel_ai_settings_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['info'] = [
      '#type' => 'markup',
      '#markup' => '<p>Hotel AI automation settings page.</p>',
    ];

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
  }
}
