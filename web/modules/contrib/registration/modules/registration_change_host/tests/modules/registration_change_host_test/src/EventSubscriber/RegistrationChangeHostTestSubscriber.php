<?php

namespace Drupal\registration_change_host_test\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\registration_change_host\Event\RegistrationChangeHostEvents;
use Drupal\registration_change_host\Event\RegistrationChangeHostPossibleHostsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides a registration change host test event subscriber.
 */
class RegistrationChangeHostTestSubscriber implements EventSubscriberInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new RegistrationChangeHostTestSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      RegistrationChangeHostEvents::REGISTRATION_CHANGE_HOST_POSSIBLE_HOSTS => 'addPossibleHosts',
    ];
  }

  /**
   * Add possible hosts.
   *
   * @param \Drupal\registration_change_host\Event\RegistrationChangeHostPossibleHostsEvent $event
   *   The registration change host event.
   */
  public function addPossibleHosts(RegistrationChangeHostPossibleHostsEvent $event) {
    $storage = $this->entityTypeManager->getStorage('node');
    $query = $storage->getQuery()->accessCheck(FALSE);
    $ids = $query->execute();
    $set = $event->getPossibleHostsSet();
    foreach ($ids as $id) {
      /** @var \Drupal\node\NodeInterface $node */
      $node = $storage->load($id);

      $possible_host = $set->buildNewPossibleHost($node);
      if ($code = $node->get('host_violation')->value) {
        // NONE is an invented special code used here to test removing
        // all violations.
        if ($code === 'NONE') {
          $result = $possible_host->isAvailable(TRUE);
          foreach ($result->getViolations() as $violation) {
            $result->removeViolationWithCode($violation->getCode());
          }
        }
        else {
          $possible_host->isAvailable(TRUE)->addViolation("Some validation problem $code", [], NULL, NULL, NULL, $code, "Cause $code");
        }
      }
      $possible_host->setLabel($node->get('host_label')->value);
      $possible_host->setDescription($node->get('host_description')->value);
      $possible = $node->get('host_possible')->value;
      if ($possible === NULL || $possible === 'always') {
        $set->addHost($possible_host);
      }
      elseif ($possible === 'if_available') {
        $set->addHostIfAvailable($possible_host);
      }
    }

    $set->addCacheContexts(['user.node_grants:registration_change_host_test']);
    $set->addCacheTags(['node_list']);
  }

}
