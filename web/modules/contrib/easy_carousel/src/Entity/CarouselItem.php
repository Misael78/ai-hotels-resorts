<?php

declare(strict_types=1);

namespace Drupal\easy_carousel\Entity;

use Drupal\Core\Url;
use Drupal\user\EntityOwnerTrait;
use Drupal\link\LinkItemInterface;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\easy_carousel\CarouselItemInterface;
use Drupal\Core\Entity\RevisionableContentEntityBase;

/**
 * Defines the carousel item entity class.
 *
 * @ContentEntityType(
 *   id = "carousel_item",
 *   label = @Translation("Carousel Slide"),
 *   label_collection = @Translation("Carousel Slides"),
 *   label_singular = @Translation("carousel slide"),
 *   label_plural = @Translation("carousel slide"),
 *   label_count = @PluralTranslation(
 *     singular = "@count carousel slide",
 *     plural = "@count carousel slides",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\easy_carousel\CarouselItemListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "add" = "Drupal\easy_carousel\Form\CarouselItemForm",
 *       "edit" = "Drupal\easy_carousel\Form\CarouselItemForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *       "delete-multiple-confirm" = "Drupal\Core\Entity\Form\DeleteMultipleForm",
 *       "revision-delete" = \Drupal\Core\Entity\Form\RevisionDeleteForm::class,
 *       "revision-revert" = \Drupal\Core\Entity\Form\RevisionRevertForm::class,
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *       "revision" = \Drupal\Core\Entity\Routing\RevisionHtmlRouteProvider::class,
 *     },
 *   },
 *   base_table = "carousel_item",
 *   data_table = "carousel_item_field_data",
 *   revision_table = "carousel_item_revision",
 *   revision_data_table = "carousel_item_field_revision",
 *   show_revision_ui = TRUE,
 *   translatable = TRUE,
 *   admin_permission = "administer carousel_item",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "revision_id",
 *     "langcode" = "langcode",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "owner" = "uid",
 *   },
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_uid",
 *     "revision_created" = "revision_timestamp",
 *     "revision_log_message" = "revision_log",
 *   },
 *   links = {
 *     "collection" = "/admin/content/carousel-item",
 *     "add-form" = "/carousel-item/add",
 *     "canonical" = "/carousel-item/{carousel_item}",
 *     "edit-form" = "/carousel-item/{carousel_item}/edit",
 *     "delete-form" = "/carousel-item/{carousel_item}/delete",
 *     "delete-multiple-form" = "/admin/content/carousel-item/delete-multiple",
 *     "revision" = "/carousel-item/{carousel_item}/revision/{carousel_item_revision}/view",
 *     "revision-delete-form" = "/carousel-item/{carousel_item}/revision/{carousel_item_revision}/delete",
 *     "revision-revert-form" = "/carousel-item/{carousel_item}/revision/{carousel_item_revision}/revert",
 *     "version-history" = "/carousel-item/{carousel_item}/revisions",
 *   },
 *   field_ui_base_route = "entity.carousel_item.settings",
 * )
 */
