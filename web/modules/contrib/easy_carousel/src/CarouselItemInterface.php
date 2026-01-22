<?php

declare(strict_types=1);

namespace Drupal\easy_carousel;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a carousel item entity type.
 */
interface CarouselItemInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

  /**
   * Gets the carousel item status.
   *
   * @return bool
   *   true if the item is enabled, otherwise false
   */
  public function getStatus(): bool;

  /**
   * Get the background color of the carousel item.
   *
   * @return string
   *   The background color in hexadecimal format.
   */
  public function getBackgroundColor(): string;

  /**
   * Get the background opacity of the carousel item.
   *
   * @return float
   *   Opacity value between 0 and 1.
   */
  public function getBackgroundOpacity(): float;

  /**
   * Get title color.
   *
   * @return string
   *   The title color in hexadecimal format.
   */
  public function getTitleColor(): string;

  /**
   * Get description color.
   *
   * @return string
   *   The description color in hexadecimal format.
   */
  public function getDescriptionColor(): string;

  /**
   * Get the text alignment.
   *
   * @return string
   *   The text alignment, either 'left', 'center', 'justify', or 'right'.
   */
  public function getTextAlignment(): string;

  /**
   * Get the text position.
   *
   * @return string
   *   The text position, either 'flex-start', 'center', or 'flex-end'.
   */
  public function getTextPosition(): string;

  /**
   * Get if the title should be shown or not.
   *
   * @return bool
   *   true/false.
   */
  public function getShowTitle(): bool;

  /**
   * Get item title..
   *
   * @return string|null
   *   The title of the item.
   */
  public function getTitle():? string;

  /**
   * Get the link of the item.
   *
   * @return array|null
   *   The link of the item or NULL. If the item has a link, an array will be
   *   returned with the following keys:
   *  - uri: The URI of the link.
   *  - title: The title of the link.
   *  - target: The target of the link ('_blank', '_self', etc).
   */
  public function getLink():? array;

  /**
   * Get item description.
   *
   * @return string|null
   *   The description of the item or null if it doesn't have.
   */
  public function getDescription():? string;

  /**
   * Get the media of the item.
   *
   * @return array|null
   *   The media of the item or NULL. If the item has media, an array will be
   *   returned with the following keys:
   *   - bundle: The type of the media ('image', 'video', 'external_image').
   *   - uri: The URI of the media.
   *   - alt: The alt text of the media.
   *   - mimeType: The mime type of the media.
   */
  public function getMedia():? array;

  /**
   * Get the image in base64 format.
   *
   * @return string|null
   *   The image in base64 format or NULL.
   */
  public function getBase64Image():? string;

  /**
   * Get the external image.
   *
   * @return array|null
   *  The external image or NULL. If the item has an external image, an array
   *  will be returned with the following keys:
   *  - url: The URL of the image.
   *  - title: The title of the image.
   */
  public function getExternalImage():? array;

  /**
   * Get the video options.
   *
   * @return string
   *   The video options that will be passed to the video uri.
   */
  public function getVideoOptions(): string;
}
