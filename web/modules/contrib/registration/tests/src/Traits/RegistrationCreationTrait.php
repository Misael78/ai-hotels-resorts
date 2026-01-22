<?php

namespace Drupal\Tests\registration\Traits;

use Drupal\node\NodeInterface;
use Drupal\registration\Entity\Registration;
use Drupal\registration\Entity\RegistrationInterface;

/**
 * Defines a trait for creating a test registration and saving it.
 */
trait RegistrationCreationTrait {

  /**
   * Creates a registration for a given node host entity.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   *
   * @return \Drupal\registration\Entity\RegistrationInterface
   *   The created (unsaved) registration.
   */
  protected function createRegistration(NodeInterface $node): RegistrationInterface {
    return Registration::create([
      'workflow' => 'registration',
      'state' => 'pending',
      'type' => 'conference',
      'entity_type_id' => 'node',
      'entity_id' => $node->id(),
    ]);
  }

  /**
   * Creates a registration for a given node host entity and saves it.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   *
   * @return \Drupal\registration\Entity\RegistrationInterface
   *   The created and saved registration.
   */
  protected function createAndSaveRegistration(NodeInterface $node): RegistrationInterface {
    $registration = $this->createRegistration($node);
    $registration->save();
    return $registration;
  }

}
