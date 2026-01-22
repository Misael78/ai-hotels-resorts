<?php

namespace Drupal\commerce_registration\Plugin\Commerce\InlineForm;

use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\commerce\Plugin\Commerce\InlineForm\EntityInlineFormBase;
use Drupal\registration\Entity\RegistrationInterface;
use Drupal\registration\Form\RegisterForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an inline form for editing a registration.
 *
 * @CommerceInlineForm(
 *   id = "registration",
 *   label = @Translation("Registration"),
 * )
 */
class Registration extends EntityInlineFormBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected AccountProxy $currentUser;

  /**
   * The registration.
   *
   * @var \Drupal\registration\Entity\RegistrationInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): Registration {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->currentUser = $container->get('current_user');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      // The order ID that will be assigned to the saved registration.
      'order_id' => 0,
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function requiredConfiguration() {
    return ['order_id'];
  }

  /**
   * {@inheritdoc}
   */
  protected function validateConfiguration() {
    parent::validateConfiguration();

    if (empty($this->configuration['order_id'])) {
      throw new \RuntimeException('The order ID must be nonzero.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildInlineForm(array $inline_form, FormStateInterface $form_state) {
    $inline_form = parent::buildInlineForm($inline_form, $form_state);

    assert($this->entity instanceof RegistrationInterface);
    $form_display = $this->loadFormDisplay();
    $form_display->buildForm($this->entity, $inline_form, $form_state);
    $form_state->set('registration', $this->entity);
    $form_state->set('host_entity', $this->entity->getHostEntity());
    RegisterForm::alterRegisterForm($inline_form, $form_state);

    // Only allow one space per registration in the inline form. Otherwise
    // the aggregate number of spaces can exceed the event capacity.
    if (!empty($inline_form['count'])) {
      $inline_form['count']['#access'] = FALSE;
    }
    // Never show the Created timestamp field in an inline registration form.
    if (!empty($inline_form['created'])) {
      $inline_form['created']['#access'] = FALSE;
    }
    return $inline_form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateInlineForm(array &$inline_form, FormStateInterface $form_state) {
    // Cleanup the form state if the person registering has access to
    // different types of registrations and had toggled both into the
    // form at different points in time before submitting.
    $who = array_merge($inline_form['#parents'], ['who_is_registering']);
    $anon = array_merge($inline_form['#parents'], ['anon_mail']);
    $user = array_merge($inline_form['#parents'], ['user_uid']);
    switch ($form_state->getValue($who)) {
      case RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_ANON:
        // Anonymous email. Clear the user if present.
        if ($form_state->hasValue($user)) {
          $form_state->unsetValue($user);
          $this->entity->set('user_uid', NULL);
        }
        break;

      case RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_ME:
        // Self registration. Clear both anonymous email and user account.
        if ($form_state->hasValue($anon)) {
          $form_state->unsetValue($anon);
          $this->entity->set('anon_mail', NULL);
        }
        if ($form_state->hasValue($user)) {
          $form_state->unsetValue($user);
        }
        // Validate the current user.
        $this->entity->set('user_uid', $this->currentUser->id());
        break;

      case RegistrationInterface:: REGISTRATION_REGISTRANT_TYPE_USER:
        // User account. Clear the anonymous email if present.
        if ($form_state->hasValue($anon)) {
          $form_state->unsetValue($anon);
          $this->entity->set('anon_mail', NULL);
        }
        break;
    }

    // The form display validation includes entity validation, which handles
    // most of the validation checks through a constraint.
    // @see \Drupal\registration\Plugin\Validation\Constraint\RegistrationConstraintValidator
    parent::validateInlineForm($inline_form, $form_state);

    $form_display = $this->loadFormDisplay();
    $form_display->extractFormValues($this->entity, $inline_form, $form_state);
    $form_display->validateFormValues($this->entity, $inline_form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitInlineForm(array &$inline_form, FormStateInterface $form_state) {
    parent::submitInlineForm($inline_form, $form_state);

    $form_display = $this->loadFormDisplay();
    $form_display->extractFormValues($this->entity, $inline_form, $form_state);

    // Set the user when self-registering.
    $parents = array_merge($inline_form['#parents'], ['who_is_registering']);
    if ($form_state->getValue($parents) == RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_ME) {
      $this->entity->set('user_uid', $this->currentUser->id());
    }

    // Ensure either user or anonymous email is set, but not both.
    if ($form_state->getValue($parents) == RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_ANON) {
      $this->entity->set('user_uid', NULL);
    }
    else {
      $this->entity->set('anon_mail', NULL);
    }

    // Set the order ID and save.
    $this->entity->set('order_id', $this->configuration['order_id']);
    $this->entity->save();
  }

  /**
   * Loads the form display used to build the registration form.
   *
   * @return \Drupal\Core\Entity\Display\EntityFormDisplayInterface
   *   The form display.
   */
  protected function loadFormDisplay(): EntityFormDisplayInterface {
    return EntityFormDisplay::collectRenderDisplay($this->entity, 'checkout');
  }

}
