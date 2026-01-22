<?php

declare(strict_types=1);

namespace Drupal\easy_carousel;

use Drupal\easy_carousel\Services\VideoUtils;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension.
 */
final class EasyCarouselTwigExtension extends AbstractExtension {

  /**
   * Video Utils.
   *
   * @var \Drupal\easy_carousel\Services\VideoUtils
   */
  protected $videoUtils;

  /**
   * EasyCarouselTwigExtension constructor.
   *
   * @param \Drupal\easy_carousel\Services\VideoUtils $videoUtils
   *   VideoUtils service.
   */
  public function __construct(VideoUtils $videoUtils) {
    $this->videoUtils = $videoUtils;
  }

  /**
   * {@inheritdoc}
   */
  public function getFunctions(): array {
    return [
      new TwigFunction('embed_url', $this->videoUtils->getEmbedUrlFromVideo(...)),
    ];
  }

}
