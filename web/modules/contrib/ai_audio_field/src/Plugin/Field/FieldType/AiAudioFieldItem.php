<?php

namespace Drupal\ai_audio_field\Plugin\Field\FieldType;

use Drupal\file\Plugin\Field\FieldType\FileFieldItemList;

/**
 * Represents a configurable ai audio file field.
 */
class AiAudioFieldItem extends FileFieldItemList {

  /**
   * {@inheritdoc}
   */
  public function getConstraints() {
    return [];
  }

}
