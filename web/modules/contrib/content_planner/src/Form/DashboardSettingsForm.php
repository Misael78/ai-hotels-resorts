<?php

namespace Drupal\content_planner\Form;

use Drupal\content_planner\DashboardBlockBase;
use Drupal\content_planner\DashboardSettingsService;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a form that configures forms module settings.
 */
class DashboardSettingsForm extends ConfigFormBase {

  /**
   * The dashboard settings service.
   *
   * @var \Drupal\content_planner\DashboardSettingsService
   */
  protected $dashboardSettingsService;

  /**
   * The dashboard block plugin manager.
   *
   * @var \Drupal\content_planner\DashboardBlockPluginManager
   */
  protected $dashboardBlockPluginManager;

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->dashboardSettingsService = $container->get('content_planner.dashboard_settings_service');
    $instance->dashboardBlockPluginManager = $container->get('content_planner.dashboard_block_plugin_manager');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'content_planner_dashboard_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      DashboardSettingsService::CONFIG_NAME,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, Request $request = NULL) {

    // Get block configurations from settings.
    $block_configurations = $this->dashboardSettingsService->getBlockConfigurations();

    // Get registered Plugins.
    $plugins = $this->dashboardBlockPluginManager->getDefinitions();
    uksort($plugins, function ($a, $b) {
      return strnatcmp($a, $b);
    });

    $block_options = [];

    // Loop over every plugin and generate renderable array.
    foreach ($plugins as $plugin_id => $plugin) {

      $block_options[$plugin_id] = $plugin['name'];

      if (array_key_exists($plugin_id, $block_configurations)) {

        $config_link = Link::createFromRoute(
          $this->t('Configure'),
          'content_planner.dashboard_block_config_form',
          ['block_id' => $plugin_id],
          [
            'query' => [
              'destination' => Url::fromRoute('content_planner.dashboard_settings')->toString(),
            ],
          ]
        );

        $block_options[$plugin_id] .= ' (' . $config_link->toString() . ')';
      }
    }

    // Default value.
    if ($block_configurations) {
      $default_value = array_keys($block_configurations);
    }
    else {
      $default_value = [];
    }

    $form['available_dashboard_blocks'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Dashboard Widgets'),
      '#collapsible' => FALSE,
      '#collapsed' => FALSE,
    ];

    $form['available_dashboard_blocks']['enabled_blocks'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Available Dashboard Widgets'),
      '#description' => $this->t('Select which Widgets should be displayed in the Dashboard.'),
      // '#required' => TRUE,.
      '#options' => $block_options,
      '#default_value' => $default_value,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $block_configurations = $this->dashboardSettingsService->getBlockConfigurations();

    // Get values.
    $values = $form_state->getValues();

    foreach ($values['enabled_blocks'] as $key => $selected) {

      if ($selected) {

        // If the block is not in the config already.
        if (!array_key_exists($key, $block_configurations)) {
          $config = DashboardBlockBase::getBasicConfigStructure();

          $config['plugin_id'] = $key;
          $block_configurations[$key] = $config;
        }

      }
      else {

        // Delete block if deselecte.
        if (array_key_exists($key, $block_configurations)) {
          unset($block_configurations[$key]);
        }

      }
    }

    // Save.
    $this->dashboardSettingsService->saveBlockConfigurations($block_configurations);
  }

}
