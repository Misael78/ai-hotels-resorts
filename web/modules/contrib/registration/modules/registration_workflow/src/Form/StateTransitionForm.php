<?php

namespace Drupal\registration_workflow\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\registration\Entity\RegistrationInterface;

/**
 * Defines a form that allows a registration to be transitioned to a new state.
 */
class StateTransitionForm extends ConfirmFormBase {

  /**
   * The registration.
   *
   * @var \Drupal\registration\Entity\RegistrationInterface
   */
  protected RegistrationInterface $registration;

  /**
   * The transition ID.
   *
   * @var string
   */
  protected string $transition;

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'registration_workflow_state_transition_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?RegistrationInterface $registration = NULL, $transition = NULL): array {
    $this->registration = $registration;
    $this->transition = $transition;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    $workflow = $this->registration->getWorkflow();
    $transition = $workflow->getTypePlugin()->getTransition($this->transition);
    return $this->t('Are you sure you want to @transition registration #@registration_id?', [
      '@transition' => $transition->label(),
      '@registration_id' => $this->registration->id(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): TranslatableMarkup {
    return $this->t('This action cannot be undone.');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return $this->registration->toUrl('canonical');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelText(): TranslatableMarkup {
    $workflow = $this->registration->getWorkflow();
    $transition = $workflow->getTypePlugin()->getTransition($this->transition);
    return $this->t('No, do not @transition the registration', [
      '@transition' => $transition->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Save the mew state.
    $workflow = $this->registration->getWorkflow();
    $new_state = $workflow->getTypePlugin()->getTransition($this->transition)->to();
    $this->registration->set('state', $new_state->id());
    $this->registration->save();

    // Display a confirmation message.
    switch ($this->transition) {
      case 'cancel':
        $this->messenger()->addMessage($this->t('The registration has been canceled.'));
        break;

      case 'complete':
        $this->messenger()->addMessage($this->t('The registration has been completed.'));
        break;

      case 'hold':
        $this->messenger()->addMessage($this->t('The registration has been put on hold.'));
        break;

      default:
        $this->messenger()->addMessage($this->t('The transition has been performed.'));
    }
  }

}
