<?php

namespace Drupal\registration;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Defines the list builder for registrations.
 */
class RegistrationListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['id'] = $this->t('ID');
    $header['type'] = $this->t('Type');
    $header['user'] = $this->t('User');
    $header['spaces'] = $this->t('Spaces');
    $header['host'] = $this->t('Host');
    $header['status'] = $this->t('Status');
    $header['updated'] = $this->t('Updated');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\registration\Entity\RegistrationInterface $entity */
    if ($user = $entity->getUser()) {
      $user = Link::fromTextAndUrl(
        $user->getDisplayName(),
        $user->toUrl()
      );
    }
    else {
      $user = $entity->getAnonymousEmail();
    }

    // Get the attached column value.
    if ($host_entity = $entity->getHostEntity()) {
      $host_entity = Link::fromTextAndUrl(
        $host_entity->label(),
        $host_entity
          ->getEntity()
          ->toUrl()
        );
    }

    $row['id'] = Link::fromTextAndUrl($entity->id(), $entity->toUrl());
    $row['type'] = $entity->getType()->label();
    $row['user'] = $user;
    $row['spaces'] = $entity->getSpacesReserved();
    $row['host'] = $host_entity;
    $row['status'] = $entity->getState()->label();
    $row['updated'] = \Drupal::service('date.formatter')
      ->format($entity->getChangedTime(), 'short');

    return $row + parent::buildRow($entity);
  }

}
