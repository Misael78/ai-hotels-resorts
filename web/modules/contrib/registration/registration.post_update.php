<?php

/**
 * @file
 * Post update functions for Registration.
 */

/**
 * Update registration workflows with complete state.
 */
function registration_post_update_1() {
  $workflows = \Drupal::entityTypeManager()
    ->getStorage('workflow')
    ->loadByProperties([
      'type' => 'registration',
    ]);

  // Find registration workflows using the default complete state and update
  // the workflow type plugin to formally identify it, to avoid the site
  // builder having to do it manually.
  foreach ($workflows as $workflow) {
    $plugin = $workflow->getTypePlugin();
    $configuration = $plugin->getConfiguration();
    if (empty($configuration['complete_registration_state'])) {
      if ($plugin->hasState('complete')) {
        $configuration['complete_registration_state'] = 'complete';
        $plugin->setConfiguration($configuration);
        $workflow->save();
      }
    }
  }
}

/**
 * Initialize the completed base field for existing complete registrations.
 */
function registration_post_update_2() {
  $clear_cache = FALSE;

  $registration_types = \Drupal::entityTypeManager()
    ->getStorage('registration_type')
    ->loadMultiple();

  // Get the workflow for each registration type and if the workflow has a
  // completed state, update the completed timestamp for registrations in that
  // state to match the "changed" timestamp. It is possible that the "changed"
  // timestamp does not correspond with the completion time of the registration,
  // but this will either be true, or will approximate the completion time, for
  // most registrations in a typical system. It is better for consistency to
  // have a completed time set for completed registrations, since this will
  // happen automatically for future completed registrations.
  foreach ($registration_types as $registration_type) {
    $plugin = $registration_type->getWorkflow()->getTypePlugin();
    $configuration = $plugin->getConfiguration();
    if (!empty($configuration['complete_registration_state'])) {
      $complete_state = $configuration['complete_registration_state'];
      $query = \Drupal::database()
        ->update('registration')
        ->condition('type', $registration_type->id())
        ->condition('state', $complete_state)
        ->expression('completed', 'changed');
      $affected_rows = $query->execute();
      if ($affected_rows > 0) {
        $clear_cache = TRUE;
      }
    }
  }

  // Clear cache if database updates were done, since the Entity API was not
  // used and there could be cached entities in the system that were updated.
  if ($clear_cache) {
    drupal_flush_all_caches();
  }
}
