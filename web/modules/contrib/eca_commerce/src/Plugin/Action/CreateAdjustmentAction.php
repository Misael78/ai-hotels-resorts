<?php

namespace Drupal\eca_commerce\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\commerce_order\Adjustment;
use Drupal\commerce_order\AdjustmentTypeManager;
use Drupal\commerce_order\EntityAdjustableInterface;
use Drupal\commerce_price\Price;
use Drupal\commerce_store\Resolver\StoreResolverInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca\Plugin\ECA\PluginFormTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Describes the eca_commerce add_adjustment action.
 *
 * This allows users to add price adjustments when added to cart based on ECA.
 */
#[Action(
  id: 'eca_commerce_add_adjustment',
  label: new TranslatableMarkup('Order Item: Add Price Adjustment'),
  type: 'commerce_order_item',
)]
#[EcaAction(
  version_introduced: '1.0.0',
)]
class CreateAdjustmentAction extends ConfigurableActionBase {

  use CurrencyActionTrait;
  use PluginFormTrait;

  /**
   * The adjustment type manager.
   *
   * @var \Drupal\commerce_order\AdjustmentTypeManager|null
   */
  protected ?AdjustmentTypeManager $adjustmentTypeManager;

  /**
   * The default store resolver.
   *
   * @var \Drupal\commerce_store\Resolver\StoreResolverInterface|null
   */
  protected ?StoreResolverInterface $defaultStoreResolver;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->adjustmentTypeManager = $container->get('plugin.manager.commerce_adjustment_type', ContainerInterface::NULL_ON_INVALID_REFERENCE);
    $instance->defaultStoreResolver = $container->get('commerce_store.default_store_resolver', ContainerInterface::NULL_ON_INVALID_REFERENCE);
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $access_result = AccessResult::AllowedIf($object instanceof EntityAdjustableInterface);

    return $return_as_object ? $access_result : $access_result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(mixed $entity = NULL): void {
    if (!class_exists(Adjustment::class) || !class_exists(Price::class)) {
      // Early return.
      return;
    }

    $label = $this->tokenService->replaceClear($this->configuration['label']);
    $amount = $this->tokenService->replaceClear($this->configuration['amount']);
    $fallback_currency = $this->getFallbackCurrency($entity);
    $currency = $this->configuration['currency'] ?: $fallback_currency;
    if ($currency === '_eca_token') {
      $currency = $this->getTokenValue('currency', $fallback_currency);
    }
    $percentage = $this->tokenService->replaceClear($this->configuration['percentage']);
    $definition = [
      'type' => $this->configuration['type'],
      'label' => $label,
      'amount' => new Price($amount, $currency),
      'percentage' => $percentage ?: NULL,
      'source_id' => 'custom',
      'included' => $this->configuration['included'],
      'locked' => $this->configuration['locked'],
    ];
    $adjustment = new Adjustment($definition);
    switch ($this->configuration['method']) {
      case 'set:clear':
        $entity->setAdjustments([]);

      case 'append:drop_first':
        $entity->addAdjustment($adjustment);
        break;
    }

    if ($this->configuration['save_entity']) {
      $entity->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'method' => 'set:clear',
      'type' => '_none',
      'label' => '',
      'amount' => '',
      'currency' => '',
      'percentage' => '',
      'included' => FALSE,
      'locked' => TRUE,
      'save_entity' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['method'] = [
      '#type' => 'select',
      '#title' => $this->t('Method'),
      '#default_value' => $this->configuration['method'],
      '#description' => $this->t('The method to set an entity, like cleaning the old one, etc..'),
      '#weight' => -40,
      '#options' => [
        'set:clear' => $this->t('Set and clear previous value'),
        'append:drop_first' => $this->t('Append and drop first when full'),
      ],
    ];
    $types = [
      '_none' => $this->t('- Select -'),
    ];
    if (isset($this->adjustmentTypeManager)) {
      foreach ($this->adjustmentTypeManager->getDefinitions() as $id => $definition) {
        if (!empty($definition['has_ui'])) {
          $types[$id] = $definition['label'];
        }
      }
    }
    $form['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Type'),
      '#options' => $types,
      '#weight' => 1,
      '#default_value' => $this->configuration['type'],
      '#required' => TRUE,
    ];
    $form['locked'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Locked'),
      '#description' => $this->t('Note: Adjustments added from UI interactions need to be locked to persist after an order refresh.'),
      '#default_value' => $this->configuration['locked'],
    ];
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#size' => 20,
      '#default_value' => $this->configuration['label'],
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];
    $form['amount'] = [
      '#type' => 'number',
      '#title' => $this->t('Amount'),
      '#default_value' => $this->configuration['amount'],
      '#required' => TRUE,
      '#attributes' => ['class' => ['clearfix']],
      '#eca_token_replacement' => TRUE,
    ];
    $form['currency'] = [
      '#type' => 'select',
      '#title' => $this->t('Currency'),
      '#options' => ['_none' => 'Use default'] + $this->getAvailableCurrencies(),
      '#default_value' => $this->configuration['currency'],
      '#size' => 5,
      '#required' => TRUE,
      '#eca_token_select_option' => TRUE,
    ];
    $form['percentage'] = [
      '#type' => 'number',
      '#title' => $this->t('Percentage'),
      '#default_value' => $this->configuration['percentage'],
      '#attributes' => ['class' => ['clearfix']],
      '#eca_token_replacement' => TRUE,
    ];
    $form['included'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Included in the base price'),
      '#default_value' => $this->configuration['amount'],
    ];
    $form['save_entity'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Save entity'),
      '#default_value' => $this->configuration['save_entity'],
      '#description' => $this->t('Saves the entity or not after setting the value.'),
      '#weight' => -10,
    ];

    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['method'] = $form_state->getValue('method');
    $this->configuration['type'] = $form_state->getValue('type');
    $this->configuration['locked'] = $form_state->getValue('locked');
    $this->configuration['label'] = $form_state->getValue('label');
    $this->configuration['amount'] = $form_state->getValue('amount');
    if ($form_state->getValue('currency') === '_none') {
      $currency = '';
    }
    else {
      $currency = $form_state->getValue('currency');
    }
    $this->configuration['currency'] = $currency;
    $this->configuration['percentage'] = $form_state->getValue('percentage');
    $this->configuration['included'] = $form_state->getValue('included');
    $this->configuration['save_entity'] = $form_state->getValue('save_entity');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * Computes all valid choices for the type setting.
   *
   * @return string[]
   *   All valid choices.
   */
  public static function getValidAdjustmentTypes(): array {
    $ids = [];
    foreach (\Drupal::service('plugin.manager.commerce_adjustment_type')->getDefinitions() as $id => $definition) {
      if (!empty($definition['has_ui'])) {
        $ids[] = $id;
      }
    }
    return $ids;
  }

  /**
   * Computes all valid choices for the currency setting.
   *
   * @return string[]
   *   All valid choices.
   */
  public static function getValidCurrencies(): array {
    $currency_codes = array_keys(\Drupal::entityTypeManager()->getStorage('commerce_currency')->loadMultiple());
    return array_combine($currency_codes, $currency_codes);
  }

}
