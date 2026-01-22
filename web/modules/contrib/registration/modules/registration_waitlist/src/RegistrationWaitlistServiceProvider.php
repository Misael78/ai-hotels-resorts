<?php

namespace Drupal\registration_waitlist;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Modifies the registration workflow transition access checker.
 */
class RegistrationWaitlistServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    if ($container->hasDefinition('registration_workflow.state_transition_access_checker')) {
      $definition = $container->getDefinition('registration_workflow.state_transition_access_checker');
      $definition->setClass('Drupal\registration_waitlist\Access\StateTransitionAccessCheck');
    }
  }

}
