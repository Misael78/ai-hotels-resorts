<?php

namespace Drupal\commerce_registration\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Url;
use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\registration\Entity\RegistrationInterface;
use Drupal\registration\Form\EmailRegistrantsForm;
use Drupal\registration\HostEntityInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the broadcast to email registrants form for a product.
 */
class EmailProductRegistrantsForm extends EmailRegistrantsForm {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'email_product_registrants';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $product = $this->registrationManager->getEntityFromParameters($this->getRouteMatch()->getParameters());
    if (!($product instanceof ProductInterface)) {
      throw new \InvalidArgumentException("Product must be available for email to be sent.");
    }

    // Setup.
    $form = [];
    $registrant_count = 0;
    $handler = $this->entityTypeManager->getHandler('commerce_product_variation', 'registration_host_entity');
    foreach ($product->getVariations() as $variation) {
      $host_entity = $handler->createHostEntity($variation);
      if ($registration_type = $host_entity->getRegistrationType()) {
        // Only send to registrants with active registrations.
        $states = $registration_type->getActiveStates();
        if (empty($states)) {
          $message = $this->t('There are no active registration states configured. For email to be sent, an active registration state must be specified for the @type registration type.', [
            '@type' => $registration_type->label(),
          ]);
          $this->messenger()->addError($message);
          return $form;
        }

        $registrants = $this->registrationMailer->getRecipientList($host_entity);
        $registrant_count += count($registrants);
      }
    }

    // If no registrants yet then take an early exit.
    if ($registrant_count == 0) {
      $form['notice'] = [
        '#markup' => $this->t('There are no registrants for %name', [
          '%name' => $product->label(),
        ]),
      ];
      return $form;
    }

    // Check if doing a preview or not.
    $values = $form_state->getValues();
    $triggering_element = $form_state->getTriggeringElement() ?? ['#id' => 'edit-submit'];
    $preview = ($triggering_element['#id'] == 'edit-preview');

    if ($preview) {
      // In preview mode, display subject and message with tokens replaced
      // so the user can see what the resulting subject and message will be.
      $form['subject_preview'] = [
        '#type' => 'item',
        '#title' => $this->t('Subject'),
      ];
      $form['message_preview'] = [
        '#type' => 'item',
        '#title' => $this->t('Message'),
      ];

      // Use a sample registration for token replacement.
      $host_entity = $this->getHostEntity($form_state);
      $registration = $host_entity->generateSampleRegistration();

      // Replace tokens in Subject.
      $subject = Html::escape($values['subject']);
      if (!empty($values['variation_id']) && is_numeric($values['variation_id'])) {
        $this->replaceTokens($form['subject_preview'], $host_entity, $registration, $subject);
      }
      else {
        $this->replaceProductTokens($form['subject_preview'], $product, $registration, $subject);
      }

      // Replace tokens in Message.
      $build = [
        '#type' => 'processed_text',
        '#text' => $values['message']['value'],
        '#format' => $values['message']['format'],
      ];
      if (version_compare(\Drupal::VERSION, '10.3', '>=')) {
        $message = $this->renderer->renderInIsolation($build);
      }
      else {
        // @phpstan-ignore-next-line
        $message = $this->renderer->renderPlain($build);
      }

      if (!empty($values['variation_id']) && is_numeric($values['variation_id'])) {
        $this->replaceTokens($form['message_preview'], $host_entity, $registration, $message);
      }
      else {
        $this->replaceProductTokens($form['message_preview'], $product, $registration, $message);
      }

      // Hidden fields for the next submit.
      $form['variation_id'] = [
        '#type' => 'hidden',
        '#value' => $values['variation_id'],
      ];
      $form['subject'] = [
        '#type' => 'hidden',
        '#value' => $values['subject'],
      ];
      $form['message'] = [
        '#type' => 'hidden',
        '#value' => $values['message'],
      ];
    }
    else {
      // Not in preview mode, do a standard form build.
      $form['variation_id'] = [
        '#title' => $this->t('Recipients'),
        '#type' => 'commerce_registration_entity_select',
        '#required' => TRUE,
        '#all' => $this->t('All Registrants'),
        '#target_type' => 'commerce_product_variation',
        '#selection_handler' => 'commerce_registration_variation',
        '#selection_settings' => [
          'product_id' => $product->id(),
        ],
        '#autocomplete_threshold' => 20,
        '#autocomplete_size' => 40,
        '#default_value' => $values['variation_id'] ?? '',
        '#description' => $this->t('The email message will be sent to all registrants for this item.'),
      ];
      $form['subject'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Subject'),
        '#required' => TRUE,
        '#default_value' => $values['subject'] ?? '',
      ];
      $description = $this->t('Enter the message you want to send to registrants for %label. Tokens are supported, e.g., [node:title].', [
        '%label' => $product->label(),
      ]);
      $form['message'] = [
        '#type' => 'text_format',
        '#title' => $this->t('Message'),
        '#required' => TRUE,
        '#description' => $description,
        '#default_value' => $values['message']['value'] ?? '',
        '#format' => $values['message']['format'] ?? filter_default_format(),
      ];
      if ($this->moduleHandler->moduleExists('token')) {
        $form['token_tree'] = [
          '#theme' => 'token_tree_link',
          '#token_types' => [
            'commerce_product',
            'commerce_product_variation',
            'commerce_order',
            'registration',
            'registration_settings',
          ],
          '#global_types' => FALSE,
          '#weight' => 10,
        ];
      }
    }

