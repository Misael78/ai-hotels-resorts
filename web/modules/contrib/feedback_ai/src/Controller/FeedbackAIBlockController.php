<?php

namespace Drupal\feedback_ai\Controller;

use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class FeedbackAIBlockController.
 *
 * Provides functionality to render the custom block programmatically.
 */
class FeedbackAIBlockController extends ControllerBase {

  /**
   * The block manager.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockManager;

  /**
   * Constructs a FeedbackAIBlockController object.
   *
   * @param \Drupal\Core\Block\BlockManagerInterface $block_manager
   *   The block manager.
   */
  public function __construct(BlockManagerInterface $block_manager) {
    $this->blockManager = $block_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.block')
    );
  }

  /**
   * Renders the custom block.
   *
   * @return array
   *   A render array for the custom block.
   */
  public function renderCustomBlock() {
    // Get the block plugin.
    $plugin_block = $this->blockManager->createInstance('feedback_ai_chart_block', []);

    // Ensure the block plugin is valid.
    if ($plugin_block) {
      $block_build = $plugin_block->build();

      // Optionally add a surrounding container or other renderable items.
      $output = [
        '#type' => 'container',
        '#attributes' => ['class' => ['feedback-ai-chart-container']],
        'block' => $block_build,
      ];

      return $output;
    }

    // Fallback if the block plugin is not found.
    return [
      '#markup' => $this->t('Custom block could not be rendered.'),
    ];
  }

}
