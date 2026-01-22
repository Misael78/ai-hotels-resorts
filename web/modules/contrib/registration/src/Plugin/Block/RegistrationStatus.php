<?php

namespace Drupal\registration\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Utility\Token;
use Drupal\registration\HostEntityInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a registration status block.
 *
 * @Block(
 *   id = "registration_status",
 *   category = @Translation("Registration"),
 *   deriver = "Drupal\registration\Plugin\Derivative\RegistrationStatusBlock",
 * )
 */
class RegistrationStatus extends BlockBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * List of states this block supports.
   *
   * This list is an associative array key by state identifier, with a value of
   * another associative array containing "label" which is a string used in the
   * block form and "callback" which contains a callback to evaluate if the
   * state is true.
   *
   * @var array[]
   */
  protected array $states;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected Token $token;

  /**
   * Constructs a new RegistrationStatusBlock.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, Token $token) {
    /*
     * Set before the parent constructor so that default configuration will be
     * able to be defined based on our states.
     */
    $this->setStates();
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->token = $token;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('token')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    // Configuration to format remaining spaces message.
    $default_config = [
      'remaining_spaces_single' => $this->t('There is 1 space remaining.'),
      'remaining_spaces_plural' => $this->t('There are @count spaces remaining.'),
    ];

    // Configuration for the block content of each state.
    $default_format = filter_default_format();
    foreach ($this->getStateIds() as $state_id) {
      $default_config[$state_id] = ['value' => '', 'format' => $default_format];
    }

    return $default_config;
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form['remaining_spaces_single'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Remaining spaces singular'),
      '#description' => $this->t('The string for the singular case. Used in token [host_entity:formatted_spaces_remaining] when 1 space is remaining.'),
      '#default_value' => $this->configuration['remaining_spaces_single'],
    ];

    $form['remaining_spaces_plural'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Remaining spaces plural'),
      '#description' => $this->t('The string for the plural case. Use @count in place of the item count. Used in token [host_entity:formatted_spaces_remaining] when multiple spaces are remaining.'),
      '#default_value' => $this->configuration['remaining_spaces_plural'],
    ];

    $form['token'] = [
      '#theme' => 'token_tree_link',
      '#token_types' => ['node', 'registration_settings'],
    ];

    foreach ($this->states as $state_id => $state) {
      $form[$state_id] = [
        '#type' => 'text_format',
        '#title' => $state['label'],
        '#default_value' => $this->configuration[$state_id]['value'],
        '#format' => $this->configuration[$state_id]['format'],
        '#description' => $this->t('Additional available variables are: [host_entity:formatted_spaces_remaining]'),
      ];

    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['remaining_spaces_single'] = $form_state->getValue('remaining_spaces_single');
    $this->configuration['remaining_spaces_plural'] = $form_state->getValue('remaining_spaces_plural');
    foreach ($this->getStateIds() as $state_id) {
      $this->configuration[$state_id] = $form_state->getValue($state_id);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $host_entity = $this->getHostEntity();
    if (!$host_entity) {
      return [];
    }

    $state_id = $this->getHostEntityState($host_entity);
    $block_content = $this->configuration[$state_id]['value'];

    // Support tokens for the entity context and registration settings entity.
    $tokens = [
      $host_entity->getEntityTypeId() => $host_entity->getEntity(),
      'registration_settings' => $host_entity->getSettings(),
    ];

    // Token callback to dynamically replace the spaces remaining based on the
    // configured single/plural labels for the count.
    $additional_token_callback = [$this, 'blockTokens'];
    $token_cache_metadata = new BubbleableMetadata();
    $token_cache_metadata->setCacheContexts(['host_entity']);
    $block_content = $this->token->replace(
      $block_content,
      $tokens,
      ['callback' => $additional_token_callback, 'clear' => TRUE],
      $token_cache_metadata
    );

    $build = [
      '#type' => 'processed_text',
      '#text' => $block_content,
      '#format' => $this->configuration[$state_id]['format'],
    ];

    // Cache metadata from the host entity dependency.
    $build_cache_metadata = CacheableMetadata::createFromObject($host_entity);

    // The block states depend on registration availability, apply its
    // cacheability so the block rebuilds when availability changes.
    $validation_result = $host_entity->isAvailableForRegistration(TRUE);
    $build_cache_metadata->addCacheableDependency($validation_result);

    // Merge cache metadata from the build and the token replacements.
    $token_cache_metadata->merge($build_cache_metadata)->applyTo($build);

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return ['host_entity'];
  }

  /**
   * Token callback to add replacements for host entity remaining count.
   *
   * This function is used by \Drupal\Core\Utility\Token::replace() to set up
   * some additional tokens that can be used in this block based on the labels
   * for single and plural counts of the remaining spaces.
   *
   * @param array $replacements
   *   An associative array variable containing mappings from token names to
   *   values (for use with strtr()).
   * @param array $data
   *   An associative array of token replacement values.
   * @param array $options
   *   A keyed array of settings and flags to control the token replacement
   *   process. See \Drupal\Core\Utility\Token::replace().
   */
  public function blockTokens(array &$replacements, array $data, array $options): void {
    $host_entity = $this->getHostEntity();
    if (!$host_entity) {
      return;
    }

    $remaining = $host_entity->getSpacesRemaining();
    // If the registration allows unlimited capacity the spaces remaining token
    // is not applicable.
    if ($remaining === NULL) {
      $replacements['[host_entity:formatted_spaces_remaining]'] = '';
      return;
    }

    // Format based on the block configuration.
    $formatted_spaces_remaining = $this->formatPlural(
      $remaining,
      $this->configuration['remaining_spaces_single'],
      $this->configuration['remaining_spaces_plural']
    );

    $replacements['[host_entity:formatted_spaces_remaining]'] = $formatted_spaces_remaining;
  }

  /**
   * Set the states.
   */
  protected function setStates(): void {
    $this->states = [
      'enabled' => [
        'label' => $this->t('Enabled'),
        'callback' => function (HostEntityInterface $hostEntity) {
          return $hostEntity->isAvailableForRegistration();
        },
      ],
      'disabled_before_open' => [
        'label' => $this->t('Disabled - before open date'),
        'callback' => function (HostEntityInterface $hostEntity) {
          return $hostEntity->isBeforeOpen();
        },
      ],
      'disabled_after_close' => [
        'label' => $this->t('Disabled - after close date'),
        'callback' => function (HostEntityInterface $hostEntity) {
          return $hostEntity->isAfterClose();
        },
      ],
      'disabled_capacity' => [
        'label' => $this->t('Disabled - at capacity'),
        'callback' => function (HostEntityInterface $hostEntity) {
          return $hostEntity->getSpacesRemaining() === 0;
        },
      ],
      'disabled' => [
        'label' => $this->t('Disabled'),
        'callback' => function (HostEntityInterface $hostEntity) {
          return !$hostEntity->isAvailableForRegistration();
        },
      ],
    ];
  }

  /**
   * Get the state identifiers.
   *
   * @return string[]
   *   List of state identifiers.
   */
  protected function getStateIds(): array {
    return array_keys($this->states);
  }

  /**
   * Get the host entity from the block's entity context.
   *
   * @return \Drupal\registration\HostEntityInterface|null
   *   The host entity. If no registration is configured for the entity provided
   *   by the context then NULL is returned.
   */
  protected function getHostEntity(): ?HostEntityInterface {
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $this->getContextValue('entity');
    /** @var \Drupal\registration\RegistrationHostEntityHandlerInterface $host_entity_handler */
    $host_entity_handler = $this->entityTypeManager->getHandler($entity->getEntityTypeId(), 'registration_host_entity');
    $host_entity = $host_entity_handler->createHostEntity($entity);

    // If registrations are only available on specific bundles, the context will
    // provide the entity which is converted into a host entity - this may not
    // be applicable for registration.
    return $host_entity->isConfiguredForRegistration() ? $host_entity : NULL;
  }

  /**
   * Get the current state of the host entity.
   *
   * @param \Drupal\registration\HostEntityInterface $host_entity
   *   The host entity.
   *
   * @return string
   *   The host entity state identifier.
   */
  protected function getHostEntityState(HostEntityInterface $host_entity): string {
    foreach ($this->states as $state_id => $state) {
      $callback = $state['callback'];
      if (call_user_func($callback, $host_entity)) {
        return $state_id;
      }
    }

    // At least 1 condition should evaluate as the enabled and disabled negate
    // the same condition.
    throw new \LogicException(sprintf('Host entity state not determined for %s.', $host_entity->id()));
  }

}
