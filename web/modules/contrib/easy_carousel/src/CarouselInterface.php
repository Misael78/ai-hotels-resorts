<?php

declare(strict_types=1);

namespace Drupal\easy_carousel;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a carousel entity type.
 */
interface CarouselInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

  /**
   * Get the carousel items.
   *
   * @return array
   *   An array of carousel items.
   */
  public function getItems(): array;
}
