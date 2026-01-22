<?php

namespace Drupal\registration\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation of the 'registration_state' formatter.
 *
 * @FieldFormatter(
 *   id = "registration_state",
 *   label = @Translation("Registration state"),
 *   field_types = {
 *     "string",
 *   }
 * )
 */
class RegistrationStateFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    $elements[] = [
      '#markup' => $items->getEntity()->getState()->label(),
    ];
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition): bool {
    return $field_definition->getName() === 'state' && $field_definition->getTargetEntityTypeId() === 'registration';
  }

}
