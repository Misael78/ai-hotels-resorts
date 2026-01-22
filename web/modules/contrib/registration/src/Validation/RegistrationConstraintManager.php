<?php

namespace Drupal\registration\Validation;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Validation\Attribute\Constraint;
use Drupal\Core\Validation\ConstraintFactory;

/**
 * The constraint plugin manager for registration constraints.
 */
class RegistrationConstraintManager extends DefaultPluginManager {

  /**
   * Overrides \Drupal\Component\Plugin\PluginManagerBase::__construct().
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    $this->factory = new ConstraintFactory($this);
    parent::__construct('Plugin/Validation/RegistrationConstraint', $namespaces, $module_handler, NULL, Constraint::class, 'Drupal\registration\Validation\Annotation\RegistrationConstraint');
    $this->alterInfo('registration_validation_constraint');
    $this->setCacheBackend($cache_backend, 'registration_validation_constraint_plugins');
  }

  /**
   * Creates a validation constraint.
   *
   * @param string $name
   *   The name or plugin id of the constraint.
   * @param mixed $options
   *   The options to pass to the constraint class. Required and supported
   *   options depend on the constraint class.
   *
   * @return \Symfony\Component\Validator\Constraint
   *   A validation constraint plugin.
   */
  public function create($name, $options) {
    if (!is_array($options)) {
      // Plugins need an array as configuration, so make sure we have one.
      // The constraint classes support passing the options as part of the
      // 'value' key also.
      $options = isset($options) ? ['value' => $options] : [];
    }
    return $this->createInstance($name, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition(&$definition, $plugin_id) {
    // Make sure 'type' is set and either an array or FALSE.
    if ($definition['type'] !== FALSE && !is_array($definition['type'])) {
      $definition['type'] = [$definition['type']];
    }
  }

  /**
   * Returns a list of constraints that support the given type.
   *
   * @param string $type
   *   The type to filter on.
   *
   * @return array
   *   An array of constraint plugin definitions supporting the given type,
   *   keyed by constraint name (plugin ID).
   */
  public function getDefinitionsByType($type) {
    $definitions = [];
    foreach ($this->getDefinitions() as $plugin_id => $definition) {
      if ($definition['type'] === FALSE || in_array($type, $definition['type'])) {
        $definitions[$plugin_id] = $definition;
      }
    }
    return $definitions;
  }

}
