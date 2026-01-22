<?php

declare(strict_types=1);

namespace Drupal\easy_carousel\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\easy_carousel\Entity\Carousel;
use Drupal\easy_carousel\CarouselInterface;
use Drupal\easy_carousel\CarouselRenderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a carousel base block.
 */
abstract class CarouselBaseBlock extends BlockBase implements ContainerFactoryPluginInterface, CarouselRenderInterface {

  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs the plugin instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'selected_carousel' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    return [
      'selected_carousel' => [
        '#type' => 'entity_autocomplete',
        '#title' => $this->t('Select the carousel to display'),
        '#required' => TRUE,
        '#target_type' => 'carousel',
        '#tags' => TRUE,
        '#default_value' => $this->getSelectedCarousel() ?? [],
        '#selection_handler' => 'default',
      ]
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['selected_carousel'] = $form_state->getValue('selected_carousel');
    $this->configuration['last_updated'] = time();
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $carousel = $this->getSelectedCarousel();
    if ($carousel) {
      $configuration = $this->configuration;
      $build = $this->getRenderArray($carousel, $configuration);
      return $build;
    }
    else {
      $build['carousel'] = [
        '#markup' => $this->t('No carousel found.'),
      ];
    }
    return $build;
  }

  /**
   * Get the stored Carousel saved in the configuration.
   *
   * @return Carousel|null
   *   The carousel entity or null if not exists.
   */
  public function getSelectedCarousel():? Carousel {
    $id = $this->configuration['selected_carousel'][0]['target_id'] ?? NULL;
    if ($id) {
      $carousel = $this->entityTypeManager->getStorage('carousel')->load($id);
      return $carousel;
    }
    return NULL;
  }

  /** @inheritDoc */
  public function getUniqueId(CarouselInterface $carousel, array $configuration): string {
    return $carousel->id() . '_' . $configuration['last_updated'];
  }

  /** @inheritDoc */
  public function getRenderArray(CarouselInterface $carousel, array $configuration): array {
    $blockType = $this->getType();
    $theme = 'carousel_' . $blockType;
    $library = 'easy_carousel/' . $blockType;
    $carouselValues = $this->getAllValues($carousel, $configuration);
    return [
      '#theme' => $theme,
      '#carousel' => $carousel,
      '#slides' => $carousel->getItems(),
      '#configuration' => $configuration,
      '#data' => $carouselValues,
      '#attached' => [
        'library' => [ $library ],
        'drupalSettings' => [
          'easy_carousel' => [
            $carouselValues['id'] => [
              'config' => $configuration,
            ]
          ]
        ]
      ]
    ];
  }

    /**
   * @inheritDoc
   */
  public function getAllValues(CarouselInterface $carousel, array $configuration): array {

    $values = [
      'id' => $this->getUniqueId($carousel, $configuration),
      'status' => $carousel->get('status')->value == 1,
      'cid' => $carousel->id(),
      'label' => $carousel->label(),
      'config' => $configuration,
      'items' => [],
    ];

    foreach($carousel->getItems() as $item) {
      $values['items'][] = [
        'id' => $item->id(),
        'label' => $item->label(),
        'status' => $item->getStatus(),
        'media' => $item->getMedia(),
        'base64_image' => $item->getBase64Image(),
        'external_image' => $item->getExternalImage(),
        'background_color' => $this->opacityToHexValue($item->getBackgroundColor(), $item->getBackgroundOpacity()),
        'opacity' => $item->getBackgroundOpacity(),
        'title_color' => $item->getTitleColor(),
        'description_color' => $item->getDescriptionColor(),
        'text_alignment' => $item->getTextAlignment(),
        'text_position' => $item->getTextPosition(),
        'show_title' => $item->getShowTitle(),
        'title' => $item->getTitle(),
        'link' => $item->getLink(),
        'description' => $item->getDescription(),
      ];
    }

    // Clone all block configuration values.
    foreach ($configuration as $key => $value) {
      $values['config'][$key] = $value;
    }

    return $values;
  }

  /**
   * Convert opacity in the corresponding hex value.
   *
   * @param string $color_hex
   *   Color in hexadecimal format. (ej. "#FF5733").
   * @param float $opacity
   *   Opacity, float 0-1.
   *
   * @return string
   *   The color in RGBA hexadecimal (ej. "#FF573380").
   */
  protected function opacityToHexValue(string $color_hex, float $opacity) {
    $color_hex = str_replace('#', '', $color_hex);
    $alpha = dechex((int) round($opacity * 255));
    // Ensure that alfa channel has two digits
    $alpha = str_pad($alpha, 2, '0', STR_PAD_LEFT);
    return '#' . $color_hex . $alpha;
  }


}
