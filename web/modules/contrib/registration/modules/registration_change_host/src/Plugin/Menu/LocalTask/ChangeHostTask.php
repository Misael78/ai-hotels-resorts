<?php

namespace Drupal\registration_change_host\Plugin\Menu\LocalTask;

use Drupal\Core\Menu\LocalTaskDefault;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\registration\Entity\RegistrationInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines a local task plugin for changing registration host.
 *
 * @phpcs:disable Drupal.Semantics.FunctionT.NotLiteralString
 */
class ChangeHostTask extends LocalTaskDefault {

  /**
   * {@inheritdoc}
   */
  public function getTitle(?Request $request = NULL, ?RegistrationInterface $registration = NULL): ?TranslatableMarkup {
    if ($registration) {
      $config = \Drupal::config('registration_change_host.settings');
      return new TranslatableMarkup($config->get('task_title'), [
        '@host_type_label' => $registration->getHostEntityTypeLabel(),
        '%id' => $registration->id(),
      ]);
    }
    return NULL;
  }

}
