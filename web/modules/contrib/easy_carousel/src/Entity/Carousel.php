<?php

declare(strict_types=1);

namespace Drupal\easy_carousel\Entity;

use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\RevisionableContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\easy_carousel\CarouselInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the carousel entity class.
 *
 * @ContentEntityType(
 *   id = "carousel",
 *   label = @Translation("Carousel"),
 *   label_collection = @Translation("Carousels"),
 *   label_singular = @Translation("carousel"),
 *   label_plural = @Translation("carousels"),
 *   label_count = @PluralTranslation(
 *     singular = "@count carousels",
 *     plural = "@count carousels",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\easy_carousel\CarouselListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "add" = "Drupal\easy_carousel\Form\CarouselForm",
 *       "edit" = "Drupal\easy_carousel\Form\CarouselForm",
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
 *   base_table = "carousel",
 *   data_table = "carousel_field_data",
 *   revision_table = "carousel_revision",
 *   revision_data_table = "carousel_field_revision",
 *   show_revision_ui = TRUE,
 *   translatable = TRUE,
 *   admin_permission = "administer carousel",
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
 *     "collection" = "/admin/content/carousel",
 *     "add-form" = "/carousel/add",
 *     "canonical" = "/carousel/{carousel}",
 *     "edit-form" = "/carousel/{carousel}/edit",
 *     "delete-form" = "/carousel/{carousel}/delete",
 *     "delete-multiple-form" = "/admin/content/carousel/delete-multiple",
 *     "revision" = "/carousel/{carousel}/revision/{carousel_revision}/view",
 *     "revision-delete-form" = "/carousel/{carousel}/revision/{carousel_revision}/delete",
 *     "revision-revert-form" = "/carousel/{carousel}/revision/{carousel_revision}/revert",
 *     "version-history" = "/carousel/{carousel}/revisions",
 *   },
 *   field_ui_base_route = "entity.carousel.settings",
 * )
 */
final class Carousel extends RevisionableContentEntityBase implements CarouselInterface {

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

    /*
      Valid plugin IDs for Drupal\Core\TypedData\TypedDataManager are:
        duration_iso8601,
        datetime_iso8601,
        boolean,
        binary,
        map,
        float,
        timestamp,
        uri,
        any,
        string,
        list,
        language_reference,
        language,
        integer,
        timespan,
        decimal,
        email,
        field_item:changed,
        field_item:boolean,
        field_item:created,
        field_item:entity_reference,
        field_item:email,
        field_item:uri,
        field_item:string,
        field_item:map,
        field_item:decimal,
        field_item:string_long,
        field_item:timestamp,
        field_item:password,
        field_item:integer,
        field_item:language,
        field_item:uuid,
        field_item:float,
        entity_reference,
        entity,
        entity:carousel,
        entity:carousel_item,
        entity:field_storage_config,
        entity:field_config,
        entity:media,
        entity:media_type,
        entity:date_format,
        entity:base_field_override,
        entity:entity_form_display,
        entity:entity_form_mode,
        entity:entity_view_mode,
        entity:entity_view_display
    */

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

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setRevisionable(FALSE)
      ->setLabel(t('Status'))
      ->setDefaultValue(TRUE)
      ->setSetting('on_label', 'Enabled')
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => [
          'display_label' => FALSE,
        ],
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

    // Definición del nuevo campo que referencia a CustomEntity
    $fields['items'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Carousel items'))
      ->setRequired(FALSE)
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setSetting('target_type', 'carousel_item')
      ->setSetting('handler', 'default')
      ->setTranslatable(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete', // Puedes usar un widget de autocomplete para la referencia.
      ])
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'entity_reference_entity_view', // O cualquier otro tipo de visualización que desees.
      ]);

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
      ->setDescription(t('The time that the carousel entity was created.'))
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
      ->setDescription(t('The time that the carousel entity was last edited.'));

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getItems(): array {
    return $this->get('items')->referencedEntities();
  }

}