final class CarouselItem extends RevisionableContentEntityBase implements CarouselItemInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);
    if (!$this->getOwnerId()) {
      // If no owner has been set explicitly, make the anonymous user the owner.
      $this->setOwnerId(0);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {

    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setRevisionable(FALSE)
      ->setTranslatable(TRUE)
      ->setLabel(t('Title'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['media'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Media'))
      ->setRequired(FALSE)
      ->setSetting('target_type', 'media')
      ->setSetting('handler', 'default')
      ->setTranslatable(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'media_library_widget',
        'settings' => [
          'multiple' => FALSE,
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'media_thumbnail'
      ]);

    $fields['video_options'] = BaseFieldDefinition::create('string')
      ->setRevisionable(FALSE)
      ->setTranslatable(TRUE)
      ->setLabel(t('Set video options to the embeded video url'))
      ->setDescription(t('You can attach to the video url a set of options for Youtube/Vimeo videos to control the player behavior.<br/>Examples:<br/><strong>Youtube</strong> (https://developers.google.com/youtube/player_parameters):<br/>Options: ?autoplay=1&mute=1&controls=0&cc_load_policy=0&iv_load_policy=3&modestbranding=1&rel=0&fs=0&loop=1&playlist=<br/><br/><strong>Vimeo</strong> (https://developer.vimeo.com/player/sdk/embed):<br/>Options: ?mute=1&autoplay=1&controls=1'))
      ->setRequired(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', FALSE);

    $fields['base64_image'] = BaseFieldDefinition::create('string_long')
      ->setRevisionable(FALSE)
      ->setTranslatable(TRUE)
      ->setLabel(t('Base64 Image'))
      ->setDescription(t('You can add a image in a base64 format. Paste the base64 content here'))
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['external_image'] = BaseFieldDefinition::create('link')
    ->setLabel(t('External image'))
    ->setDescription(t('External image (enter the image URL)'))
    ->setRequired(FALSE)
    ->setSetting('link_type', LinkItemInterface::LINK_EXTERNAL)
    ->setSetting('title', DRUPAL_OPTIONAL)
    ->setDisplayOptions('form', [
      'type' => 'link',
    ])
    ->setDisplayConfigurable('form', TRUE)
    ->setDisplayOptions('view', [
      'type' => 'link',
      'label' => 'hidden',
    ])
    ->setDisplayConfigurable('view', TRUE);

    $fields['show_title'] = BaseFieldDefinition::create('boolean')
    ->setRevisionable(FALSE)
      ->setLabel(t('Show title in the slide'))
      ->setDefaultValue(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'type' => 'boolean',
        'label' => 'above',
        'settings' => [
          'format' => 'enabled-disabled',
        ],
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['description'] = BaseFieldDefinition::create('text_long')
      ->setRevisionable(FALSE)
      ->setTranslatable(TRUE)
      ->setLabel(t('Description'))
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'type' => 'text_default',
        'label' => 'hidden',
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['link'] = BaseFieldDefinition::create('link')
    ->setLabel(t('Link'))
    ->setDescription(t('Enter a URL for this entity.'))
    ->setRequired(FALSE)
    ->setSetting('link_type', LinkItemInterface::LINK_EXTERNAL | LinkItemInterface::LINK_INTERNAL)
    ->setSetting('title', DRUPAL_OPTIONAL)
    ->setDisplayOptions('form', [
      'type' => 'link',
    ])
    ->setDisplayConfigurable('form', TRUE)
    ->setDisplayOptions('view', [
      'type' => 'link',
      'label' => 'hidden',
    ])
    ->setDisplayConfigurable('view', TRUE);

    $fields['link_target'] = BaseFieldDefinition::create('list_string')
    ->setLabel(t('Link target'))
      ->setDescription(t('Select where the link should open.'))
      ->setRequired(FALSE)
      ->setSettings([
        'allowed_values' => [
          '_self' => t('Same window'),
          '_blank' => t('New window / tab'),
          '_parent' => t('Parent frame'),
          '_top' => t('Top frame'),
        ],
      ])
      ->setDefaultValue('_blank')
      ->setDisplayOptions('form', [
        'type' => 'options_select',
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['item_background'] = BaseFieldDefinition::create('string')
      ->setRevisionable(FALSE)
      ->setTranslatable(TRUE)
      ->setLabel(t('Background color (hex notation ie #ffffff)'))
      ->setDefaultValue('#000000')
      ->setRequired(FALSE)
      ->setSetting('max_length', 10)
      ->setDisplayOptions('form', ['type' => 'color_widget'])
      ->setDisplayConfigurable('form', TRUE);

    $fields['background_opacity'] = BaseFieldDefinition::create('float')
      ->setRevisionable(FALSE)
      ->setTranslatable(FALSE)
      ->setLabel(t('Background opacity'))
      ->setDefaultValue(0.7)
      ->setRequired(FALSE)
      ->setDisplayOptions('form', ['type' => 'number'])
      ->setDisplayConfigurable('form', TRUE);

    $fields['title_color'] = BaseFieldDefinition::create('string')
      ->setRevisionable(FALSE)
      ->setTranslatable(TRUE)
      ->setLabel(t('Title color (hex notation ie #ffffff)'))
      ->setDefaultValue('#ffffff')
      ->setRequired(FALSE)
      ->setSetting('max_length', 10)
      ->setDisplayOptions('form', ['type' => 'color_widget'])
      ->setDisplayConfigurable('form', TRUE);

    $fields['description_color'] = BaseFieldDefinition::create('string')
      ->setRevisionable(FALSE)
      ->setTranslatable(TRUE)
      ->setLabel(t('Description color (hex notation ie #ffffff)'))
      ->setDefaultValue('#ffffff')
      ->setRequired(FALSE)
      ->setSetting('max_length', 10)
      ->setDisplayOptions('form', ['type' => 'color_widget'])
      ->setDisplayConfigurable('form', TRUE);

    $fields['text_alignment'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Text alignment'))
      ->setDescription(t('Select the text alignment'))
      ->setRequired(FALSE)
      ->setSettings([
        'allowed_values' => [
          'left' => t('Left'),
          'center' => t('Center'),
          'right' => t('Right'),
          'justify' => t('Justified'),
        ],
      ])
      ->setDefaultValue('flex-end')
      ->setDisplayOptions('form', [
        'type' => 'options_select',
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['text_position'] = BaseFieldDefinition::create('list_string')
    ->setLabel(t('Text position (vertical alignment)'))
    ->setDescription(t('Select the text position'))
    ->setRequired(FALSE)
      ->setSettings([
        'allowed_values' => [
          'flex-start' => t('Top'),
          'center' => t('Center'),
          'flex-end' => t('Bottom'),
        ],
      ])
      ->setDefaultValue('flex-end')
      ->setDisplayOptions('form', [
        'type' => 'options_select',
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setRevisionable(FALSE)
      ->setLabel(t('Status'))
      ->setDefaultValue(TRUE)
      ->setSetting('on_label', 'Enabled')
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'type' => 'boolean',
        'label' => 'above',
        'settings' => [
          'format' => 'enabled-disabled',
        ],
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setRevisionable(FALSE)
      ->setTranslatable(TRUE)
      ->setLabel(t('Author'))
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback(self::class . '::getDefaultEntityOwner')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => 60,
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'author',
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setTranslatable(TRUE)
      ->setDescription(t('The time that the carousel item entity was created.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setTranslatable(TRUE)
      ->setDescription(t('The time that the carousel item entity was last edited.'));

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus(): bool {
    return $this->get('status')->value == 1;
  }

  /**
   * {@inheritdoc}
   */
  public function getBackgroundColor(): string {
    return $this->get('item_background')->value ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getTitleColor(): string {
    return $this->get('title_color')->value ?? '#000000';
  }

  /**
   * {@inheritdoc}
   */
  public function getDescriptionColor(): string {
    return $this->get('description_color')->value ?? '#000000';
  }

  /**
   * {@inheritdoc}
   */
  public function getBackgroundOpacity(): float {
    return $this->get('background_opacity')->value ? floatval($this->get('background_opacity')->value) : 0.0;
  }

  /**
   * {@inheritdoc}
   */
  public function getTextAlignment(): string {
    return $this->get('text_alignment')->value ?? 'center';
  }

  /**
   * {@inheritdoc}
   */
  public function getTextPosition(): string {
    return $this->get('text_position')->value ?? 'center';
  }

  /**
   * {@inheritdoc}
   */
  public function getShowTitle(): bool {
    return (bool) $this->get('show_title')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle():? string {
    return $this->get('label')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getLink():? array {
    if (empty($this->get('link')->uri)) {
      return NULL;
    }
    $uri = $this->getUriFromString($this->get('link')->uri);
    $title = $this->get('link')->title;
    $target = $this->get('link_target')->value ?? '_blank';
    return ['uri' => $uri, 'title' => $title, 'target' => $target];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription():? string {
    return $this->get('description')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getMedia():? array {
    if (empty($this->get('media')->entity)) {
      return NULL;
    }
    /** @var \Drupal\media\Entity\Media $media */
    $media = $this->get('media')->entity;
    $bundle = $media->bundle();
    $uri = '';
    $alt = '';
    $mimeType = '';
    if ($bundle === 'image') {
      $uri = $media->get('field_media_image')->entity->uri->value;
      $alt = $media->get('field_media_image')->first()->alt;
    }
    elseif ($bundle === 'video') {
      $uri = $media->get('field_media_video_file')->entity->uri->value;
      $mimeType = $media->get('field_media_video_file')->entity->getMimeType();
    }
    elseif ($bundle === 'remote_video') {
      $uri = $media->get('field_media_oembed_video')->value;
    }
    return ['bundle' => $bundle, 'uri' => $uri, 'alt' => $alt, 'mime_type' => $mimeType];
  }

  /**
   * {@inheritdoc}
   */
  public function getBase64Image():? string {
    return $this->get('base64_image')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getExternalImage():? array {
    $value = $this->get('external_image')->getValue()[0];
    return !empty($value['uri']) ? $value : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getVideoOptions(): string {
    return $this->get('video_options')->value ?? '';
  }

  /**
   * Convert the link to an url.
   *
   * @param FieldItemListInterface $urlOrRoute
   *   The link field.
   *
   * @return string
   *   The url string
   */
  function getUri(FieldItemListInterface $urlOrRoute): string {
    $uri = $urlOrRoute->getValue()[0]['uri'] ?? NULL;
    if (is_null($uri)) {
      return '';
    }
    return $this->getUriFromString($uri);
  }

  /**
   * Convert the link to an url.
   *
   * @param string $uri
   *   The uri.
   *
   * @return string
   *   The url string
   */
  function getUriFromString(string $uri): string {
    if (strpos($uri, 'entity:') === 0) {
      $current_url = Url::fromUri($uri);
      return $current_url->toString();
    } else {
      return $uri;
    }
  }
}
