<?php

declare(strict_types=1);

namespace Drupal\easy_carousel\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a gallery carousel block.
 *
 * @Block(
 *   id = "gallery_carousel_block",
 *   admin_label = @Translation("Easy Carousel - Gallery"),
 *   category = @Translation("Easy Carousel Blocks")
 * )
 */
final class GalleryCarouselBlock extends CarouselBaseBlock {

  /**
   * {@inheritdoc}
   */
  public function getType(): string {
    return 'gallery';
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return parent::defaultConfiguration() + [
      'carousel_height' => 600,
      'thumbnail_width' => 150,
      'auto_start' => TRUE,
      'speed' => 5000,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {

    $form['carousel_height'] = [
      '#type' => 'number',
      '#required' => TRUE,
      '#title' => $this->t('Carousel height'),
      '#default_value' => $this->configuration['carousel_height'],
      '#step' => '10',
      '#min' => '0',
    ];

    $form['thumbnail_width'] = [
      '#type' => 'number',
      '#required' => TRUE,
      '#title' => $this->t('Thumnails width'),
      '#default_value' => $this->configuration['thumbnail_width'],
      '#step' => '10',
      '#min' => '0',
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
    $this->configuration['carousel_height'] = $form_state->getValue('carousel_height');
    $this->configuration['thumbnail_width'] = $form_state->getValue('thumbnail_width');
    $this->configuration['auto_start'] = $form_state->getValue('auto_start');
    $this->configuration['speed'] = $form_state->getValue('speed');
  }
}
