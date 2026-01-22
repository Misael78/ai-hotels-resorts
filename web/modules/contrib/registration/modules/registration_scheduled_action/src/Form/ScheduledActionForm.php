<?php

namespace Drupal\registration_scheduled_action\Form;

use Drupal\Core\Action\ActionManager;
use Drupal\Core\Action\ConfigurableActionBase;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Maintains new or existing scheduled actions.
 */
class ScheduledActionForm extends EntityForm {

  /**
   * The manager for action plugins.
   *
   * @var \Drupal\Core\Action\ActionManager
   */
  protected ActionManager $pluginManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * Constructs a ScheduledActionForm object.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Action\ActionManager $plugin_manager
   *   The manager for action plugins.
   */
  public function __construct(LanguageManagerInterface $language_manager, ActionManager $plugin_manager) {
    $this->languageManager = $language_manager;
    $this->pluginManager = $plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): ScheduledActionForm {
    return new static(
      $container->get('language_manager'),
      $container->get('plugin.manager.action')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\registration_scheduled_action\Entity\ScheduledActionInterface $scheduled_action */
    $scheduled_action = $this->entity;
    $form['#tree'] = TRUE;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $scheduled_action->label(),
      '#required' => TRUE,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $scheduled_action->id(),
      '#machine_name' => [
        'exists' => '\Drupal\registration_scheduled_action\Entity\ScheduledAction::load',
        'source' => ['label'],
      ],
      '#maxlength' => EntityTypeInterface::BUNDLE_MAX_LENGTH,
      '#disabled' => !$scheduled_action->isNew(),
    ];
    $form['target_langcode'] = [
      '#type' => 'select',
      '#options' => $this->getLanguageOptions(),
      '#title' => $this->t('Target language'),
      '#required' => TRUE,
      '#default_value' => $scheduled_action->getTargetLangcode(),
      '#access' => $this->languageManager->isMultilingual(),
    ];
    $form['datetime'] = [
      '#type' => 'registration_scheduled_action_datetime',
      '#title' => $this->t('When'),
      '#min' => 0,
      '#max' => 365,
      '#size' => 3,
      '#required' => TRUE,
      '#default_value' => $scheduled_action->getDateTime(),
      '#suffix' => $this->t('Enter the schedule relative to the selected action date.'),
    ];
    $form['plugin_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Action'),
      '#required' => TRUE,
      '#options' => $this->getPluginOptions(),
      '#default_value' => $scheduled_action->getPluginId(),
      '#ajax' => [
        'event' => 'change',
        'callback' => '::ajaxRefresh',
        'wrapper' => 'configuration-wrapper',
      ],
    ];
    $form['configuration'] = [
      '#prefix' => '<div id="configuration-wrapper">',
      '#type' => 'container',
      '#suffix' => '</div>',
    ];
    // Initialize the configuration in form state.
    if (!$form_state->hasValue('plugin_id')) {
      $form_state->setValue('plugin_id', $scheduled_action->getPluginId());
      $form_state->setValue('configuration', $scheduled_action->getPluginConfiguration());
    }
    // Display the plugin date.
    $form['configuration']['plugin_date'] = [
      '#title' => $this->t('Date'),
      '#type' => 'item',
      '#access' => $form_state->hasValue('plugin_id'),
    ];
    // Add the configuration form if the selected plugin is configurable.
    if ($plugin_id = $form_state->getValue('plugin_id')) {
      $plugin = $this->pluginManager->createInstance($plugin_id);
      if ($plugin instanceof ConfigurableActionBase) {
        $configuration = $form_state->getValue('configuration') ?? [];
        $plugin->setConfiguration($configuration);
        $plugin_form = $plugin->buildConfigurationForm([], $form_state);
        $form['configuration'] += $plugin_form;
        $form['configuration']['plugin_date']['#markup'] = $plugin->getDateFieldLabel();
      }
    }
    $form['status'] = [
      '#type' => 'radios',
      '#title' => $this->t('Status'),
      '#options' => [
        0 => $this->t('Disabled'),
        1  => $this->t('Enabled'),
      ],
      '#default_value' => (int) $scheduled_action->isEnabled(),
    ];
    return $form;
  }

  /**
   * AJAX callback for the scheduled action form.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The part of the form to update.
   */
  public function ajaxRefresh(array $form, FormStateInterface $form_state): array {
    return $form['configuration'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    /** @var \Drupal\registration_scheduled_action\Entity\ScheduledActionInterface $scheduled_action */
    $plugin_id = $values['plugin_id'];
    $selected_plugin = \Drupal::service('plugin.manager.action')->createInstance($plugin_id);

    // Ensure the provided date and time are allowed by the selected plugin.
    $position = $values['datetime']['values']['position'];
    $allowed_positions = $selected_plugin->getAllowedPositions();

    // Ensure at least one position is allowed.
    if (empty($allowed_positions)) {
      throw new \InvalidArgumentException("The selected plugin returned an invalid positions array.");
    }

    if (!in_array($position, $allowed_positions)) {
      if ($position == 'before') {
        $form_state->setErrorByName('datetime', $this->t('"Before" is not allowed for the selected action.'));
      }
      else {
        $form_state->setErrorByName('datetime', $this->t('"After" is not allowed for the selected action.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    /** @var \Drupal\registration_scheduled_action\Entity\ScheduledActionInterface $scheduled_action */
    $scheduled_action = $this->entity;
    $scheduled_action
      ->setPluginId($values['plugin_id'])
      ->setDateTime($values['datetime']['values'])
      ->setTargetLangcode($values['target_langcode'])
      ->setEnabled($values['status']);

    if ($scheduled_action->isNew()) {
      $scheduled_action->setWeight(0);
    }

    // Save the configuration if the plugin is configurable.
    $configuration = [];
    if ($scheduled_action->getPlugin() instanceof ConfigurableActionBase) {
      $configuration = $values['configuration'];
    }
    $scheduled_action->setPluginConfiguration($configuration);

    $return = $scheduled_action->save();

    $this->messenger()->addMessage($this->t('The scheduled action %label has been successfully saved.', ['%label' => $this->entity->label()]));
    $form_state->setRedirect('entity.registration_scheduled_action.collection');

    return $return;
  }

  /**
   * Get the list of available languages.
   *
   * @return array
   *   The list of available languages, keyed by language code.
   */
  protected function getLanguageOptions(): array {
    $options = [];
    $options[LanguageInterface::LANGCODE_NOT_SPECIFIED] = $this->t('- All -');
    $languages = $this->languageManager->getLanguages();
    foreach ($languages as $langcode => $language) {
      $options[$langcode] = $language->getName();
    }
    return $options;
  }

  /**
   * Get the list of available registration action plugins.
   *
   * These must implement the QueryableActionInterface so the plugin can select
   * the objects that the action will be applied to.
   *
   * @return array
   *   The list of available plugins, keyed by plugin ID.
   */
  protected function getPluginOptions(): array {
    $options = [];
    $plugins = $this->pluginManager->getDefinitionsByType('registration');
    foreach ($plugins as $id => $plugin) {
      $interfaces = class_implements($plugin['class']);
      if (isset($interfaces['Drupal\registration_scheduled_action\Action\QueryableActionInterface'])) {
        $options[$id] = $plugin['label'];
      }
    }
    asort($options);
    return $options;
  }

}
