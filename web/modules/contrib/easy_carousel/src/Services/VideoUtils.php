<?php

namespace Drupal\easy_carousel\Services;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

class VideoUtils implements VideoUtilsInterface {

  use StringTranslationTrait;

  /** @var LoggerChannelInterface */
  private $logger;

  /** @var EntityTypeManagerInterface */
  protected $entityTypeManager;

  /**
   * Constructs a new VideoUtils object.
   *
   * @param LoggerChannelFactoryInterface $loggerChannelFactory
   *   The logger channel factory.
   * @param EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(LoggerChannelFactoryInterface $loggerChannelFactory, EntityTypeManagerInterface $entityTypeManager) {
    $this->logger = $loggerChannelFactory->get('easy_carousel');
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * @inheritDoc
   */
  public function getEmbedUrlFromVideo(string $videoUrl, ?string $videoOptions): string {
    if ($this->isYoutubeVideo($videoUrl)) {
      $videoId = $this->getYoutubeVideoId($videoUrl);
      $ret = "https://www.youtube.com/embed/" . $videoId;
      if (!empty($videoOptions)) {
        if ($this->hasPlaylistParam($videoOptions)) {
          if ($this->isPlaylistEmpty($videoOptions)) {
            $options = str_replace('playlist=', 'playlist=' . $videoId, $videoOptions);
            $ret = $ret . $options;
          }
        }
      }
      return $ret;
    }
    else if ($this->isVimeoVideo($videoUrl)) {
      $videoId = $this->getVimeoVideoId($videoUrl);
      return "https://player.vimeo.com/video/" . $videoId;
    }
    return $videoUrl;
  }

  /**
   * @inheritDoc
   */
  public function isYoutubeVideo(string $url): bool {
    return $this->isProviderVideoUrl($url, ['www.youtube.com', 'youtube.com', 'youtu.be']);
  }

  /**
   * @inheritDoc
   */
  public function isVimeoVideo(string $url): bool {
    $vimeoPatterns = [
      "/https?:\/\/(?:www\.)?(?:vimeo\.com\/(?:video\/)?|player\.vimeo\.com\/video\/)\d+/",
      "/https?:\/\/[a-zA-Z0-9\-_]+\.vimeo\.com\/(?:video\/)?\d+/"
    ];
    return $this->isProviderVideoUrl($url, $vimeoPatterns, TRUE);
  }

  /**
   * @inheritDoc
   */
  public function getYoutubeVideoId(string $videoUrl): ?string {
    if (!$this->isYoutubeVideo($videoUrl))
      return NULL;
    $parsedUrl = parse_url($videoUrl);
    $host = $parsedUrl['host'];
    $path = $parsedUrl['path'] ?? '';
    $query = $parsedUrl['query'] ?? '';
    if (strpos($host, 'youtu.be') !== false) {
      return ltrim($path, '/');
    }
    parse_str($query, $queryParams);
    if (isset($queryParams['v'])) {
      return $queryParams['v'];
    }
    return NULL;
  }

  /**
   * @inheritDoc
   */
  public function getVimeoVideoId(string $url): ?string {
    $patterns = [
      // https://vimeo.com/12345678
      '/https?:\/\/(www\.)?vimeo\.com\/(\d+)/',
      // https://vimeo.com/channels/staffpicks/12345678
      '/https?:\/\/(www\.)?vimeo\.com\/channels\/[\w]+\/(\d+)/',
      // https://vimeo.com/groups/groupname/videos/12345678
      '/https?:\/\/(www\.)?vimeo\.com\/groups\/[\w]+\/videos\/(\d+)/',
      // https://player.vimeo.com/video/12345678
      '/https?:\/\/player\.vimeo\.com\/video\/(\d+)/'
    ];
    foreach ($patterns as $pattern) {
      if (preg_match($pattern, $url, $matches)) {
        return $matches[2] ?? $matches[1];
      }
    }
    return null;
  }

  /**
   * Check if a url is a valid provider url.
   *
   * @param string $url
   *   The url.
   * @param array $validUrlPatterns
   *   The valid provider urls.
   *
   * @return bool
   *   true/false depending if is valid or not.
   */
  private function isProviderVideoUrl(string $url, array $validUrlPatterns, bool $isRegex = FALSE): bool {
    if ($isRegex) {
      foreach ($validUrlPatterns as $pattern) {
        if (preg_match($pattern, $url)) {
          return true;
        }
      }
      return false;
    } else {
      $parsedUrl = parse_url($url);
      if (!$parsedUrl || !isset($parsedUrl['host'])) {
        return false;
      }
      return in_array($parsedUrl['host'], $validUrlPatterns);
    }
  }

  /**
   * Checks if a "playlist" param is empty or not.
   *
   * @param string $queryString
   *   The query string.
   *
   * @return bool
   *   TRUE or FALSE.
   */
  private function isPlaylistEmpty(string $queryString): bool {
    parse_str(ltrim($queryString, '?'), $params);
    return empty($params['playlist']);
  }

  /**
   * Checks if a "playlist" param exists in the query string.
   *
   * @param string $queryString
   *   The query string.
   *
   * @return bool
   *   TRUE or FALSE.
   */
  private function hasPlaylistParam(string $queryString): bool {
    parse_str(ltrim($queryString, '?'), $params);
    return isset($params['playlist']);
  }
}
