<?php

namespace Drupal\registration_change_host\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\registration\Entity\RegistrationInterface;
use Drupal\registration\Form\RegisterForm;
use Drupal\registration_change_host\RegistrationChangeHostManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the ChangeHost form.
 */
class ChangeHostForm extends RegisterForm {

  /**
   * The registration change host manager.
   *
   * @var \Drupal\registration_change_host\RegistrationChangeHostManagerInterface
   */
  protected RegistrationChangeHostManagerInterface $registrationChangeHostManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): ChangeHostForm {
    $instance = parent::create($container);
    $instance->registrationChangeHostManager = $container->get('registration_change_host.manager');
    $instance->logger = $container->get('registration_change_host.logger');
    return $instance;
  }

  /**
   * Checks access for this form.
   *
   * @param \Drupal\Core\Routing\RouteMatch $route_match
   *   The current route match.
   * @param \Drupal\registration\Entity\RegistrationInterface $registration
   *   The registration being changed.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(?RouteMatchInterface $route_match = NULL, ?RegistrationInterface $registration = NULL) {
    $host_type_id = $route_match->getRawParameter('host_type_id');
    $host_id = $route_match->getRawParameter('host_id');
    $set = $this->registrationChangeHostManager->getPossibleHosts($registration);

    $host = $set->getHost($host_id, $host_type_id);
    if ($host) {
      if ($host->isCurrent()) {
        return AccessResult::forbidden("Host $host_type_id $host_id is the current host and cannot be changed using this form.")->addCacheableDependency($registration);
      }
      $availability_result = $host->isAvailable(TRUE);
      if ($availability_result->isValid()) {
        $result = AccessResult::allowed();
      }
      else {
        $result = AccessResult::forbidden()->setReason($availability_result->getReason());
      }
      return $result->addCacheableDependency($availability_result)->addCacheableDependency($set);
    }
    return AccessResult::forbidden("Host $host_type_id $host_id not found in possible hosts.")->addCacheableDependency($set);
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);
    /** @var \Drupal\registration\Entity\RegistrationInterface $registration */
    $registration = $this->getEntity();
    $original_registration = \Drupal::entityTypeManager()->getStorage('registration')->loadUnchanged($registration->id());
    $old_host = $original_registration->getHostEntity();
    $form_state->set('old_host_entity', $old_host);
    $new_host = $form_state->get('host_entity');
    $message = $this->t("The registration will be changed from %old_host to %new_host.",
      [
        '%old_host' => $old_host->label(),
        '%new_host' => $new_host->label(),
      ]);
    $form['change_host_notice'] = [
      '#markup' => '<p>' . $message . '</p>',
      '#weight' => 100,
      '#group' => 'footer',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityFromRouteMatch(RouteMatchInterface $route_match, $entity_type_id = NULL) {
    /** @var \Drupal\registration\Entity\RegistrationInterface $registration */
    $registration = $route_match->getParameter('registration');
    if (!$registration instanceof RegistrationInterface) {
      throw new \Exception('Registration must be supplied to ChangeHostForm');
    }
    $new_host_type_id = $this->getRouteMatch()->getRawParameter('host_type_id') ?? $registration->getHostEntity()->getEntityTypeId();
    $new_host_id = $this->getRouteMatch()->getRawParameter('host_id');

    // The host may have already been changed if this method is called
    // multiple times.
    if ($registration->getHostEntityTypeId() !== $new_host_type_id || $registration->getHostEntityId() != $new_host_id) {
      $registration = $this->registrationChangeHostManager->changeHost($registration, $new_host_type_id, $new_host_id);
    }
    return $registration;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $old_host = $form_state->get('old_host_entity');
    return $this->registrationChangeHostManager->saveChangedHost(
      $this->getEntity(),
      $old_host,
      fn() => parent::save($form, $form_state)
    );
  }

  /**
   * Provides the title for the change host page.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param \Drupal\registration\Entity\RegistrationInterface $registration
   *   The registration.
   *
   * @return string
   *   The title.
   *
   * @phpcs:disable Drupal.Semantics.FunctionT.NotLiteralString
   */
  public function title(RouteMatchInterface $route_match, RegistrationInterface $registration) {
    /** @var \Drupal\registration\Entity\RegistrationInterface $changed_registration */
    $changed_registration = $this->getEntityFromRouteMatch($route_match, $registration);
    $config = $this->config('registration_change_host.settings');
    return $this->t($config->get('confirm_form_title'), [
      '@host_type_label' => $changed_registration->getHostEntityTypeLabel(),
      '%id' => $changed_registration->id(),
    ]);
  }

  /**
   * Ensure the host entity is set.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  protected function setHostEntity(FormStateInterface $form_state) {
    /** @var \Drupal\registration\Entity\RegistrationInterface $entity */
    $entity = $this->getEntity();
    $host_type = $this->getRouteMatch()->getRawParameter('host_type') ?? $entity->getHostEntity()->getEntityTypeId();
    $host_id = $this->getRouteMatch()->getRawParameter('host_id');
    $host_entity = $form_state->get('host_entity');
    if (!isset($host_entity) || $host_entity->getEntityTypeId() != $host_type || $host_entity->getEntityId() != $host_id) {
      $host = $this->entityTypeManager->getStorage($host_type)->load($host_id);
      $handler = $this->entityTypeManager->getHandler($host_type, 'registration_host_entity');
      $host_entity = $handler->createHostEntity($host);
      $form_state->set('host_entity', $host_entity);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state): array {
    $actions = parent::actions($form, $form_state);
    $host = $form_state->get('host_entity');

    // Improve save button.
    $actions['submit']['#value'] = $this->t('Save and confirm');

    // Disable delete button when changing host.
    $actions['delete']['#access'] = FALSE;

    // Add a cancel link.
    $actions['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => $host->getEntity()->toUrl(),
      '#weight' => 20,
    ];

    return $actions;
  }

}
