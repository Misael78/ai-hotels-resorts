<?php

namespace Drupal\registration_workflow;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\workflows\Entity\Workflow;
use Drupal\workflows\State;

/**
 * Provides registration workflow permissions.
 */
class RegistrationWorkflowPermissionProvider {

  use StringTranslationTrait;

  /**
   * Builds an array of transition permissions.
   *
   * @return array
   *   The transition permissions.
   */
  public function buildPermissions() {
    $permissions = [];
    /** @var \Drupal\workflows\WorkflowInterface $workflow */
    foreach (Workflow::loadMultipleByType('registration') as $workflow) {
      foreach ($workflow->getTypePlugin()->getTransitions() as $transition) {
        $permissions['use ' . $workflow->id() . ' ' . $transition->id() . ' transition'] = [
          'title' => $this->t('%workflow workflow: Use the %transition transition.', [
            '%workflow' => $workflow->label(),
            '%transition' => $transition->label(),
          ]),
          'description' => $this->formatPlural(
            count($transition->from()),
            'Move registrations from %from state to %to state.',
            'Move registrations from %from states to %to state.', [
              '%from' => implode(', ', array_map([State::class, 'labelCallback'], $transition->from())),
              '%to' => $transition->to()->label(),
            ]
          ),
          'dependencies' => [
            $workflow->getConfigDependencyKey() => [$workflow->getConfigDependencyName()],
          ],
        ];
      }
    }

    return $permissions;
  }

}