    // Send button that will kick off the emails.
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send'),
      '#button_type' => 'primary',
    ];

    if ($preview) {
      // In preview mode already, provide a button to re-edit the message.
      $form['actions']['message'] = [
        '#type' => 'submit',
        '#value' => $this->t('Edit message'),
        '#button_type' => 'secondary',
      ];
    }
    else {
      // Not in preview mode, provide a button to do a preview.
      $form['actions']['preview'] = [
        '#type' => 'submit',
        '#value' => $this->t('Preview'),
        '#button_type' => 'secondary',
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $triggering_element = $form_state->getTriggeringElement();
    if ($triggering_element['#id'] == 'edit-submit') {
      // The Send button was submitted. Fire off the emails.
      $variation_id = $form_state->getValue('variation_id');
      if (is_numeric($variation_id)) {
        // Send emails to registrants for a single product variation.
        $host_entity = $this->getHostEntity($form_state);
        $registration_type = $host_entity->getRegistrationType();
        $states = $registration_type->getActiveStates();
        $values['states'] = array_keys($states);
        $values['mail_tag'] = 'broadcast';
        $success_count = $this->registrationMailer->notify($host_entity, $values);
        $message = $this->formatPlural($success_count,
         'Registration broadcast sent to 1 recipient.',
         'Registration broadcast sent to @count recipients.',
        );
        $this->messenger()->addStatus($message);
      }
      else {
        // Send emails to registrants for all variations of a product.
        $success_count = 0;
        $handler = $this->entityTypeManager->getHandler('commerce_product_variation', 'registration_host_entity');
        $product = $this->registrationManager->getEntityFromParameters($this->getRouteMatch()->getParameters());
        $values['token_entities'] = [
          'commerce_product' => $product,
        ];
        foreach ($product->getVariations() as $variation) {
          $host_entity = $handler->createHostEntity($variation);
          if ($host_entity->isConfiguredForRegistration()) {
            $registration_type = $host_entity->getRegistrationType();
            $states = $registration_type->getActiveStates();
            $values['states'] = array_keys($states);
            $success_count += $this->registrationMailer->notify($host_entity, $values);
          }
        }
        $message = $this->formatPlural($success_count,
         'Registration broadcast sent to 1 recipient.',
         'Registration broadcast sent to @count recipients.',
        );
        $this->messenger()->addStatus($message);
      }

      // Redirect to the Manage Registrations tab for the product.
      // Build the parameters to the route dynamically since they
      // are only known at run time based on the host entity type.
      // As an example, the route for a commerce product variation
      // includes both the product ID and the product variation ID.
      // We cannot assume the route parameters should only be based
      // on the host entity type and host entity ID, as for nodes.
      $parameters = $this->getRouteMatch()->getRawParameters()->all();

      // Set the redirect URL.
      $url = Url::fromRoute("entity.commerce_product.commerce_registration.manage_registrations", $parameters);
      $form_state->setRedirectUrl($url);
    }
    else {
      // Either the Preview or Edit message button was submitted.
      $form_state->setRebuild();
    }
  }

  /**
   * Gets the host entity.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\registration\HostEntityInterface|null
   *   The host entity.
   */
  protected function getHostEntity(FormStateInterface $form_state): ?HostEntityInterface {
    $host_entity = NULL;
    $handler = $this->entityTypeManager->getHandler('commerce_product_variation', 'registration_host_entity');
    $variation_id = $form_state->getValue('variation_id');
    if (is_numeric($variation_id)) {
      $variation = $this->entityTypeManager->getStorage('commerce_product_variation')->load($variation_id);
      if ($variation) {
        $variation_host_entity = $handler->createHostEntity($variation);
        if ($variation_host_entity->isConfiguredForRegistration()) {
          $host_entity = $variation_host_entity;
        }
      }
    }
    else {
      $product = $this->registrationManager->getEntityFromParameters($this->getRouteMatch()->getParameters());
      foreach ($product->getVariations() as $variation) {
        $variation_host_entity = $handler->createHostEntity($variation);
        if ($variation_host_entity->isConfiguredForRegistration()) {
          $host_entity = $variation_host_entity;
          break;
        }
      }
    }
    return $host_entity;
  }

  /**
   * Replaces tokens in a string and puts the result into a render element.
   *
   * Modifies the render element with bubbleable metadata and #markup set.
   *
   * @param array $element
   *   The render element.
   * @param \Drupal\commerce_product\Entity\ProductInterface $product
   *   The product.
   * @param \Drupal\registration\Entity\RegistrationInterface $registration
   *   The registration entity.
   * @param string $input
   *   The input string with tokens.
   */
  protected function replaceProductTokens(array &$element, ProductInterface $product, RegistrationInterface $registration, string $input) {
    $entities = [
      'commerce_product' => $product,
      'registration' => $registration,
    ];
    $bubbleable_metadata = new BubbleableMetadata();
    $element['#markup'] = $this->token->replace($input, $entities, [], $bubbleable_metadata);
    $bubbleable_metadata->applyTo($element);
  }

}
