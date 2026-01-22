<?php

namespace Drupal\registration_waitlist\TwigExtension;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\registration\Entity\RegistrationInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Provides the Registration Wait List extensions.
 */
class RegistrationWaitListTwigExtension extends AbstractExtension {

  /**
   * {@inheritdoc}
   */
  public function getFilters(): array {
    return [
      // phpcs:ignore
      new TwigFilter('host_entity_waitlist_indicator', [$this, 'hostEntityWaitListIndicator']),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return 'registration_waitlist.twig_extension';
  }

  /**
   * Renders an indicator if a registration will add to a waiting list.
   *
   * The indicator renders if the host entity has a waiting list and the
   * next registration will add to it.
   *
   * Example: {{ entity|host_entity_waitlist_indicator }}
   *
   * @param mixed $entity
   *   The host entity.
   * @param int $spaces
   *   (optional) The number of spaces being checked. Defaults to 1.
   * @param mixed|null $registration
   *   (optional) An existing registration to check, if available.
   *
   * @return array
   *   A renderable array with a waiting list indicator, or an empty array
   *   if the indicator is not applicable.
   *
   * @throws \InvalidArgumentException
   */
  public static function hostEntityWaitListIndicator(mixed $entity, int $spaces = 1, mixed $registration = NULL): array {
    $build = [];
    if (empty($entity)) {
      // Nothing to render.
      return $build;
    }
    if (!($entity instanceof ContentEntityInterface)) {
      throw new \InvalidArgumentException('The "host_entity_waitlist_indicator" filter must be given a content entity as the host entity.');
    }
    if ($registration && !($registration instanceof RegistrationInterface)) {
      throw new \InvalidArgumentException('The "host_entity_waitlist_indicator" filter must be given a valid registration entity.');
    }

    $handler = \Drupal::entityTypeManager()->getHandler($entity->getEntityTypeId(), 'registration_host_entity');
    $host_entity = $handler->createHostEntity($entity);
    $validation_result = $host_entity->isAvailableForRegistration(TRUE);
    if ($validation_result->isValid()) {
      if ($host_entity->shouldAddToWaitList($spaces, $registration)) {
        $build = [
          '#theme' => 'host_entity_waitlist_indicator',
        ];
      }
    }
    $validation_result->getCacheableMetadata()->applyTo($build);
    return $build;
  }

}
