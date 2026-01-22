<?php

namespace Drupal\registration\Event;

/**
 * Events fired by the Registration module.
 */
final class RegistrationEvents {

  /**
   * Name of the event fired to allow alter of the registration count.
   *
   * This is the number of registrations, not spaces reserved.
   *
   * @Event
   *
   * @see \Drupal\registration\Event\RegistrationDataAlterEvent
   */
  const REGISTRATION_ALTER_COUNT = 'registration.alter.count';

  /**
   * Name of the event fired to allow alter of registration enabled status.
   *
   * The standard check looks at the status flag, open and close
   * dates, and whether there is still room for new registrations
   * based on the capacity setting. Use this to apply your own logic.
   *
   * @Event
   *
   * @deprecated in registration:3.4.0 and is removed from registration:4.0.0.
   *   Use REGISTRATION_ALTER_VALIDATION_RESULT instead.
   *
   * @see https://www.drupal.org/node/3496339
   */
  const REGISTRATION_ALTER_ENABLED = 'registration.alter.enabled';

  /**
   * Name of the event fired to allow alter of registration email.
   *
   * The data altered is an array of message parameters.
   *
   * @Event
   *
   * @see \Drupal\registration\Event\RegistrationDataAlterEvent
   * @see \Drupal\registration\Notify\RegistrationMailer
   */
  const REGISTRATION_ALTER_MAIL = 'registration.alter.mail';

  /**
   * Name of the event fired to allow alter of email recipients.
   *
   * The recipient list is an associative array indexed by email address.
   * See the mailer interface file for a description of this structure.
   *
   * @Event
   *
   * @see \Drupal\registration\Event\RegistrationDataAlterEvent
   * @see \Drupal\registration\Notify\RegistrationMailerInterface
   */
  const REGISTRATION_ALTER_RECIPIENTS = 'registration.alter.recipients';

  /**
   * Name of the event fired to allow alter of spaces remaining.
   *
   * This is the capacity minus the number of spaces currently reserved.
   *
   * @Event
   *
   * @see \Drupal\registration\Event\RegistrationDataAlterEvent
   */
  const REGISTRATION_ALTER_SPACES_REMAINING = 'registration.alter.remaining';

  /**
   * Name of the event fired to allow alter of registration usage.
   *
   * This is the number of spaces currently reserved.
   *
   * @Event
   *
   * @see \Drupal\registration\Event\RegistrationDataAlterEvent
   */
  const REGISTRATION_ALTER_USAGE = 'registration.alter.usage';

  /**
   * Name of the event fired to validate host entities against any value.
   *
   * @Event
   *
   * @see \Drupal\registration\Event\RegistrationDataAlterEvent
   */
  const REGISTRATION_ALTER_HOST_VALIDATION = 'registration.alter.host.validate';

  /**
   * Name of the event fired to allow alter of registration validation results.
   *
   * @Event
   *
   * @see \Drupal\registration\Event\RegistrationDataAlterEvent
   */
  const REGISTRATION_ALTER_VALIDATION_RESULT = 'registration.alter.validation_result';

  /**
   * Name of the event fired to allow alter of the registration form.
   *
   * @Event
   *
   * @see \Drupal\registration\Event\RegistrationFormEvent
   */
  const REGISTRATION_ALTER_REGISTER_FORM = 'registration.alter.register_form';

  /**
   * Name of the event fired when handling registration logging.
   *
   * @Event
   *
   * @see \Drupal\registration\Event\RegistrationSaveEvent
   */
  const REGISTRATION_SAVE_LOG = 'registration.save.log';

  /**
   * Name of the event fired when handling registration confirmation.
   *
   * @Event
   *
   * @see \Drupal\registration\Event\RegistrationSaveEvent
   */
  const REGISTRATION_SAVE_CONFIRMATION = 'registration.save.confirmation';

  /**
   * Name of the event fired after loading a registration.
   *
   * @Event
   *
   * @see \Drupal\registration\Event\RegistrationEvent
   */
  const REGISTRATION_LOAD = 'registration.registration.load';

  /**
   * Name of the event fired after creating a new registration.
   *
   * Fired before the registration is saved.
   *
   * @Event
   *
   * @see \Drupal\registration\Event\RegistrationEvent
   */
  const REGISTRATION_CREATE = 'registration.registration.create';

  /**
   * Name of the event fired before saving a registration.
   *
   * @Event
   *
   * @see \Drupal\registration\Event\RegistrationEvent
   */
  const REGISTRATION_PRESAVE = 'registration.registration.presave';

