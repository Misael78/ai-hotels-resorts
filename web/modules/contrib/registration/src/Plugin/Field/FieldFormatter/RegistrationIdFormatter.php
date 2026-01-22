<?php

namespace Drupal\registration\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Link;
use Drupal\registration\Entity\RegistrationInterface;

/**
 * Plugin implementation of the 'registration_id' formatter.
 *
 * Formats the registration ID as a link to the registration.
 *
 * @FieldFormatter(
 *   id = "registration_id",
 *   label = @Translation("Registration ID"),
 *   field_types = {
 *     "integer"
 *   }
 * )
 */
class RegistrationIdFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];

    $entity = $items->getEntity();
    if ($entity instanceof RegistrationInterface) {
      if ($entity->access('view')) {
        $elements[] = [
          '#markup' => Link::fromTextAndUrl($entity->id(), $entity->toUrl('canonical', [
            'language' => \Drupal::languageManager()->getLanguage($langcode),
          ]))->toString(),
        ];
      }
      else {
        $elements[] = [
          '#markup' => $entity->id(),
        ];
      }
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition): bool {
    $field_name = $field_definition->getName();
    return ($field_name == 'registration_id');
  }

}
