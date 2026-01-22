<?php

namespace Drupal\registration\Plugin\Field\FieldFormatter;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'registration_link' formatter.
 *
 * @FieldFormatter(
 *   id = "registration_link",
 *   label = @Translation("Registration link"),
 *   field_types = {
 *     "registration",
 *   }
 * )
 */
class RegistrationLinkFormatter extends FormatterBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): RegistrationLinkFormatter {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    $options = parent::defaultSettings();

    $options['label'] = '';
    $options['show_reason'] = FALSE;
    $options['css_classes'] = '';
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form = parent::settingsForm($form, $form_state);
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#description' => $this->t("Optional label to use when displaying the registration title or link. Leave blank to use the parent event's label."),
      '#default_value' => $this->getSetting('label'),
    ];
    $form['show_reason'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show a reason when the link is hidden'),
      '#description' => $this->t("Displays a short message when registration is not available and the link is hidden."),
      '#default_value' => $this->getSetting('show_reason'),
    ];
    $form['css_classes'] = [
      '#type' => 'textfield',
      '#title' => $this->t('CSS class name(s)'),
      '#description' => $this->t("Separate multiple classes by spaces."),
      '#default_value' => $this->getSetting('css_classes'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = [];
    if ($label = $this->getSetting('label')) {
      $summary[] = $this->t('Registration label: @label', [
        '@label' => $label,
      ]);
    }
    else {
      $summary[] = $this->t('Registration label: Parent label');
    }
    if ($show_reason = $this->getSetting('show_reason')) {
      $summary[] = $this->t('Show reason when hidden: True');
    }
    else {
      $summary[] = $this->t('Show reason when hidden: False');
    }
    if ($css_classes = $this->getSetting('css_classes')) {
      $summary[] = $this->t('CSS class: @css_classes', [
        '@css_classes' => $css_classes,
      ]);
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
          $registration_type = $this->entityTypeManager
            ->getStorage('registration_type')
            ->load($id);
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

              // Only show the link if the host entity is open for registration.
              // For performance reasons, the isAvailableForRegistration
              // method, which takes capacity into account, is not used here.
              // That method has a dependency on when registrations are added,
              // updated or deleted, causing this render array to be rebuilt
              // more often than desired. The minor downside is the possibility
              // that capacity has been reached - if that happens, the user may
              // click on a link that takes them to a page indicating there is
              // no more room, which is a minor UX consideration.
              $validation_result = $host_entity->isOpenForRegistration(TRUE);
              $validation_result->getCacheableMetadata()->applyTo($elements);

              if ($validation_result->isValid()) {
                $entity_type_id = $host_entity->getEntityTypeId();
                $url = Url::fromRoute("entity.$entity_type_id.registration.register", [
                  $entity_type_id => $host_entity->id(),
                ]);
                $label = $this->getSetting('label') ?: $registration_type->label();
                $class[] = $this->getSetting('css_classes');
                $link = Link::fromTextAndUrl($label, $url)->toRenderable();
                $link['#attributes'] = ['class' => $class];
                $elements[] = $link;
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
