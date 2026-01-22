<?php

namespace Drupal\registration\Plugin\WorkflowType;

/**
 * An interface for registration workflow type plugins.
 */
interface RegistrationWorkflowTypeInterface {

  /**
   * Gets the available registration state options for the workflow plugin.
   *
   * The options are suitable for use in a form select element.
   *
   * @return array
   *   The states as an options array of labels keyed by ID.
   */
  public function getStateOptions(): array;

}
