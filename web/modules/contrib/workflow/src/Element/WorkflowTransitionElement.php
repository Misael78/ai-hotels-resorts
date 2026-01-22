<?php

namespace Drupal\workflow\Element;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\workflow\Controller\WorkflowTransitionFormController;
use Drupal\workflow\Entity\WorkflowState;
use Drupal\workflow\Entity\WorkflowTransitionInterface;

/**
 * Provides a form element for the WorkflowTransitionForm and ~Widget.
 *
 * Properties:
 * - #return_value: The value to return when the checkbox is checked.
 *
 * @see \Drupal\Core\Render\Element\FormElement
 * @see https://www.drupal.org/node/169815 "Creating Custom Elements"
 *
 * @FormElement("workflow_transition")
 */
class WorkflowTransitionElement extends FormElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo(): array {
    $class = static::class;
    return [
      '#input' => TRUE,
      '#return_value' => 1,
      '#process' => [
        [$class, 'processTransition'],
        [$class, 'processAjaxForm'],
      ],
      '#element_validate' => [
        [$class, 'validateTransition'],
      ],
      '#theme_wrappers' => ['form_element'],
    ];
  }

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The form ID.
   *
   * @usage Do not change name lightly.
   *   It is also used in hook_form_FORM_ID_alter().
   */
  public static function getFormId(): string {
    return 'workflow_transition_form';
  }

  /**
   * Generate an element.
   *
   * This function is referenced in the Annotation for this class.
   *
   * @param array $element
   *   The element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $complete_form
   *   The form.
   *
   * @return array
   *   The Workflow element
   */
  public static function processTransition(array &$element, FormStateInterface $form_state, array &$complete_form): array {
    workflow_debug(__FILE__, __FUNCTION__, __LINE__); // @todo D8: test this snippet.
    return WorkflowTransitionElement::alter($element, $form_state, $complete_form);
  }

  /**
   * Changes the WorkflowTransition element, created by Field UI.
   *
   * Internal function, to be reused in:
   * - TransitionElement,
   * - TransitionDefaultWidget.
   *
   * @param array $element
   *   Reference to the form element.
   * @param \Drupal\Core\Form\FormStateInterface|null $form_state
   *   The form state.
   * @param array $complete_form
   *   The form.
   *
   * @return array
   *   The form element $element.
   *
   * @usage:
   *   @example $element['#default_value'] = $transition;
   *   @example $element += WorkflowTransitionElement::alter($element, $form_state, $form);
   */
  public static function alter(array &$element, ?FormStateInterface $form_state, array &$complete_form): array {

    // A Transition object must have been set explicitly.
    /** @var \Drupal\workflow\Entity\WorkflowTransitionInterface $transition */
    $transition = $element['#default_value'];
    $field_name = $transition->getFieldName();
    $field_label = $transition->getFieldLabel();
    $wid = $transition->getWorkflowId();

    // The help text is not available for container. Let's add it to the
    // 'to_sid' widget. N.B. it is empty on Workflow Tab, Node View page.
    // @see www.drupal.org/project/workflow/issues/3217214
    /*
     * Output: generate the element.
     */

    // Prepare a UI wrapper. It might be a (collapsible) fieldset.
    // Note: It will be overridden in WorkflowTransitionForm.
    $element = WorkflowTransitionElement::addWrapper($element);
    unset($element['#title']);
    // Add class following node-form pattern (both on form and container).
    $element['#attributes']['class'][] = "workflow-transition-{$wid}-container";
    $element['#attributes']['class'][] = "workflow-transition-container";

    // Start overriding BaseFieldDefinitions.
    // @see WorkflowTransition::baseFieldDefinitions()
    $attribute_name = 'field_name';
    $attribute_key = 'widget';
    $widget = [];
    $widget += self::getAttributeStates($attribute_name, $transition, []);
    self::updateWidget($element[$attribute_name], $attribute_key, $widget);

    $attribute_name = 'from_sid';
    $attribute_key = 'widget';
    // The 'from_state' cannot be changed, hence is always a 'value' formatter.
    $from_sid = $element[$attribute_name][$attribute_key]['#default_value'][0];
    if ($formatter = FALSE) {
      $entity = $transition->getTargetEntity();
      $widget = workflow_state_formatter($entity, $field_name, $from_sid);
      $widget['#title'] = t('Current state');
      $widget['#label_display'] = 'before'; // 'above', 'hidden'.
      $element[$attribute_name]['widget'] = $widget;
      $widget = [];
    }
    else {
      $element[$attribute_name]['widget']['#type'] = 'item'; // Read-only display element.
      $element[$attribute_name]['widget']['#markup'] = WorkflowState::load($from_sid);
      $widget = [];
    }
    $widget += self::getAttributeStates($attribute_name, $transition, []);
    self::updateWidget($element[$attribute_name], $attribute_key, $widget);

    // Add the 'options' widget.
    // It may be replaced later if 'Action buttons' are chosen.
    $attribute_name = 'to_sid';
    $attribute_key = 'widget';
    // Subfield is NEVER disabled in Workflow 'Manage form display' settings.
    // @see WorkflowTypeFormHooks class.
    if (isset($element[$attribute_name])) {
      // Reset $to_sid array to value, only needed for radios.
      $to_sid = $element[$attribute_name][$attribute_key]['#default_value'][0]
        ?? $element[$attribute_name][$attribute_key]['#default_value']
        // The following might be obsolete.
        ?? $element[$attribute_name][0][$attribute_key]['#default_value'][0]
        ?? $element[$attribute_name][0][$attribute_key]['#default_value']
        // Default value may be empty when field is added for existing entities.
        ?? '';
      $to_sid = is_array($to_sid) ? reset($to_sid) : $to_sid;
      $widget = [
        '#title' => t('Change @name', ['@name' => $field_label]),
        // @todo Manipulating '#default_value' only needed for 'radios'. Why?
        '#default_value' => $to_sid,
      ];
      // Adding ['#type','#access'].
      $widget += self::getAttributeStates($attribute_name, $transition, $element[$attribute_name][$attribute_key]);
      self::updateWidget($element[$attribute_name], $attribute_key, $widget);
    }

    // Display scheduling form under certain conditions.
    $attribute_name = 'scheduled';
    $attribute_key = 'widget';
    // Subfield may be disabled in Workflow 'Manage form display' settings.
    if (isset($element[$attribute_name])) {
      // Determine a unique class for '#states' API.
      $class_identifier = self::getClassIdentifier($transition, $form_state);

      // Copy weight from the timestamp, since that is set in the 'Manage form display' screen.
      $weight = $element['timestamp']['#weight']
        ?? $element['scheduled']['#weight'];
      // The 'scheduled' checkbox is directly above 'timestamp' widget.
      $element[$attribute_name]['#weight'] = $weight - 0.002;
      $widget = [
        '#weight' => $weight,
        '#attributes' => [
          'class' => [$class_identifier],
        ],
      ];
      $widget += self::getAttributeStates($attribute_name, $transition, []);
      // Determine the widget type - 'radios' and 'checkbox' are not compatible.
      $attribute_type = $element[$attribute_name]['widget']['#type']
        ?? $element[$attribute_name]['widget']['value']['#type'];
      ($attribute_type == 'radios')
        // For 'options_select', 'radios' widget.
        ? self::updateWidget($element[$attribute_name], $attribute_key, $widget)
        // For 'boolean_checkbox' widget.
        : self::updateWidget($element[$attribute_name][$attribute_key], 'value', $widget);

      // Display scheduling timestamp element under certain conditions.
      $attribute_name = 'timestamp';
      $attribute_key = 'widget';
      // Subfield may be disabled in Workflow 'Manage form display' settings.
      if (isset($element[$attribute_name])) {
        $element[$attribute_name] += [
          // @see https://www.drupal.org/docs/drupal-apis/form-api/conditional-form-fields
          '#states' => [
            'visible' => [
              // For some reason, adding both lines will break the widget.
              ($attribute_type == 'radios')
              // For 'options_buttons' widget.
                ? [":input[class^='{$class_identifier}']" => ['value' => '1']]
              // For 'boolean_checkbox' widget.
                : [":input[class^='{$class_identifier}']" => ['checked' => TRUE]],
            ],
          ],
        ];

        $widget = [
          '#workflow_transition' => $transition,
          // A #date_increment multiple of 60 will hide the "seconds"-component.
          // Time is rounded to last minute in WT::getDefaultRequestTime().
          '#date_increment' => 60,
        ];
        $widget += self::getAttributeStates($attribute_name, $transition, []);
        self::updateWidget($element[$attribute_name][$attribute_key], 'value', $widget);
      }
    }

    // Show comment, when both Field and Instance allow this.
    $attribute_name = 'comment';
    $attribute_key = 'value';
    // Subfield may be disabled in Workflow 'Manage form display' settings.
    if (isset($element[$attribute_name])) {
      $widget = [];
      $widget += self::getAttributeStates($attribute_name, $transition);
      self::updateWidget($element[$attribute_name]['widget'], $attribute_key, $widget);
    }

    // Let user/system enforce the transition.
    $attribute_name = 'force';
    $attribute_key = 'widget';
    // Subfield may be disabled in Workflow 'Manage form display' settings.
    if (isset($element[$attribute_name])) {
      $widget = [];
      $widget += self::getAttributeStates($attribute_name, $transition);
      // Determine the widget type - 'radios' and 'checkbox' are not compatible.
      $attribute_type = $element[$attribute_name]['widget']['#type']
        ?? $element[$attribute_name]['widget']['value']['#type'];
      ($attribute_type == 'radios')
        // For 'options_select', 'radios' widget.
        ? self::updateWidget($element[$attribute_name], $attribute_key, $widget)
        // For 'boolean_checkbox' widget.
        : self::updateWidget($element[$attribute_name][$attribute_key], 'value', $widget);
    }

    $attribute_name = 'executed';
    $attribute_key = 'widget';
    if (isset($element[$attribute_name])) {
      $widget = [];
      $widget += self::getAttributeStates($attribute_name, $transition);
      self::updateWidget($element[$attribute_name], 'widget', $widget);
    }

    return $element;
  }

  /**
   * Internal function to generate a wrapper with title for an element.
   *
   * @param array $element
   *   The form element to be altered.
   *
   * @return array
   *   The form element $element.
   *
   * @usage:
   *   @example $element = WorkflowTransitionElement::addWrapper($element);
   */
  protected static function addWrapper(array $element): array {
    $transition = $element['#default_value'];
    $workflow_settings = $transition->getWorkflow()?->getSettings();

    $element = [
      '#type' => ($workflow_settings['fieldset'] != 0) ? 'details' : 'container',
      // Title may be NULL, since it will overwrite the 'History' page.
      '#title' => $workflow_settings['name_as_title']
        ? $transition->getFieldLabel()
        : NULL,
      '#collapsible' => ($workflow_settings['fieldset'] != 0),
      '#open' => ($workflow_settings['fieldset'] != 2),
      '#tree' => TRUE,
    ] + $element;

    return $element;
  }

  /**
   * Adds the workflow attributes to the standard attribute of each widget.
   *
   * For some reason, the widgets are in another level when the entity form page
   * is presented, then when the entity form page is submitted.
   *
   * @param array $haystack
   *   The array in which the widget is hidden.
   * @param string $attribute_key
   *   The widget key.
   * @param array $data
   *   The additional workflow data for the widget.
   */
  protected static function updateWidget(array &$haystack, string $attribute_key, array $data): void {
    if (isset($haystack[0][$attribute_key])) {
      $haystack[0][$attribute_key] = $data + $haystack[0][$attribute_key];
    }
    elseif (!empty($haystack[$attribute_key])) {
      $haystack[$attribute_key] = $data + $haystack[$attribute_key];
    }
    else {
      // Subfield is disabled in Workflow 'Manage form display' settings.
      // Do not add our data.
    }
  }

  /**
   * Define class for '#states' behavior.
   *
   * First, fetch the form ID. This is unique for each entity,
   * to allow multiple forms per page (Views, etc.).
   * Make it uniquer by adding the field name, or else the scheduling of
   * multiple workflow_fields is not independent of each other.
   * If we are indeed on a Transition form (so, not a Node Form with widget)
   * then change the form ID, too.
   *
   * @param \Drupal\workflow\Entity\WorkflowTransitionInterface $transition
   *   The transition at hand.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return string
   *   The unique class for the WorkflowTransitionForm.
   */
  protected static function getClassIdentifier(WorkflowTransitionInterface $transition, FormStateInterface $form_state): string {
    $form_id = $form_state?->getFormObject()->getFormId()
      ?? WorkflowTransitionElement::getFormId();
    $form_uid = Html::getUniqueId($form_id);
    // @todo Align with WorkflowTransitionForm->getFormId().
    $field_name = $transition->getFieldName();
    $class_identifier = Html::getClass("scheduled_{$form_uid}-{$field_name}");
    return $class_identifier;
  }

  /**
   * Determines the #states of a Form attribute.
   *
   * States can have the following form:
   *   $states = [
   *     '#type' => {'select' | 'hidden'},
   *     '#access' => {FALSE | TRUE },
   *     '#required' => {FALSE | TRUE },
   *   ];
   *
   * @param string $attribute_name
   *   The attribute name.
   * @param \Drupal\Core\Entity\EntityInterface $transition
   *   The transition object.
   * @param array $element
   *   The current element of the attribute, holding information.
   *
   * @return array
   *   The field states.
   *
   * @see https://git.drupalcode.org/project/drupal/-/blob/11.x/core/lib/Drupal/Core/Form/FormHelper.php
   */
  public static function getAttributeStates(string $attribute_name, WorkflowTransitionInterface $transition, array $element = []): array {
    $states = [];
    /*
    @see https://www.drupal.org/docs/drupal-apis/form-api/conditional-form-fields
    Here is a list of properties that are used during the rendering and form processing of form elements:
    - #access: (bool) Whether the element is accessible or not; when FALSE, the element is not rendered and the user submitted value is not taken into consideration.
    - #disabled: (bool) If TRUE, the element is shown but does not accept user input.
    - #input: (bool, internal) Whether or not the element accepts input.
    - #required: (bool) Whether or not input is required on the element.
    - #states: (array) Information about JavaScript states, such as when to hide or show the element based on input on other elements. Refer to FormHelper::processStates.
    - #value: Used to set values that cannot be edited by the user. Should NOT be confused with #default_value, which is for form inputs where users can override the default value. Used by: button, hidden, image_button, submit, token, value.

    // '#states' => [
    //   'visible' => ["input.$class_identifier" => ['value' => '1']],
    //   'visible' => [':input[name="field_1"]' => ['value' => 'two']],
    //   'required' => [':input[name="field_1"]' => ['value' => 'two']],
    //   'required' => [TRUE],
    // ],
     */

    // Avoid loading same user many times by using static.
    static $user = NULL;
    // Avoid PHP8.2 Error: Constant expression contains invalid operations.
    $user ??= workflow_current_user();
    /** @var \Drupal\workflow\Entity\WorkflowTransitionInterface $transition */
    // Workflow might be empty on Action/VBO configuration.
    $wid = $transition->getWorkflowId();
    $workflow = $transition->getWorkflow();
    $workflow_settings = $workflow?->getSettings();

    $controller = WorkflowTransitionFormController::create($transition);
    $show_options_widget = $controller->mustShowOptionsWidget();

    switch ($attribute_name) {
      /*
      // @see https://www.drupal.org/docs/drupal-apis/form-api/conditional-form-fields
      // Since states are driven by JavaScript only, it is important to
      // understand that all states are applied on presentation only,
      // none of the states force any server-side logic, and that they will
      // not be applied for site visitors without JavaScript support.
      $form['field_2'] = [
        '#type' => 'select',
        '#title' => $this->t('Field 2'),
        '#options' => [
          'A' => $this->t('A'),
          'B' => $this->t('B'),
          'C' => $this->t('C'),
          'D' => $this->t('D'),
        ],
        '#required' => TRUE,
        '#disabled' => TRUE,
        '#states' => [
          'visible' => [
            ':input[name="field_1"]' => ['value' => 'two']
          ],
          'optional' => [
            ':input[name="field_1"]' => ['value' => 'one']
          ],
          'required' => [
            ':input[name="field_1"]' => ['value' => 'two']
          ],
        ],
      ];
       */
      case 'field_name':
        // Only show field_name on VBO/Actions screen.
        $states = ['#access' => FALSE];
        break;

      case 'from_sid':
        $states = [
          '#access' => FALSE,
          // The 'required' asterisk from BaseField will be removed in the form.
          '#required' => FALSE,
        ];
        // Decide if we show either a widget or a formatter.
        // Add a state formatter before the rest of the form,
        // when transition is scheduled or widget is hidden.
        // Also no widget if the only option is the current sid.
        if ($transition->isScheduled()
          || $transition->isExecuted()
          || !$show_options_widget
        ) {
          $states = [
            '#access' => TRUE,
            // The 'required' asterisk from BaseField will be removed in form.
            '#required' => FALSE,
          ];
        }
        break;

      case 'to_sid':
        $allowed_options = $element['#options'];
        // $allowed_options = $transition->getSettableOptions(NULL, $attribute_name);
        $widget_has_one_option = 1 == count($allowed_options);

        $options_type = $workflow_settings['options'];
        // Avoid error with grouped options when workflow not set.
        $options_type = $wid ? $options_type : 'select';
        // Suppress 'buttons' on 'edit executed transition'.
        $options_type = $show_options_widget && !$widget_has_one_option ? $options_type : 'radios';

        // Note: we SET the button type here in a static variable.
        if (WorkflowTransitionButtons::useActionButtons($options_type)) {
          // In WorkflowTransitionForm, a default 'Submit' button is added there.
          // In Entity Form, workflow_form_alter() adds button per permitted state.
          // Performance: inform workflow_form_alter() to do its job.
          //
          // Make sure the '#type' is not set to the invalid 'buttons' value.
          // It will be replaced by action buttons, but sometimes, the select box
          // is still shown.
          // @see workflow_form_alter().
          $states = [
            '#type' => 'select',
            '#access' => FALSE,
            // The 'required' asterisk from BaseField will be removed in the form.
            '#required' => FALSE,
          ];
        }
        else {
          // When not $show_options_widget, the 'from_sid' is displayed.
          $states = [
            '#type' => $options_type,
            '#access' => $show_options_widget,
            // The 'required' asterisk from BaseField will be removed in the form.
            '#required' => FALSE,
          ];
        }
        break;

      case 'scheduled':
        $controller = WorkflowTransitionFormController::create($transition);
        $add_schedule = $controller->isSchedulingAllowed();
        // Admin may have disabled schedule, while scheduled transitions exist.
        $default_value = $add_schedule && $transition->isScheduled();
        $states = [
          '#default_value' => $default_value,
          '#access' => $add_schedule,
          // The 'required' asterisk from BaseField will be removed in the form.
          '#required' => FALSE,
        ];
        break;

      case 'timestamp':
        $controller = WorkflowTransitionFormController::create($transition);
        $add_schedule = $controller->isSchedulingAllowed();
        $states = [
          '#access' => $add_schedule,
        ];
        break;

      case 'comment':
        $states = [
          // [0 => 'hidden', 1 => 'optional', 2 => 'required',];
          '#access' => ($workflow_settings['comment_log_node'] != '0'),
          '#required' => ($workflow_settings['comment_log_node'] == '2'),
        ];
        break;

      case 'force':
        $states = [
          // Only show 'force' parameter on VBO/Actions screen.
          '#access' => FALSE,
          // The 'required' asterisk from BaseField will be removed in the form.
          '#required' => FALSE,
        ];
        break;

      case 'executed':
        $states = [
          '#access' => FALSE,
        ];
        break;

      default:
        break;
    }

    return $states;
  }

}
