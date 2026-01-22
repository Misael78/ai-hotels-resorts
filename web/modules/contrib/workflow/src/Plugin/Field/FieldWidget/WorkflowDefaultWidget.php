<?php

namespace Drupal\workflow\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\workflow\Entity\WorkflowTransitionInterface;
use Drupal\workflow\Form\WorkflowTransitionForm;

/**
 * Plugin implementation of the 'workflow_default' widget.
 *
 * @FieldWidget(
 *   id = "workflow_default",
 *   label = @Translation("Workflow Transition form"),
 *   description = @Translation("A complex widget showing the Transition form."),
 *   field_types = {
 *     "workflow",
 *   },
 * )
 */
class WorkflowDefaultWidget extends WidgetBase {

  /**
   * Generates a widget.
   *
   * @param \Drupal\workflow\Entity\WorkflowTransitionInterface $transition
   *   A WorkflowTransition.
   *
   * @return \Drupal\workflow\Plugin\Field\FieldWidget\WorkflowDefaultWidget
   *   The WorkflowTransition widget.
   */
  public static function createInstance(WorkflowTransitionInterface $transition): WorkflowDefaultWidget {
    // Function called in: F___, F___ ______, WorkflowStateActionBase.
    $entity_type_manager = \Drupal::service('entity_type.manager');
    $entity = $transition->getTargetEntity();
    $entity_type_id = $entity->getEntityTypeId();
    $entity_bundle = $entity->bundle();
    $view_mode = 'default';
    $field_name = $transition->getFieldName();

    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $form_display */
    $entity_form_display = $entity_type_manager->getStorage('entity_form_display');
    $form_display = $entity_form_display->load("$entity_type_id.$entity_bundle.$view_mode");
    $widget = $form_display->getRenderer($field_name);

    return $widget;
    /*
    $element = [];
    if ($widget) {
    / * * @ v a r \Drupal\Core\Entity\FieldableEntityInterface $entity * /
    $items = $entity->get($field_name);
    $items->filterEmptyItems();
    $dummy_form = [];
    $form_state = new FormState();
    // @see $form['#parents'];
    $element[$field_name] = $widget->form($items, $dummy_form, $form_state);
    }

    return $element;
     */
  }

  /**
   * {@inheritdoc}
   */
  public function form(FieldItemListInterface $items, array &$form, FormStateInterface $form_state, $get_delta = NULL) {
    // Just for debugging and analysis.
    $element = parent::form($items, $form, $form_state, $get_delta);
    return $element;
  }

  /**
   * {@inheritdoc}
   *
   * Gets the TransitionWidget in a form (for e.g., Workflow History Tab).
   *
   * This is a minimized version of FormBuilder::retrieveForm().
   * As a drawback, the form_alter hooks must be implemented separately.
   *
   * Be careful: Widget may be shown in very different places. Test carefully!!
   *  - On a entity add/edit page;
   *  - On a entity preview page;
   *  - On a entity view page;
   *  - Obsolete: On a entity 'workflow history' tab;
   *  - On a comment display, in the comment history;
   *  - On a comment form, below the comment history.
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    // Function called in: F___, F___ ______, F________, Widget, Widget submit.
    // Note: no parent::call, since parent is an abstract method.
    /** @var \Drupal\workflow\Plugin\Field\WorkflowItemListInterface $items */
    if (!$workflow = $items?->getWorkflow()) {
      // @todo Add error message.
      return $element;
    }

    if ($this->isDefaultValueWidget($form_state)) {
      // On the Field settings page, User may not set a default value
      // (This is done by the Workflow module).
      return [];
    }

    // To prepare Transition widget, use the Form, to get attached fields.
    $transition = $items->getDefaultTransition();
    // Add result to $element, respecting existing formSingleElement attributes.
    // @todo Move this code into function form().
    // Mimic $entity_form_builder->getForm(), but only get the element.
    // Mimic $this->formBuilder->buildForm(), but only get the element.
    // -------------------------------------------------------------------- // .
    // Start duplicate code in Widget.
    // @todo Replace by $entity = $entity_form_builder->getForm()?
    // Create a new $form_state, to replace Node by WorkflowTransition.
    $form_object1 = clone $form_state->getFormObject();
    $form_object1->setEntity($transition);
    $new_form_state = clone $form_state;
    $new_form_state->setFormObject($form_object1);
    // The following line creates a $form_state with WT object.
    $form_state_additions = [];
    $form_object = WorkflowTransitionForm::createInstance($transition, $new_form_state, $form_state_additions);
    $form_id = $form_object->getFormId();

