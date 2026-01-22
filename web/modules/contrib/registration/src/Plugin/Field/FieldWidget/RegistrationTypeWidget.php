<?php

namespace Drupal\registration\Plugin\Field\FieldWidget;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxy;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'registration_type' widget.
 *
 * @FieldWidget(
 *   id = "registration_type",
 *   label = @Translation("Registration type"),
 *   field_types = {
 *     "registration"
 *   }
 * )
 */
class RegistrationTypeWidget extends WidgetBase {

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected AccountProxy $currentUser;

  /**
   * The configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The entity type bundle information.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfo
   */
  protected EntityTypeBundleInfo $entityTypeBundleInfo;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->config = $container->get('config.factory')->get('registration.settings');
    $instance->currentUser = $container->get('current_user');
    $instance->entityTypeBundleInfo = $container->get('entity_type.bundle.info');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->moduleHandler = $container->get('module_handler');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'hide_register_tab' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $element = parent::settingsForm($form, $form_state);
    $element['hide_register_tab'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide Register Tab'),
      '#description' => $this->t('Hide the tab on the content displaying the registration form. The form can still be embedded or linked to by changing the field display settings.'),
      '#default_value' => (bool) $this->getSetting('hide_register_tab'),
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = parent::settingsSummary();
    if ($this->getSetting('hide_register_tab')) {
      $summary[] = $this->t('Hide the Register tab: Yes');
    }
    else {
      $summary[] = $this->t('Hide the Register tab: No');
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $default_value = $items[$delta]->get('registration_type')->getValue();
    $element['registration_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Registration type'),
      '#options' => $this->getRegistrationTypeOptions(),
      '#default_value' => $default_value,
    ];

    // Set the field help.
    $element['registration_type']['#description'] = $element['#description'];
    if (empty($element['#description'])) {
      // Default if field help is blank.
      $entity = $items->getEntity();
      $entity_type = $entity->getEntityTypeId();
      $entity_bundle = $entity->bundle();
      $bundle_info = $this->entityTypeBundleInfo->getBundleInfo($entity_type);
      $bundle = $bundle_info[$entity_bundle]['label'];

      $element['registration_type']['#description'] = $this->t('Select what type of registrations should be enabled for this @type. Depending on the display settings, it will appear as either string, registration link, or form.', [
        '@type' => $bundle,
      ]);
    }

    return $element;
  }

  /**
   * Returns an array of registration type options.
   *
   * @return array
   *   The array keyed by registration type machine name.
   */
  protected function getRegistrationTypeOptions(): array {
    $options = ['' => $this->t('-- Disable Registrations --')];
    $allowed_types = $this->getFieldSetting('allowed_types');
    $entities = $this->entityTypeManager->getStorage('registration_type')->loadMultiple();
    foreach ($entities as $id => $entity) {
      if ($this->canAssignType($id)) {
        if ($allowed_types) {
          if (in_array($id, $allowed_types)) {
            $options[$id] = $entity->label();
          }
        }
        else {
          $options[$id] = $entity->label();
        }
      }
    }
    return $options;
  }

  /**
   * Determines if a given type can be assigned to a registration field.
   *
   * @param string $id
   *   The machine name of the registration type.
   *
   * @return bool
   *   TRUE if the registration type can be assigned, FALSE otherwise.
   */
  protected function canAssignType(string $id): bool {
    if ($this->config->get('limit_field_values')) {
      return $this->currentUser->hasPermission("administer registration") || $this->currentUser->hasPermission("assign $id registration field");
    }
    return TRUE;
  }

}
