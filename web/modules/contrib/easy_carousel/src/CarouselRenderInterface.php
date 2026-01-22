<?php

namespace Drupal\easy_carousel;

use Drupal\easy_carousel\CarouselInterface;


interface CarouselRenderInterface {

  /**
   * Get the carousel type.
   *
   * @return string
   *   The carousel type, e.g. "simple", "bootstrap", "brands", etc.
   */
  public function getType(): string;

  /**
   * Build the carousel render array.
   *
   * @param CarouselInterface $carousel
   *   The Carousel to render.
   * @param array $configuration
   *   The block configuration.
   *
   * @return array
   *   The renderable array.
   */
  public function getRenderArray(CarouselInterface $carousel, array $configuration): array;

  /**
   * Get unique id for the block/carousel.
   *
   * Same carousel could be rendered in multiple blocks, so this function ensure
   * that the configuration exists for the carousel with the same id.
   *
   * @param CarouselInterface $carousel
   *   The carousel entity.
   * @param array $configuration
   *   The block configuration.
   *
   * @return string
   */
  public function getUniqueId(CarouselInterface $carousel, array $configuration): string;

  /**
   * Get the values of the carousel object.
   *
   * @param CarouselInterface $carousel
   *  The carousel item entity.
   *
   * @param array $configuration
   *  The block configuration.
   *
   * @return array
   *   The raw values.
   */
  public function getAllValues(CarouselInterface $carousel, array $configuration): array;

}
