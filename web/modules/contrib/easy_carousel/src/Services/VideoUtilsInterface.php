<?php

declare(strict_types=1);

namespace Drupal\easy_carousel\Services;

interface VideoUtilsInterface {

  /**
   * Convert Youtube or Vimeo url to a valid embed url.
   *
   * @param string $videoUrl
   *   The video url.
   *
   * @param string $videoOptions
   *   The video options.
   *
   * @return string
   *   The equivalent url or the same url if the url is not a Youtube/Vimeo
   *   url.
   */
  public function getEmbedUrlFromVideo(string $videoUrl, ?string $videoOptions): string;

  /**
   * Check if a url is a Youtube url.
   *
   * @param string $url
   *   The url.
   *
   * @return bool
   *   true/false.
   */
  public function isYoutubeVideo(string $url): bool;

  /**
   * Check if a url is a Vimeo url.
   *
   * @param string $url
   *   The url.
   *
   * @return bool
   *   true/false.
   */
  public function isVimeoVideo(string $url): bool;

  /**
   * Get the Youtube video ID from youtube url.
   *
   * @param string $videoUrl
   *   Youtube url.
   *
   * @return string|null
   *   Video ID or null if is not a valid Youtube URL.
   */
  public function getYoutubeVideoId(string $videoUrl): ?string;

  /**
   * Get the Vimeo video ID from youtube url.
   *
   * @param string $videoUrl
   *   Vimeo url.
   *
   * @return string|null
   *   Video ID or null if is not a valid Vimeo URL.
   */
  public function getVimeoVideoId(string $url): ?string;
}
