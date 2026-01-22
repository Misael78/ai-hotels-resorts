<?php

namespace Drupal\registration_inline_entity_form;

/**
 * Provides #ief_element_submit, the IEF version of #element_validate.
 *
 * @see Drupal\inline_entity_form\ElementSubmit
 */
class RegistrationElementSubmit {

  /**
   * Attaches the #ief_element_submit functionality to the given form.
   *
   * @param array $form
   *   The form.
   */
  public static function attach(array &$form) {
    // Entity form actions.
    foreach (['submit', 'publish', 'unpublish'] as $action) {
      if (!empty($form['actions'][$action])) {
        self::addCallback($form['actions'][$action]);
      }
    }
    // Generic submit button.
    if (!empty($form['submit'])) {
      self::addCallback($form['submit']);
    }
  }

  /**
   * Adds the trigger callback to the given element.
   *
   * Unlike the standard callback in the IEF module, this is appended as the
   * last submit handler, so that registration handling can occur after other
   * entity handlers have run.
   *
   * @param array $element
   *   The element.
   */
  public static function addCallback(array &$element) {
    if (!empty($element['#submit'])) {
      $ief_trigger = ['Drupal\inline_entity_form\ElementSubmit', 'trigger'];
      $element['#submit'] = array_merge($element['#submit'], [$ief_trigger]);
    }
  }

}