    $form_builder = \Drupal::getContainer()->get('form_builder');
    // The following line updates FormState with Storage and FormDisplay for WT.
    // The retrieveForm() calls builder->buildForm(), formObject->form().
    $element += $form_builder->retrieveForm($form_id, $new_form_state);
    // Function called in: F___, F___ ______, F________, Widget, Widget submit.
    if ($call_prepare_form = FALSE) {
      // Avoid adding clutter to $form - only call the hooks.
      // Enhance the form and call alter hooks.
      // Invoke hook_form_alter(), hook_form_BASE_FORM_ID_alter(), and
      // hook_form_FORM_ID_alter() implementations.
      $form_builder->prepareForm($form_id, $element, $new_form_state);
      // // Remove '#type' = 'form' wrappers.
      // $element = WorkflowTransitionElement::addWrapper($element);
    }
    else {
      // Remove unwanted elements from retrieveForm().
      unset($element['#process']);
      unset($element['footer']);
      unset($element['actions']);
      if ($call_hooks = FALSE) {
        // In widget, do not call form hooks.
        // Mimic \Drupal\Core\Form\FormBuilder::prepareForm(), call the alter hooks.
        // Invoke hook_form_alter(), hook_form_BASE_FORM_ID_alter(), and
        // hook_form_FORM_ID_alter() implementations.
        $hooks = ['form'];
        if ($base_form_id = $form_object->getBaseFormId()) {
          $hooks[] = "form_{$base_form_id}";
        }
        $form_id = $form_object->getFormId();
        $hooks[] = "form_{$form_id}";
        \Drupal::moduleHandler()->alter($hooks, $element, $new_form_state, $form_id);
        \Drupal::theme()->alter($hooks, $element, $new_form_state, $form_id);
      }
    }
    // End of (almost) duplicate code in Widget.
    // -------------------------------------------------------------------- // .

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state) {
    // Function called in: F___, F___ ______, F________, W_____, Widget submit.
    /** @var \Drupal\workflow\Plugin\Field\WorkflowItemListInterface $items */
    /** @var \Drupal\workflow\Entity\WorkflowTransitionInterface $transition */
    $transition = $items->getTransition();
    // We need to create new form_state, since original gives date error.
    $form_state_additions = [
      'buttons' => $form_state->getButtons(),
      'input' => $form_state->getUserInput(),
      'values' => $form_state->getValues(),
      'triggering_element' => $form_state->getTriggeringElement(),
    ];

    // We now promote the Workflow Widget to a complete form,
    // and do extractFormValues() on that form,
    // using the given $form_state->values().
    // For that, create new $form_state and call wrapper function buildEntity().
    // -------------------------------------------------------------------- // .
    // Start duplicate code in Widget.
    // @todo Replace by $form = $entity_form_builder->getForm()?
    // Create a new $form_state, to replace Node by WorkflowTransition.
    $new_form_state = NULL;
    // The following line creates a $form_state with WT object.
    $form_object = WorkflowTransitionForm::createInstance($transition, $new_form_state, $form_state_additions);
    $field_name = $transition->getFieldName();
    $element = $form[$field_name]['widget'][0];
    // End of (almost) duplicate code in Widget.
    // -------------------------------------------------------------------- // .

    // Now, let core do its job and get the new transition.
    /** @var \Drupal\workflow\Entity\WorkflowTransitionInterface $transition */
    $transition = $form_object->buildEntity($element, $form_state);
    // Refresh the target entity, since multiple versions are lingering around.
    // This is at least necessary for 'entity_create' form.
    $transition->setTargetEntity($items->getEntity());

    $transition->setEntityWorkflowField();
  }

}
