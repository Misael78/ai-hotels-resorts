<?php

namespace Drupal\feedback_ai\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a custom form block.
 *
 * @Block(
 *   id = "feedback_ai_chart_block",
 *   admin_label = @Translation("Feedback AI Chart"),
 *   category = @Translation("Feedback AI Chart Block"),
 * )
 */
class SentimentChartBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The form builder instance.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Connection $database) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {

    // Fetch the data points from the database or any other source.
    $feedbacks = $this->getFeedbackData();
    $dataPoints = $this->prepareDataPoints($feedbacks);

    if (empty($feedbacks)) {
      return [
        '#markup' => '<div class="no-data-message">No Sentiment Result Available</div>',
        '#attached' => [
          'library' => [
            'feedback_ai/feedback_ai_css',
          ],
        ],
      ];
    }

    /* Render the chart container and attach the necessary
    libraries and settings.*/
    return [
      '#theme' => 'sentiment_chart',
      '#attached' => [
        'library' => [
          'feedback_ai/feedback_ai_canvas',
          'feedback_ai/feedback_ai_charts',
        ],
        'drupalSettings' => [
          'FeedbackAi' => [
            'dataPoints' => $dataPoints,
          ],
        ],
      ],
    ];
  }

  /**
   * Fetches the feedback data from the database.
   */
  private function getFeedbackData() {
    $database = $this->database;
    $query = $database->select('feedback_ai', 'sf')
      ->fields('sf', ['sentiment_text', 'sentiment_result', 'created'])
      ->orderBy('created', 'DESC')
      ->range(0, 10);
    return $query->execute()->fetchAll();
  }

  /**
   * Prepares data points for the chart.
   */
  private function prepareDataPoints($feedbacks) {
    $positive = 0;
    $neutral = 0;
    $negative = 0;

    foreach ($feedbacks as $feedback) {
      if ($feedback->sentiment_result === 'Positive') {
        $positive++;
      }
      elseif ($feedback->sentiment_result === 'Neutral') {
        $neutral++;
      }
      elseif ($feedback->sentiment_result === 'Negative') {
        $negative++;
      }
    }

    $total = $positive + $neutral + $negative;

    if ($total > 0) {
      $positive_percentage = ($positive / $total) * 100;
      $neutral_percentage = ($neutral / $total) * 100;
      $negative_percentage = ($negative / $total) * 100;
    }
    else {
      $positive_percentage = 0;
      $neutral_percentage = 0;
      $negative_percentage = 0;
    }

    return [
      ['label' => 'Positive', 'y' => $positive_percentage],
      ['label' => 'Neutral', 'y' => $neutral_percentage],
      ['label' => 'Negative', 'y' => $negative_percentage],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }

}
