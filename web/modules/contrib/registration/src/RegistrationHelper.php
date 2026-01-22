<?php

namespace Drupal\registration;

use Drupal\registration\Entity\RegistrationInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Header\MailboxHeader;

/**
 * Defines a utility class.
 */
class RegistrationHelper {

  /**
   * Updates an array of registration links with current language.
   *
   * Used on entity operations and form action links, since both have the
   * same structure. Note the interface language is being used here, since
   * registration maintenance can be considered an admin task.
   *
   * @param array $links
   *   The links to update.
   */
  public static function applyInterfaceLanguageToLinks(array &$links) {
    foreach ($links as $index => &$link) {
      switch ($index) {
        case 'edit':
        case 'delete':
          $url_key = !empty($link['#type']) ? '#url' : 'url';
          if (!empty($link[$url_key])) {
            $options = $link[$url_key]->getOptions();
            if (isset($options['language'])) {
              $interface_language = \Drupal::languageManager()->getCurrentLanguage();
              if ($options['language']->getId() != $interface_language->getId()) {
                $options['language'] = $interface_language;
                $link[$url_key]->setOptions($options);
              }
            }
          }
          break;
      }
    }
  }

  /**
   * Expands a settings array.
   *
   * @param array $settings
   *   The input settings array.
   *
   * @return array
   *   An expanded array.
   */
  public static function expand(array $settings): array {
    $result = [];
    foreach ($settings as $field => $value) {
      if (!is_array($value)) {
        $value = [0 => ['value' => $value]];
      }
      $result[$field] = $value;
    }
    return $result;
  }

  /**
   * Extracts a host entity and registration, if possible, from a variable.
   *
   * @param mixed $value
   *   The variable.
   *
   * @return array
   *   An array containing two elements, a host entity and a registration.
   *   Either or both of the returned elements may be null.
   */
  public static function extractEntitiesFromValue(mixed $value): array {
    $host_entity = NULL;
    $registration = NULL;

    if ($value instanceof HostEntityInterface) {
      $host_entity = $value;
    }
    elseif ($value instanceof RegistrationInterface) {
      $registration = $value;
      $host_entity = $registration->getHostEntity();
    }

    return [$host_entity, $registration];
  }

  /**
   * Flattens a settings array.
   *
   * @param array $settings
   *   The input settings array.
   *
   * @return array
   *   The flattened array.
   */
  public static function flatten(array $settings): array {
    $result = [];
    foreach ($settings as $field => $value) {
      if (is_array($value) && (count($value) == 1)) {
        if (array_key_exists('value', $value)) {
          $value = $value['value'];
        }
        elseif (isset($value[0], $value[0]['value'])) {
          if (count($value[0]) == 1) {
            // The "value" element is the only one, return it.
            $value = $value[0]['value'];
          }
          else {
            // This is likely a text field with both a value and a format. Need
            // to return the array containing both.
            $value = $value[0];
          }
        }
      }
      $result[$field] = $value;
    }
    return $result;
  }

  /**
   * Gets a mailbox header.
   *
   * Currently, only the "From" header is supported.
   *
   * @param string $header_name
   *   The header to get, currently must be "From".
   * @param string|null $header_input
   *   Any input that should be used to calculate the returned header.
   *   For the "From" header, pass the "from address" in registration settings.
   *
   * @return string|null
   *   The derived header, if available.
   */
  public static function getMailboxHeader(string $header_name, ?string $header_input = NULL): ?string {
    $header = NULL;

    if ($header_name == 'From') {
      // Default to using the from address from registration settings.
      $header = $from_address = $header_input;

      // Check if the from address is a simple email address, or is already in
      // the form of a "From" header, e.g. "My Site <email@example.org>".
      if ($from_address && (!str_contains($from_address, '<'))) {
        // Convert a simple email address to a header using the site name.
        if ($site_name = \Drupal::config('system.site')->get('name')) {
          $mailbox = new MailboxHeader('From', new Address($from_address, $site_name));
          $header = $mailbox->getBodyAsString();
        }
      }
    }

    return $header;
  }

}
