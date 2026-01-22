<?php

declare(strict_types=1);

namespace Drupal\easy_carousel\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a brands carousel block.
 *
 * @Block(
 *   id = "brands_carousel_block",
 *   admin_label = @Translation("Easy Carousel - Brands Carousel"),
 *   category = @Translation("Easy Carousel Blocks")
 * )
 */
final class BrandsCarouselBlock extends CarouselBaseBlock {

  /**
   * {@inheritdoc}
   */
  public function getType(): string {
    return 'brands';
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return parent::defaultConfiguration() + [
      'speed' => 0.5,
      'slide_width' => 150,
      'margin_between_slides' => 10,
      'overlayed_content' => FALSE,
      'direction' => 'rtl',
      'stop_on_hover' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {

    $form['overlayed_content'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Overlayed content'),
      '#default_value' => $this->configuration['overlayed_content'],
    ];

    $form['stop_on_hover'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Stop animation when hover'),
      '#default_value' => $this->configuration['stop_on_hover'],
    ];

    $form['speed'] = [
      '#type' => 'number',
      '#required' => TRUE,
      '#title' => $this->t('Carousel speed'),
      '#default_value' => $this->configuration['speed'],
      '#step' => '0.1',
      '#min' => '0',
    ];

    $form['slide_width'] = [
      '#type' => 'number',
      '#required' => TRUE,
      '#title' => $this->t('Slide width'),
      '#default_value' => $this->configuration['slide_width'],
      '#step' => '1.0',
      '#min' => '0',
    ];

    $form['margin_between_slides'] = [
      '#type' => 'number',
      '#required' => TRUE,
      '#title' => $this->t('Margin between slides'),
      '#default_value' => $this->configuration['margin_between_slides'],
      '#step' => '5.0',
      '#min' => '0',
    ];

    $form['direction'] = [
      '#type' => 'select',
      '#title' => $this->t('Direction'),
      '#options' => [
        'rtl' => $this->t('Right to left'),
        'ltr' => $this->t('Left to right')
      ],
      '#default_value' => $this->getConfiguration()['direction'] ?? 'rtl',
    ];

    return parent::blockForm($form, $form_state) + $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    parent::blockSubmit($form, $form_state);
    $this->configuration['speed'] = $form_state->getValue('speed');
    $this->configuration['slide_width'] = $form_state->getValue('slide_width');
    $this->configuration['margin_between_slides'] = $form_state->getValue('margin_between_slides');
    $this->configuration['overlayed_content'] = $form_state->getValue('overlayed_content');
    $this->configuration['direction'] = $form_state->getValue('direction');
    $this->configuration['stop_on_hover'] = $form_state->getValue('stop_on_hover');
  }
}
