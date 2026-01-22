<?php

namespace Drupal\registration_change_host\Form;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\registration\Form\RegisterForm;
use Drupal\registration_change_host\RegistrationChangeHostManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the SingleStepChangeHostForm form.
 */
class SingleStepChangeHostForm extends RegisterForm {

  /**
   * The registration change host manager.
   *
   * @var \Drupal\registration_change_host\RegistrationChangeHostManagerInterface
   */
  protected RegistrationChangeHostManagerInterface $registrationChangeHostManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): SingleStepChangeHostForm {
    $instance = parent::create($container);
    $instance->registrationChangeHostManager = $container->get('registration_change_host.manager');
    $instance->logger = $container->get('registration_change_host.logger');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);

    $keep_keys = [
      'actions',
      'advanced',
      'meta',
      'notice',
    ];
    foreach (Element::children($form) as $key) {
      if (!in_array($key, $keep_keys)) {
        $form[$key]['#access'] = FALSE;
      }
    }

    /** @var \Drupal\registration\Entity\RegistrationInterface $registration */
    $registration = $this->getEntity();

    $set = $this->registrationChangeHostManager->getPossibleHosts($registration);
    if (!$set->hasAvailableHosts()) {
      $this->messenger()->addMessage($this->t('There is nothing available to change to.'));
    }

    // Merge metadata from the possible host set.
    $build_metadata = CacheableMetadata::createFromObject($set);
    // Merge metadata from each individual possible host.
    foreach ($set->getHosts() as $host) {
      $host_metadata = CacheableMetadata::createFromObject($host);
      $build_metadata = $build_metadata->merge($host_metadata);
    }
    $form_metadata = CacheableMetadata::createFromRenderArray($form);
    $form_metadata->merge($build_metadata)->applyTo($form);

    $options = [];
    foreach ($set->getHosts() as $host) {
      if (!$host->isCurrent() && $host->isAvailable()) {
        $options[$host->getEntityTypeId() . ':' . $host->id()] = $host->label();
      }
    }

    $form['old_host'] = [
      '#type' => 'item',
      '#title' => $this->t('Current:'),
      '#markup' => $registration->getHostEntity()->label(),
    ];

    $form['new_host'] = [
      '#type' => 'select',
      '#title' => $this->t('New:'),
      '#options' => $options,
      '#required' => TRUE,
    ];
    $form['message'] = [
      '#markup' => $this->t('All other fields of the registration will be retained.'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    /** @var \Drupal\registration\Entity\RegistrationInterface $registration */
    $registration = $this->getEntity();
    $new_host = $form_state->getValue('new_host');
    $values = explode(':', $new_host);
    $entity_type_id = $values[0];
    $entity_id = $values[1];
    if ($this->registrationChangeHostManager->isDataLostWhenHostChanges($registration, $entity_type_id, $entity_id, TRUE)) {
      $form_state->setError($form, $this->t('The selection has an incompatible registration type.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    /** @var \Drupal\registration\Entity\RegistrationInterface $registration */
    $registration = $this->getEntity();
    $old_host = $registration->getHostEntity();
    $new_host = $form_state->getValue(['new_host']);
    $values = explode(':', $new_host);
    $entity_type_id = $values[0];
    $entity_id = $values[1];
    $registration = $this->registrationChangeHostManager->changeHost($registration, $entity_type_id, $entity_id);
    return $this->registrationChangeHostManager->saveChangedHost(
      $registration,
      $old_host,
      fn() => parent::save($form, $form_state)
    );
  }

  /**
   * Returns an array of supported actions for the current entity form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   An array of supported Form API action elements keyed by name.
   */
  protected function actions(array $form, FormStateInterface $form_state): array {
    $actions = parent::actions($form, $form_state);
    $actions['delete']['#access'] = FALSE;
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityFromRouteMatch(RouteMatchInterface $route_match, $entity_type_id): EntityInterface {
    if ($route_match->getRawParameter($entity_type_id) !== NULL) {
      $entity = $route_match->getParameter($entity_type_id);
    }
    else {
      throw new \InvalidArgumentException("The registration could not be loaded.");
    }

    return $entity;
  }

}
