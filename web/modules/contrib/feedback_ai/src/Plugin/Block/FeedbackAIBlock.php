<?php

namespace Drupal\feedback_ai\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a custom form block.
 *
 * @Block(
 *   id = "feedback_ai_block",
 *   admin_label = @Translation("Feedback AI Form"),
 *   category = @Translation("Custom")
 * )
 */
class FeedbackAIBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The form builder instance.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FormBuilderInterface $form_builder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $feedback_ai_block = $this->formBuilder->getForm('Drupal\feedback_ai\Form\FeedbackAIForm');

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['feedback-ai-block']],
      '#title' => $this->t('Feedback AI Form'),
      '#title_display' => 'before',
      'content' => $feedback_ai_block,
      '#theme' => 'feedback_ai_block',
    ];
  }

}
