<?php

namespace Drupal\commerce_registration\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\registration\Entity\RegistrationInterface;
use Drupal\registration\Form\RegisterForm as BaseRegisterForm;

/**
 * Extends the Register form.
 *
 * Adds support for the form to appear on a product page with multiple
 * product variations when using the Registration Form display
 * formatter. The form will appear multiple times, once per variation.
 *
 * This support is not needed when the product variations are displayed
 * as an Add to Cart form, which is the more typical use case. However, some
 * installations may not use the Add to Cart form, so this support is added
 * for those unusual use cases.
 *
 * The registration field should be hidden on the product variation display
 * that is used for the Add to Cart form. In that case the inline registration
 * form is used instead of this one.
 *
 * @see \Drupal\commerce_registration\Plugin\Commerce\InlineForm\Registration
 */
class RegisterForm extends BaseRegisterForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    // Return a unique form ID per instance.
    return parent::getFormId() . '-' . $this->getEntity()->getHostEntityId();
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);

    // Alter the form unless a notice was given.
    if (empty($form['notice'])) {
      self::alterRegisterForm($form, $form_state);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Copy the "Who is registering" field value in the form state to match
    // the standard name.
    $host_entity = $form_state->get('host_entity');
    $name = 'who_is_registering_' . $host_entity->id();
    $value = $form_state->getValue($name);
    $form_state->setValue('who_is_registering', $value);

    // Perform the validation.
    return parent::validateForm($form, $form_state);
  }

  /**
   * Alter the register form.
   */
  public static function alterRegisterForm(array &$form, FormStateInterface $form_state) {
    $registration_manager = \Drupal::service('registration.manager');
    $current_user = \Drupal::currentUser();

    $registration = $form_state->get('registration');
    $host_entity = $form_state->get('host_entity');
    $settings = $host_entity->getSettings();

    // Add the "Who is registering" field.
    $registrant_options = $registration_manager->getRegistrantOptions($registration, $settings);

    $default = NULL;
    if (!$registration->isNew()) {
      $default = $registration->getRegistrantType($current_user);
    }
    elseif (count($registrant_options) == 1) {
      $keys = array_keys($registrant_options);
      $default = reset($keys);
    }

    // Use a unique input name so visibility works.
    unset($form['who_is_registering']);
    $name = 'who_is_registering_' . $host_entity->id();
    $form[$name] = [
      '#name' => $name,
      '#type' => 'select',
      '#title' => t('This registration is for:'),
      '#options' => $registrant_options,
      '#default_value' => $default,
      '#required' => TRUE,
      '#access' => (count($registrant_options) > 1),
      '#weight' => -1,
    ];

    // Determine the field name for visibility.
    if (!empty($form['#parents'])) {
      $parent_name = '';
      foreach ($form['#parents'] as $index => $parent) {
        $append = $index ? "[$parent]" : $parent;
        $parent_name = $parent_name . $append;
      }
      $name = $parent_name . "[$name]";
    }

    // The following checks for empty form fields, since the site admin
    // may have hidden certain fields on the form via the form display.
    // Set the User field visibility and required states.
    if (!empty($form['user_uid'])) {
      $form['user_uid']['#access'] = isset($registrant_options[RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_USER]);
      $form['user_uid']['#states'] = [
        'visible' => [
          ":input[name='$name']" => ['value' => RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_USER],
        ],
      ];
      // @see https://www.drupal.org/project/drupal/issues/2855139
      $form['user_uid']['widget'][0]['target_id']['#states'] = [
        'required' => [
          ":input[name='$name']" => ['value' => RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_USER],
        ],
      ];
    }

    // Set the Email field visibility and required states.
    if (!empty($form['anon_mail'])) {
      $anonymous_allowed = isset($registrant_options[RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_ANON]);
      $form['anon_mail']['#access'] = $anonymous_allowed;
      $form['anon_mail']['#states'] = [
        'visible' => [
          ":input[name='$name']" => ['value' => RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_ANON],
        ],
      ];
      if ((count($registrant_options) == 1) && $anonymous_allowed) {
        $form['anon_mail']['widget'][0]['value']['#required'] = TRUE;
      }
      else {
        // @see https://www.drupal.org/project/drupal/issues/2855139
        $form['anon_mail']['widget'][0]['value']['#states'] = [
          'required' => [
            ":input[name='$name']" => ['value' => RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_ANON],
          ],
        ];
      }
    }
  }

}
