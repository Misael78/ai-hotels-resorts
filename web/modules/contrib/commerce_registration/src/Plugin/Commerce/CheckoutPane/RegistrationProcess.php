<?php

namespace Drupal\commerce_registration\Plugin\Commerce\CheckoutPane;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the registration process pane.
 *
 * Creates new registrations for the person completing checkout. This pane may
 * not be needed if the registration_information pane is enabled.
 *
 * @CommerceCheckoutPane(
 *   id = "registration_process",
 *   label = @Translation("Registration process"),
 *   default_step = "payment",
 *   wrapper_element = "container",
 * )
 */
class RegistrationProcess extends CheckoutPaneBase implements CheckoutPaneInterface {

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected AccountProxy $currentUser;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?CheckoutFlowInterface $checkout_flow = NULL): RegistrationProcess {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition, $checkout_flow);
    $instance->currentUser = $container->get('current_user');
    $instance->languageManager = $container->get('language_manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form): array {
    $handler = $this->entityTypeManager->getHandler('commerce_product_variation', 'registration_host_entity');

    foreach ($this->order->getItems() as $item) {
      $variation = $item->getPurchasedEntity();
      $host_entity = $handler->createHostEntity($variation);
      if ($host_entity->isConfiguredForRegistration() && $item->get('registration')->isEmpty()) {
        // Create and save the registration.
        $registration = $host_entity->createRegistration();
        $registration->set('order_id', $this->order->id());
        $registration->set('author_uid', $this->currentUser->id());
        $registration->set('count', (int) $item->getQuantity());

        // Set the current language to record the language used to register.
        // This is better than using the content language since a translation
        // might not be available for the host entity yet.
        $registration->set('langcode', $this->languageManager->getCurrentLanguage()->getId());

        // Set user or email address.
        if ($this->order->getCustomer()->isAuthenticated()) {
          $registration->set('user_uid', $this->order->getCustomer()->id());
        }
        else {
          $registration->set('anon_mail', $this->order->getEmail());
        }
        $registration->save();

        // Save the item.
        $item->get('registration')->appendItem(['target_id' => $registration->id()]);
        $item->save();
      }
    }

    return $pane_form;
  }

}
