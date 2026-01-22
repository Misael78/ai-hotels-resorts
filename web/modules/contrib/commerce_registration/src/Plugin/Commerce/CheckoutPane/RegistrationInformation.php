<?php

namespace Drupal\commerce_registration\Plugin\Commerce\CheckoutPane;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\commerce\InlineFormManager;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\registration\Entity\RegistrationInterface;
use Drupal\registration\HostEntityInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the registration information pane.
 *
 * This checkout pane is optional and is intended for use cases where the
 * person completing checkout has the option to purchase a registration for
 * someone else, or in any situation where data must be collected during
 * checkout to complete the registration. If fields have been added to your
 * registration types, there is a good chance this pane should be enabled
 * so data for those fields can be provided by the person completing checkout.
 *
 * If this pane is disabled, the "registration_process" pane should be enabled,
 * so it can create registrations during checkout completion.
 *
 * @see \Drupal\commerce_registration\Plugin\Commerce\CheckoutPane\RegistrationProcess
 *
 * @CommerceCheckoutPane(
 *   id = "registration_information",
 *   label = @Translation("Registration information"),
 *   wrapper_element = "fieldset",
 * )
 */
class RegistrationInformation extends CheckoutPaneBase implements CheckoutPaneInterface {

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected AccountProxy $currentUser;

