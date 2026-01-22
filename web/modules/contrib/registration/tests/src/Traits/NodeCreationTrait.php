<?php

namespace Drupal\Tests\registration\Traits;

use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Defines a trait for creating a test node and saving it.
 */
trait NodeCreationTrait {

  /**
   * Creates a node that is configured for registration.
   *
   * @return \Drupal\node\NodeInterface
   *   The created (unsaved) node.
   */
  protected function createNode(): NodeInterface {
    return Node::create([
      'type' => 'event',
      'title' => 'My event',
      'event_registration' => 'conference',
    ]);
  }

  /**
   * Creates a node that is configured for registration and saves it.
   *
   * @return \Drupal\node\NodeInterface
   *   The created and saved node.
   */
  protected function createAndSaveNode(): NodeInterface {
    $node = $this->createNode();
    $node->save();
    return $node;
  }

}
