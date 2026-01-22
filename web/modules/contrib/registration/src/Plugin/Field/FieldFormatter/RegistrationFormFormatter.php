<?php

namespace Drupal\registration\Plugin\Field\FieldFormatter;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'registration_form' formatter.
 *
 * @FieldFormatter(
 *   id = "registration_form",
 *   label = @Translation("Registration form"),
 *   field_types = {
 *     "registration",
 *   }
 * )
 */
class RegistrationFormFormatter extends FormatterBase {

  /**
   * The entity form builder.
   *
   * @var \Drupal\Core\Entity\EntityFormBuilderInterface
   */
  protected EntityFormBuilderInterface $entityFormBuilder;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): RegistrationFormFormatter {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityFormBuilder = $container->get('entity.form_builder');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    $options = parent::defaultSettings();

    $options['show_reason'] = FALSE;
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form = parent::settingsForm($form, $form_state);
    $form['show_reason'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show a reason when the form is hidden'),
      '#description' => $this->t("Displays a short message when registration is not available and the form is hidden."),
      '#default_value' => $this->getSetting('show_reason'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = [];
    if ($show_reason = $this->getSetting('show_reason')) {
      $summary[] = $this->t('Show reason when hidden: True');
    }
    else {
      $summary[] = $this->t('Show reason when hidden: False');
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    if (($entity = $items->getEntity()) && !$entity->isNew()) {
      if (isset($items[0])) {
        if ($id = $items[0]->getValue()['registration_type']) {
          $registration_type = $this->entityTypeManager->getStorage('registration_type')->load($id);
          if ($registration_type) {
            // Check access.
            $access_result = $this->entityTypeManager
              ->getAccessControlHandler('registration')
              ->createAccess($id, NULL, [], TRUE);
            if ($access_result->isAllowed()) {
              /** @var \Drupal\registration\HostEntityInterface $host_entity */
              $host_entity = $this->entityTypeManager
                ->getHandler($entity->getEntityTypeId(), 'registration_host_entity')
                ->createHostEntity($entity, $langcode);

              // Only show the form if the host entity is open for registration.
              // For performance reasons, the isAvailableForRegistration
              // method, which takes capacity into account, is not used here.
              // That method has a dependency on when registrations are added,
              // updated or deleted, causing this render array to be rebuilt
              // more often than desired.
              $validation_result = $host_entity->isOpenForRegistration(TRUE);
              $validation_result->getCacheableMetadata()->applyTo($elements);

              if ($validation_result->isValid()) {
                $registration = $this->entityTypeManager->getStorage('registration')->create([
                  'entity_type_id' => $host_entity->getEntityTypeId(),
                  'entity_id' => $host_entity->id(),
                  'type' => $registration_type->id(),
                ]);
                // Add the host entity to the form state.
                $form = $this->entityFormBuilder->getForm($registration, 'register', [
                  'host_entity' => $host_entity,
                ]);
                $elements[] = $form;

                // Add the form to cacheability.
                $form_metadata = CacheableMetadata::createFromRenderArray($form);
                $elements_metadata = CacheableMetadata::createFromRenderArray($elements);
                $elements_metadata = $elements_metadata->merge($form_metadata);
                $elements_metadata->applyTo($elements);
              }
              elseif ($this->getSetting('show_reason')) {
                $elements[] = [
                  '#markup' => $validation_result->getReason(),
                ];
              }
            }

            // Add the access result to cacheability.
            $cacheability = CacheableMetadata::createFromRenderArray($elements);
            $cacheability->addCacheableDependency($access_result);
            $cacheability->applyTo($elements);
          }
        }
      }
    }
    return $elements;
  }

}
