<?php

namespace Drupal\workflow\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\workflow\Element\WorkflowTransitionButtons;
use Drupal\workflow\Element\WorkflowTransitionElement;
use Drupal\workflow\Entity\WorkflowManager;
use Drupal\workflow\Entity\WorkflowTransitionInterface;

/**
 * Provides a Transition Form to be used in the Workflow Widget.
 */
class WorkflowTransitionForm extends ContentEntityForm {

  /*************************************************************************
   * Implementation of interface FormInterface.
   */

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    // We need a proprietary Form ID, to identify the unique forms
    // when multiple fields or entities are shown on 1 page.
    // Test this f.i. by checking the 'scheduled' box. It will not unfold.
    // $form_id = parent::getFormId();

    /** @var \Drupal\workflow\Entity\WorkflowTransitionInterface $transition */
    $transition = $this->getEntity();
    $field_name = $transition->getFieldName();

    // Entity may be empty on VBO bulk form.
    // $entity = $transition->getTargetEntity();
    // Compose Form ID from string + Entity ID + Field name.
    // Field ID contains entity_type, bundle, field_name.
    // The Form ID is unique, to allow for multiple forms per page.
    // $workflow_type_id = $transition->getWorkflowId();
    // Field name contains implicit entity_type & bundle (since 1 field per entity)
    // $entity_type = $transition->getTargetEntityTypeId();
    // $entity_id = $transition->getTargetEntityId();
    //
    // Emulate nodeForm convention.
    $suffix = $transition->id() ? 'edit_form' : 'form';
    $form_id = implode('_', [
      'workflow_transition',
      $transition->getTargetEntityTypeId(),
      $transition->getTargetEntityId() ?? 'new',
      $field_name,
      $suffix,
    ]);
    // $form_id = Html::getUniqueId($form_id);
    return $form_id;
  }

  /**
   * Gets the Transition Form Element (for e.g., Workflow History Tab)
   *
   * @param \Drupal\workflow\Entity\WorkflowTransitionInterface $transition
   *   The current transition.
   *
   * @return array
   *   The form render element.
   *
   * @usage Use WorkflowTransitionForm::getForm() in WT forms, and
   *   WorkflowDefaultWidget::form() in entity field widgets.
   */
  public static function getForm(WorkflowTransitionInterface $transition) {
    // Function called in: Form, Form submit, Formatter, Widget, W_____ s_____.
    // Build the form via the entityBuilder, not directly via formObject.
    // This will add alter hooks etc.
    /** @var \Drupal\Core\Entity\EntityFormBuilder $entity_form_builder */
    $entity_form_builder = \Drupal::getContainer()->get('entity.form_builder');
    $form_state_additions = [];
    $form = $entity_form_builder->getForm($transition, 'add', $form_state_additions);
    return $form;
  }

  /**
   * Gets the Form object, so it can be used by WorkflowWidget.
   *
   * @param \Drupal\workflow\Entity\WorkflowTransitionInterface $transition
   *   The entity at hand.
   * @param \Drupal\Core\Form\FormStateInterface|null $form_state
   *   The form state. Will be changed/created by reference(!).
   * @param array $form_state_additions
   *   Some additions.
   *
   * @return \Drupal\workflow\Form\WorkflowTransitionForm
   *   The ContentEntityForm object for WorkflowTransition.
   */
  public static function createInstance(WorkflowTransitionInterface $transition, ?FormStateInterface &$form_state, $form_state_additions = []): WorkflowTransitionForm {
    // Function called in: F___, F___ ______, F________, Widget, Widget submit.
    // Completely override EntityFormBuilder::getForm, since we need the $form_state.
    // EntityFormBuilder::entityTypeManager is protected, so create explicitly.
    $entity_type_manager = \Drupal::service('entity_type.manager');
    $operation = 'add';
    /** @var \Drupal\workflow\Form\WorkflowTransitionForm $form_object */
    // $form_state->getFormObject() returns NodeForm, CommentForm: wrong.
    $form_object = $entity_type_manager->getFormObject($transition->getEntityTypeId(), $operation);
    $form_object->setEntity($transition);

    if (!$form_state) {
      $form_state = (new FormState)->setFormState($form_state_additions);
      $form_state->setFormObject($form_object);
    }
    else {
      $form_state->setFormObject($form_object);
    }

    return $form_object;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormDisplay(FormStateInterface $form_state) {
    // Function called in: Form, Form submit, Formatter, Widget, Widget submit.
    $form_display = $form_state->get('form_display');

    $entity_type_id = $form_display->getTargetEntityTypeId();
    if ($entity_type_id !== 'workflow_transition') {
      $entity_type_manager = \Drupal::service('entity_type.manager');
      $entity = $this->getEntity();
      $entity_type_id = $entity->getEntityTypeId();
      $entity_bundle = $entity->bundle();
      $view_mode = 'default';

      $entity_form_display = $entity_type_manager->getStorage('entity_form_display');
      $form_display = $entity_form_display->load("$entity_type_id.$entity_bundle.$view_mode");

      // $this->setFormDisplay($form_display, $form_state);
      return $form_display;
    }

    return $form_state->get('form_display');
  }

  /* *************************************************************************
   *
   * Implementation of interface EntityFormInterface (extends FormInterface).
   *
   */

  /**
   * Implements ContentEntityForm::form() and is called by buildForm().
   *
   * Caveat: !! It is not declared in the EntityFormInterface !!
   *
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    // Function called in: Form, Form submit, F________, Widget, Widget submit.
    /** @var \Drupal\workflow\Entity\WorkflowTransitionInterface $transition */
    $transition = $this->getEntity();
    $form['#default_value'] = $transition;
    // Add parents for attached field widgets, like FileWidget.
    $form['#parents'] = [$transition->getFieldName()];
    // This might cause baseFieldDefinitions to appear twice.
    $form = parent::form($form, $form_state);
    // Overwrite the BaseFields with the custom values.
    $form = WorkflowTransitionElement::alter($form, $form_state, $form);
    // Remove unwanted title. It overwrites the page title (in Claro theme).
    unset($form['#title']);

    return $form;
  }

  /**
   * Implements ContentEntityForm::actions() and is called by buildForm().
   *
   * Returns an array of supported actions for the current entity form.
   * Caveat: !! It is not declared in the EntityFormInterface !!
   *
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    // Action buttons are added in common workflow_form_alter(),
    // addActionButtons(), since it will be done in many form_id's.
    // Keep aligned: workflow_form_alter(), WorkflowTransitionForm::actions().
    if (!empty($actions['submit']['#value'])) {
      $actions['submit']['#value'] = $this->t('Update workflow');
    }
    return $actions;
  }

  /**
   * Implements ContentEntityForm::buildEntity().
   *
   * {@inheritdoc}
   */
  public function buildEntity(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Entity\FieldableEntityInterface $entity */
    $transition = parent::buildEntity($form, $form_state);

    // Mark the entity as NOT requiring validation.
    // (Copied from parent::buildEntity(), and used in validateForm().)
    $transition->setValidationRequired(FALSE);

    return $transition;
  }

  /**
   * Implements ContentEntityForm::copyFormValuesToEntity().
   *
   * This is called from:
   * - WorkflowTransitionForm::copyFormValuesToEntity(),
   * - WorkflowDefaultWidget.
   *
   * {@inheritdoc}
   */
  public function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    // Update the Transition $entity.
    parent::copyFormValuesToEntity($entity, $form, $form_state);

    /*
    // The following call to parent should set all data correct to $transition.
    // 20250815 test results:
    // field\case | node widget | WTH create | WTH edit | WTH revert | Action widget | ActionButtons | Comment |
    // field_name | n/a (4)     | ok idem    | ok idem  |  n/a       |               |               |         |
    // from_sid   |             | ok idem    | ok idem  |  n/a       |               |               |         |
    // to_sid     |             | ok update  | ok idem  |  n/a       |               |               |         |
    // timestamp  |             | OK(20250929) | NOK (1) | n/a       |               |               |         |
    // scheduled.tStamp |       | nok (3)      | NOK     |  n/a      |               |               |         |
    // comment    |             | ok         | ok       |  n/a       |               |               |         |
    // forced     |             | ok 'no'    | ok 'no'  |  n/a       |               |               |         |
    // executed   |             | ok 'yes'   | ok 'yes' |  n/a       |               |               |         |
    // extra field|             | ok (2)     | ok (2)   |  n/a       |               |               |         |
    // @todo 20250815 Known issues for WTF:copyFormValuesToEntity():
    // (1): Executed/Scheduled timestamp set to current time (on History page).
    // (2): Extra field on WT~edit form are editable in UI, so 'updated' is ok.
    // -    Extra field for scheduled WT is not supported yet.
    // (3): $is_scheduled wrong, due to (1).
    // (4): Field widget calls copyFormValuesToTransition() directly.
     */

    /** @var \Drupal\workflow\Entity\WorkflowTransitionInterface $transition */
    $transition = $entity;

    // Update the transition.
    // Use own version of copyFormValuesToEntity() to fix missing fields.
    // Note: Pay attention use case where WT changes to WST and v.v.
    // @todo This is not needed (anymore) on WFH, only for action buttons.
    if ($debug = FALSE) {
      // For debugging/testing, toggle above value,
      // so you can compare the values from transition vs. widget.
      // The transition may already be OK by core's copyFormValuesToEntity().
      $uid = $transition->getOwnerId();
      $from_sid = $transition->getFromSid();
      $to_sid = $transition->getToSid();
      $scheduled = $transition->isScheduled();
      $timestamp = $transition->getTimestamp();
      $timestamp_formatted = $transition->getTimestampFormatted();
      $comment = $transition->getComment();
      $force = $transition->isForced();
      $executed = $transition->isExecuted();
    }

    if ($transition->isExecuted()) {
      // For executed transitions,
      // only comments and attached fields are updated.
      // That happens also without this function, perhaps with above hook.
      return;
    }

    // @todo Somewhere, $form['#parents'] should (not) be set.
    $field_name = $transition->getFieldName();
    $values = $form_state->getValues()[$field_name]
      ?? $form_state->getValues();
    // On Node form, restore fact that uid is overwritten by Node owner.
    $transition->setOwner(workflow_current_user());
    $uid = $transition->getOwnerId();

    $scheduled = $transition->isScheduled();
    $scheduled = $values['scheduled']['value']
      ?? $values['scheduled'][0]['value']
      ?? FALSE;
    // When a future timestamp is set, but schedule toggle is disabled.
    $timestamp = $scheduled
      // Timestamp also determines $transition::is_scheduled().
      ? $transition->getTimestamp()
      : $transition->getDefaultRequestTime();
    $transition->setTimestamp($timestamp);
    $timestamp = $transition->getTimestamp();
    $timestamp_formatted = $transition->getTimestampFormatted();

    // Get new SID, taking into account action buttons vs. options.
    // Behavior is different between History view and Node edit widget,
    // since buttons are lost from widget's $new_form_state.
    $action_values = WorkflowTransitionButtons::getTriggeringButton($transition, $form_state, $values);
    $transition->setValues($action_values['to_sid']);
    $to_sid = $transition->getToSid();

    // The following lines seem obsolete in Edit, History, Change, Comment.
    // Note: when editing existing Transition, user may still change comments.
    // Note: subfields might be disabled, and not exist in formState.
    // Note: subfields are already set by core.
    // This is only needed on Node edit widget, not on Node view/History page.
    // $comment = $values['comment'][0]['value'] ?? '';
    // $transition->setComment($comment);
    // // Attribute 'force' is not set if '#access' is false.
    // $force = (bool) ($values['force']['value'] ?? FALSE);
    // $transition->force($force);

    // Add attached fields.
    // Oct-2025 v2.1.8: Leave active, since needed for file upload hook
    // via hook copy_form_values_to_transition_field_alter.
    $transition->copyAttachedFields($form, $form_state);

    // Update targetEntity's itemList (aka input $items) with the transition.
    // This is also needed for parent::extractFormValues().
    // Note: This is a wrapper around $items->setValue($values);
    $transition->setEntityWorkflowField();

    if (WorkflowManager::isTargetCommentEntity($transition)) {
      // Remove values, since
      // CommentWithWorkflow's 'field_name' overwrites Comment's 'field_name'.
      // This is needed because of the '#tree' problem in
      // DefaultWidget::extractFormValues().
      // Remove 'field_name', since it ruins Comment's 'field_name'.
      $form_state->unsetValue(['field_name']);
      // For some reason, 'comment' must be preserved, or in above code,
      // for Workflow History page, comment is empty. Other fields are OK.
      // This code is not used in Node edit form.
      // $form_state->unsetValue(['comment']); .
      // The following fields are nicely handled by their widgets.
      // $form_state->unsetValue(['to_sid']);
      // $form_state->unsetValue(['scheduled']);
      // $form_state->unsetValue(['timestamp']);
      // $form_state->unsetValue(['force']);
      // $form_state->unsetValue(['executed']);
      // .
      $attached_field_definitions = $transition->getAttachedFieldDefinitions();
      foreach ($attached_field_definitions as $field_name => $field) {
        // Remove value, (E.g., 'field_name' ruins Comment's 'field_name').
        $form_state->unsetValue([$field_name]);
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * Execute transition and update the target entity.
   */
  public function save(array $form, FormStateInterface $form_state) {
    // Function called in: F___, Form submit, F________, W_____, W_____ ______.
    /** @var \Drupal\workflow\Entity\WorkflowTransitionInterface $transition */
    $transition = $this->getEntity();
    return $transition->executeAndUpdateEntity();
  }

}