  /**
   * The inline form manager.
   *
   * @var \Drupal\commerce\InlineFormManager
   */
  protected InlineFormManager $inlineFormManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?CheckoutFlowInterface $checkout_flow = NULL): RegistrationInformation {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition, $checkout_flow);
    $instance->currentUser = $container->get('current_user');
    $instance->inlineFormManager = $container->get('plugin.manager.commerce_inline_form');
    $instance->languageManager = $container->get('language_manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function isVisible(): bool {
    // The pane should be hidden unless there is at least one item in the cart
    // that is configured for registration.
    $handler = $this->entityTypeManager->getHandler('commerce_product_variation', 'registration_host_entity');
    foreach ($this->order->getItems() as $item) {
      /** @var \Drupal\commerce_product\Entity\ProductVariationInterface $variation */
      $variation = $item->getPurchasedEntity();
      $host_entity = $handler->createHostEntity($variation);
      if ($host_entity->isConfiguredForRegistration()) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneSummary(): array {
    $view_builder = $this->entityTypeManager->getViewBuilder('registration');

    $summary = [];
    foreach ($this->order->getItems() as $item) {
      // Find purchased items with registrations.
      if (!$item->get('registration')->isEmpty()) {
        /** @var \Drupal\commerce_product\Entity\ProductVariationInterface $variation */
        $variation = $item->getPurchasedEntity();
        $quantity = (int) $item->getQuantity();
        $caption = $this->formatPlural($quantity,
          '%label (1 registration)',
          '%label (@count registrations)', [
            '%label' => $variation->label(),
          ]);
        $summary[$variation->id()] = [
          '#markup' => '<h3 class="variation-caption">' . $caption . '</h3>',
        ];
        for ($index = 1; $index <= $quantity; $index++) {
          if ($registration = $this->getItemRegistration($item, $index)) {
            $id = $this->getPaneSubformId($variation, $index);
            if ($quantity > 1) {
              $caption = $this->t('Registrant #@index', [
                '@index' => $index,
              ]);
              $summary[$id]['caption'] = [
                '#markup' => '<div class="registration-caption">' . $caption . '</div>',
              ];
            }
            $summary[$id]['summary'] = $view_builder->view($registration, 'summary');
          }
        }
      }
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form): array {
    $handler = $this->entityTypeManager->getHandler('commerce_product_variation', 'registration_host_entity');

    // Create an inline editing subform for each registration being purchased.
    // The person checking out can provide additional information needed to
    // complete the registration.
    foreach ($this->order->getItems() as $item) {
      /** @var \Drupal\commerce_product\Entity\ProductVariationInterface $variation */
      $variation = $item->getPurchasedEntity();
      $host_entity = $handler->createHostEntity($variation);
      if ($host_entity->isConfiguredForRegistration()) {
        $quantity = (int) $item->getQuantity();
        for ($index = 1; $index <= $quantity; $index++) {
          $registration = $this->getItemRegistration($item, $index, $host_entity);
          $inline_form = $this->inlineFormManager->createInstance('registration', [
            'order_id' => $this->order->id(),
          ], $registration);
          $form_path = $this->getPaneSubformPath($variation, $index);

          $parents = $pane_form['#parents'];
          $title = $variation->label();
          if ($quantity > 1) {
            $title = $this->t('@label - Registrant #@index', [
              '@label' => $variation->label(),
              '@index' => $index,
            ]);
          }
          $reg_form = [
            'registration' => [
              '#parents' => array_merge($parents, $form_path, ['registration']),
              '#inline_form' => $inline_form,
              '#type' => 'details',
              '#open' => TRUE,
              '#title' => $title,
            ],
          ];

          $reg_form['registration'] += $inline_form->buildInlineForm($reg_form['registration'], $form_state);

          NestedArray::setValue($pane_form, $form_path, $reg_form);
        }
      }
    }

    return $pane_form;
  }

  /**
   * {@inheritdoc}
   */
  public function validatePaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    // Ensure each registration per item has a unique email address or user,
    // unless multiple registrations per person are allowed.
    $handler = $this->entityTypeManager->getHandler('commerce_product_variation', 'registration_host_entity');
    foreach ($this->order->getItems() as $item) {
      /** @var \Drupal\commerce_product\Entity\ProductVariationInterface $variation */
      $variation = $item->getPurchasedEntity();
      $host_entity = $handler->createHostEntity($variation);
      if ($host_entity->isConfiguredForRegistration()) {
        $allow_multiple = $host_entity->getSettings()->getSetting('multiple_registrations');
        if (!$allow_multiple) {
          $emails = [];
          $quantity = (int) $item->getQuantity();
          for ($index = 1; $index <= $quantity; $index++) {
            $form_path = $this->getPaneSubformPath($variation, $index);
            $variation_form = NestedArray::getValue($pane_form, $form_path);
            /** @var \Drupal\commerce\Plugin\Commerce\InlineForm\EntityInlineFormInterface $inline_form */
            $inline_form = $variation_form['registration']['#inline_form'];
            /** @var \Drupal\registration\Entity\RegistrationInterface $registration */
            // Clone the entity to avoid messing with the inline form entity.
            $registration = clone $inline_form->getEntity();
            $form_display = $this->loadFormDisplay($registration);
            $form_display->extractFormValues($registration, $variation_form, $form_state);
            $email = $this->getEmail($registration, $variation_form, $form_state);
            if (in_array($email, $emails)) {
              $form_state->setError($pane_form, $this->t('Duplicate registration for %email.', [
                '%email' => $email,
              ]));
            }
            else {
              $emails[] = $email;
            }
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $handler = $this->entityTypeManager->getHandler('commerce_product_variation', 'registration_host_entity');

    // Attach the registrations edited in the checkout pane to the order items.
    foreach ($this->order->getItems() as $item) {
      /** @var \Drupal\commerce_product\Entity\ProductVariationInterface $variation */
      $variation = $item->getPurchasedEntity();
      $host_entity = $handler->createHostEntity($variation);
      if ($host_entity->isConfiguredForRegistration()) {
        $item->set('registration', NULL);
        $quantity = (int) $item->getQuantity();
        for ($index = 1; $index <= $quantity; $index++) {
          $form_path = $this->getPaneSubformPath($variation, $index);
          $variation_form = NestedArray::getValue($pane_form, $form_path);
          /** @var \Drupal\commerce\Plugin\Commerce\InlineForm\EntityInlineFormInterface $inline_form */
          $inline_form = $variation_form['registration']['#inline_form'];
          /** @var \Drupal\registration\Entity\RegistrationInterface $registration */
          $registration = $inline_form->getEntity();
          $this->ensureRegistrationEmail($registration);
          $item->get('registration')->appendItem(['target_id' => $registration->id()]);
          $item->save();
        }
      }
    }
  }

  /**
   * Gets a registration for the given order item and index.
   *
   * If a registration is not found on the order item for the requested
   * index, and a host entity is provided, a new registration will be
   * created and returned.
   *
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $item
   *   The order item.
   * @param int $index
   *   The index.
   * @param \Drupal\registration\HostEntityInterface|null $host_entity
   *   (optional) The host entity used to create a new registration.
   *
   * @return \Drupal\registration\Entity\RegistrationInterface|null
   *   The registration, if available.
   */
  protected function getItemRegistration(OrderItemInterface $item, int $index, ?HostEntityInterface $host_entity = NULL): ?RegistrationInterface {
    $registration = NULL;
    if (!$item->get('registration')->isEmpty()) {
      $referenced_entities = $item->get('registration')->referencedEntities();
      $i = 0;
      foreach ($referenced_entities as $entity) {
        $i++;
        if ($i == $index) {
          $registration = $entity;
          break;
        }
      }
    }

    // Create a new registration if needed.
    if (!$registration && $host_entity) {
      $registration = $host_entity->createRegistration();
      $registration->set('author_uid', $this->currentUser->id());
      // Set the current language to record which language was used to register.
      // This is better than using the content language since a translation
      // might not be available for the host entity yet.
      $registration->set('langcode', $this->languageManager->getCurrentLanguage()->getId());
    }

    return $registration;
  }

  /**
   * Gets the pane subform ID for a given product variation and index.
   *
   * This ID is used to differentiate between different registrations being
   * edited at the same time in different inline forms of the same checkout
   * pane.
   *
   * @param \Drupal\commerce_product\Entity\ProductVariationInterface $variation
   *   The product variation.
   * @param int $index
   *   The index.
   *
   * @return string
   *   The ID.
   */
  protected function getPaneSubformId(ProductVariationInterface $variation, int $index): string {
    return 'variation-' . $variation->id() . '-' . $index;
  }

  /**
   * Gets the pane subform path for a given product variation and index.
   *
   * @param \Drupal\commerce_product\Entity\ProductVariationInterface $variation
   *   The product variation.
   * @param int $index
   *   The index.
   *
   * @return array
   *   The path to the subform.
   */
  protected function getPaneSubformPath(ProductVariationInterface $variation, int $index): array {
    return [$this->getPaneSubformId($variation, $index), 'registration'];
  }

  /**
   * Loads the form display used to build the registration form.
   *
   * @param \Drupal\registration\Entity\RegistrationInterface $registration
   *   The registration.
   *
   * @return \Drupal\Core\Entity\Display\EntityFormDisplayInterface
   *   The form display.
   */
  protected function loadFormDisplay(RegistrationInterface $registration): EntityFormDisplayInterface {
    return EntityFormDisplay::collectRenderDisplay($registration, 'checkout');
  }

  /**
   * Gets the email address for a registration.
   *
   * The computed email field is not available yet because the registration is
   * still unsaved at this point.
   *
   * @param \Drupal\registration\Entity\RegistrationInterface $registration
   *   The registration.
   * @param array $inline_form
   *   The inline form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return string
   *   The email address.
   */
  protected function getEmail(RegistrationInterface $registration, array $inline_form, FormStateInterface $form_state): string {
    $parents = array_merge($inline_form['#parents'], ['who_is_registering']);
    if ($form_state->getValue($parents) == RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_ME) {
      return $this->currentUser->getEmail();
    }
    if ($form_state->getValue($parents) == RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_USER) {
      if ($user = $registration->getUser()) {
        return $user->getEmail();
      }
    }
    return $registration->getAnonymousEmail();
  }

  /**
   * Ensure an email address is assigned for anonymous registrations.
   *
   * @param \Drupal\registration\Entity\RegistrationInterface $registration
   *   The registration.
   */
  protected function ensureRegistrationEmail(RegistrationInterface $registration) {
    if (!$registration->getEmail() && !$registration->getUser() && !$registration->getAnonymousEmail()) {
      if ($this->order->getCustomer()->isAnonymous()) {
        $registration->set('anon_mail', $this->order->getEmail());
        $registration->save();
      }
    }
  }

}
