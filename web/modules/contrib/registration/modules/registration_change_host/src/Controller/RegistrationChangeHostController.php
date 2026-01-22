<?php

namespace Drupal\registration_change_host\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormState;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\registration\Entity\RegistrationInterface;
use Drupal\registration_change_host\RegistrationChangeHostManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Returns responses for Registration Change Host routes.
 */
class RegistrationChangeHostController extends ControllerBase {

  /**
   * The registration change host manager.
   *
   * @var \Drupal\registration_change_host\RegistrationChangeHostManagerInterface
   */
  protected RegistrationChangeHostManagerInterface $registrationChangeHostManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): RegistrationChangeHostController {
    $instance = parent::create($container);
    $instance->registrationChangeHostManager = $container->get('registration_change_host.manager');
    return $instance;
  }

  /**
   * Displays the appropriate interface to allow change of host entity.
   *
   * Checks the settings and displays either the single step change host form
   * or the change host page that is step one of the multistep workflow.
   *
   * @param \Drupal\registration\Entity\RegistrationInterface $registration
   *   The registration.
   *
   * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
   *   The render array for a form or page.
   *   Classes that extend this method may return a redirect response.
   */
  public function changeHost(RegistrationInterface $registration): array|RedirectResponse {
    $config = $this->config('registration_change_host.settings');
    if ($config->get('workflow') == 'single_step') {
      // Single step workflow.
      $form_state = new FormState();
      $form_object = $this->entityTypeManager()->getFormObject('registration', 'single_step_change_host');
      $form_object->setEntity($registration);
      $form = $this->formBuilder()->buildForm($form_object, $form_state);
    }
    else {
      // Multistep workflow.
      $form = $this->changeHostPage($registration);
    }

    // Add the settings configuration entity to the form cacheability.
    if (is_array($form)) {
      $config_metadata = CacheableMetadata::createFromObject($config);
      $form_metadata = CacheableMetadata::createFromRenderArray($form);
      $form_metadata->merge($config_metadata)->applyTo($form);
    }

    return $form;
  }

  /**
   * Displays the available hosts a registration can change to.
   *
   * @param \Drupal\registration\Entity\RegistrationInterface $registration
   *   The registration.
   *
   * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
   *   The render array showing each possible host.
   *   Classes that extend this method may return a redirect response.
   */
  public function changeHostPage(RegistrationInterface $registration): array|RedirectResponse {
    $set = $this->registrationChangeHostManager->getPossibleHosts($registration);

    if (!$set->hasAvailableHosts()) {
      \Drupal::messenger()->addMessage($this->t('There is nothing available to change to.'));
    }

    $build = [
      '#theme' => 'registration_change_host_list',
      '#set' => $set,
    ];

    // Merge metadata from the possible host set.
    $build_metadata = CacheableMetadata::createFromObject($set);
    // Merge metadata from each individual possible host.
    /** @var \Drupal\registration_change_host\PossibleHostEntityInterface $host */
    foreach ($set->getHosts() as $host) {
      $host_metadata = CacheableMetadata::createFromObject($host);
      $build_metadata = $build_metadata->merge($host_metadata);
    }
    $build_metadata->applyTo($build);

    return $build;
  }

  /**
   * Provides the title for the change host page.
   *
   * @param \Drupal\registration\Entity\RegistrationInterface $registration
   *   The registration.
   *
   * @return string
   *   The title.
   *
   * @phpcs:disable Drupal.Semantics.FunctionT.NotLiteralString
   */
  public function title(RegistrationInterface $registration): TranslatableMarkup {
    $config = $this->config('registration_change_host.settings');
    $title = $config->get('workflow') == 'single_step' ? 'form_title' : 'page_title';
    return $this->t($config->get($title), [
      '@host_type_label' => $registration->getHostEntityTypeLabel(),
      '%id' => $registration->id(),
    ]);
  }

}