  /**
   * Name of the event fired after saving a new registration.
   *
   * @Event
   *
   * @see \Drupal\registration\Event\RegistrationEvent
   */
  const REGISTRATION_INSERT = 'registration.registration.insert';

  /**
   * Name of the event fired after saving an existing registration.
   *
   * @Event
   *
   * @see \Drupal\registration\Event\RegistrationEvent
   */
  const REGISTRATION_UPDATE = 'registration.registration.update';

  /**
   * Name of the event fired before deleting a registration.
   *
   * @Event
   *
   * @see \Drupal\registration\Event\RegistrationEvent
   */
  const REGISTRATION_PREDELETE = 'registration.registration.predelete';

  /**
   * Name of the event fired after deleting a registration.
   *
   * @Event
   *
   * @see \Drupal\registration\Event\RegistrationEvent
   */
  const REGISTRATION_DELETE = 'registration.registration.delete';

  /**
   * Name of the event fired to allow alter of sync fields.
   *
   * This event is only fired for multilingual installations.
   *
   * @Event
   *
   * @see \Drupal\registration\Event\RegistrationDataAlterEvent
   */
  const REGISTRATION_SETTINGS_ALTER_SYNC_FIELDS = 'registration.registration_settings.alter.sync_fields';

  /**
   * Name of the event fired after loading registration settings.
   *
   * @Event
   *
   * @see \Drupal\registration\Event\RegistrationSettingsEvent
   */
  const REGISTRATION_SETTINGS_LOAD = 'registration.registration_settings.load';

  /**
   * Name of the event fired after creating new registration settings.
   *
   * Fired before the registration setting is saved.
   *
   * @Event
   *
   * @see \Drupal\registration\Event\RegistrationSettingsEvent
   */
  const REGISTRATION_SETTINGS_CREATE = 'registration.registration_settings.create';

  /**
   * Name of the event fired before saving registration settings.
   *
   * @Event
   *
   * @see \Drupal\registration\Event\RegistrationSettingsEvent
   */
  const REGISTRATION_SETTINGS_PRESAVE = 'registration.registration_settings.presave';

  /**
   * Name of the event fired after saving new registration settings.
   *
   * @Event
   *
   * @see \Drupal\registration\Event\RegistrationSettingsEvent
   */
  const REGISTRATION_SETTINGS_INSERT = 'registration.registration_settings.insert';

  /**
   * Name of the event fired after saving existing registration settings.
   *
   * @Event
   *
   * @see \Drupal\registration\Event\RegistrationSettingsEvent
   */
  const REGISTRATION_SETTINGS_UPDATE = 'registration.registration_settings.update';

  /**
   * Name of the event fired before deleting registration settings.
   *
   * @Event
   *
   * @see \Drupal\registration\Event\RegistrationSettingsEvent
   */
  const REGISTRATION_SETTINGS_PREDELETE = 'registration.registration_settings.predelete';

  /**
   * Name of the event fired after deleting registration settings.
   *
   * @Event
   *
   * @see \Drupal\registration\Event\RegistrationSettingsEvent
   */
  const REGISTRATION_SETTINGS_DELETE = 'registration.registration_settings.delete';

  /**
   * Name of the event fired when registration opens.
   *
   * Requires the registration_scheduled_action submodule to be enabled,
   * the "Dispatch registration open event" to be scheduled, and a properly
   * configured Cron task that runs at least once an hour.
   *
   * @Event
   *
   * @see https://www.drupal.org/node/3506953
   * @see \Drupal\registration\Event\RegistrationSettingsEvent
   * @see \Drupal\registration_scheduled_action\Plugin\Action\DispatchEventOnOpen
   */
  const REGISTRATION_SETTINGS_OPEN = 'registration.registration_settings.open';

  /**
   * Name of the event fired when registration closes.
   *
   * Requires the registration_scheduled_action submodule to be enabled,
   * the "Dispatch registration close event" to be scheduled, and a properly
   * configured Cron task that runs at least once an hour.
   *
   * @Event
   *
   * @see https://www.drupal.org/node/3506953
   * @see \Drupal\registration\Event\RegistrationSettingsEvent
   * @see \Drupal\registration_scheduled_action\Plugin\Action\DispatchEventOnClose
   */
  const REGISTRATION_SETTINGS_CLOSE = 'registration.registration_settings.close';

}
