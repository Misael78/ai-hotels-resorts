<?php

namespace Drupal\registration\Form;

use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for registration type deletion.
 *
 * @internal
 */
class RegistrationTypeDeleteConfirm extends EntityDeleteForm {

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->entityFieldManager = $container->get('entity_field.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    // Check for registrations of the type.
    $num_registrations = $this->entityTypeManager
      ->getStorage('registration')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', $this->entity->id())
      ->count()
      ->execute();
    if ($num_registrations) {
      $caption = '<p>' . $this->formatPlural($num_registrations, '%type is used by 1 registration on your site. You cannot delete this registration type until you have deleted that registration.', '%type is used by @count registrations on your site. You cannot delete %type until you have deleted all of the %type registrations.', ['%type' => $this->entity->label()]) . '</p>';
      $form['#title'] = $this->getQuestion();
      $form['description'] = ['#markup' => $caption];
      return $form;
    }

    // Check for entity references to the type.
    $registration_fields = $this->entityFieldManager->getFieldMapByFieldType('registration');
    foreach ($registration_fields as $entity_type_id => $fields) {
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
      $num_entities = 0;
      foreach ($fields as $field_id => $info) {
        $entities = $this->entityTypeManager
          ->getStorage($entity_type_id)
          ->loadByProperties([
            "$field_id.registration_type" => $this->entity->id(),
          ]);
        $num_entities += count($entities);
      }
      if ($num_entities) {
        $caption = '<p>' . $this->formatPlural($num_entities, '%type is used by 1 @entity_type entity on your site. You cannot delete this registration type until you have edited that entity to remove the reference to this registration type.', '%type is used by @count @entity_type entities on your site. You cannot delete %type until you have edited these @entity_type entities to remove the reference to %type.', [
          '@entity_type' => $entity_type->getLabel(),
          '%type' => $this->entity->label(),
        ]) . '</p>';
        $form['#title'] = $this->getQuestion();
        $form['description'] = ['#markup' => $caption];
        return $form;
      }
    }

    return parent::buildForm($form, $form_state);
  }

}
