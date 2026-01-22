<?php

declare(strict_types=1);

namespace Drupal\easy_carousel\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a simple carousel block.
 *
 * @Block(
 *   id = "simple_carousel_block",
 *   admin_label = @Translation("Easy Carousel - Simple Carousel"),
 *   category = @Translation("Easy Carousel Blocks")
 * )
 */
final class SimpleCarouselBlock extends CarouselBaseBlock {

  /**
   * {@inheritdoc}
   */
  public function getType(): string {
    return 'simple';
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return parent::defaultConfiguration() + [
      'show_controls' => TRUE,
      'show_indicators' => TRUE,
      'auto_start' => TRUE,
      'speed' => 5000,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form['show_controls'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show controls'),
      '#default_value' => $this->configuration['show_controls'],
    ];

    $form['show_indicators'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show indicators'),
      '#default_value' => $this->configuration['show_indicators'],
    ];

    $form['auto_start'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Auto start slide'),
      '#default_value' => $this->configuration['auto_start'],
    ];

    $form['speed'] = [
      '#type' => 'number',
      '#required' => TRUE,
      '#title' => $this->t('Set carousel speed (miliseconds)'),
      '#default_value' => $this->configuration['speed'],
    ];

    return parent::blockForm($form, $form_state) + $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    parent::blockSubmit($form, $form_state);
    $this->configuration['show_controls'] = $form_state->getValue('show_controls');
    $this->configuration['show_indicators'] = $form_state->getValue('show_indicators');
    $this->configuration['auto_start'] = $form_state->getValue('auto_start');
    $this->configuration['speed'] = $form_state->getValue('speed');
  }
}
