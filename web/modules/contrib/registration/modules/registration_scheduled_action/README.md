CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Configuration
 * Cron
 * Extension


INTRODUCTION
------------

Registration Scheduled Action is a small but powerful submodule of Registration allowing you to schedule actions at intervals relative to various dates contained in your site. The module comes with a sample plugin that sends emails to event registrants based on your registration settings close dates. For example, you can schedule a reminder email to be sent two days before registration closes, and a follow up email sent a week after registration closes. Since the actions are relative to whatever dates exist in your database, it is more powerful than the reminder feature found in the base Registration module, which requires you to specify a separate reminder date for each event. In addition, the plugin architecture allows you to create your own actions based on date fields that exist in other entities.


CONFIGURATION
-------------

To configure your scheduled actions, enable the module and then visit the administration page using the *Registration schedule* menu option in the Structure menu. Choose the Add button and configure your action. When using the sample *Email host entity registrants* plugin, the fields for email Subject and Message will appear once you choose the Action from the select box. The "When" field is relative to the date field the plugin targets. This is the registrations settings close date for the sample plugin.

For multilingual sites, you can specify the target language so that registrants using different languages to register can receive emails translated into their language, for example.

Scheduled actions are configuration entities that should be exported so they are retained during deployments.

CRON
-------------

Please note that the use of this module requires that you have a properly configured cron job executing at least once per day. If you configure your actions to use hours or minutes in the "When" field, cron will need to run every hour or every minute if you want your actions to run on time.

EXTENSION
-------------

Many sites will have a date field on their events that is separate from the registration settings close date. You can create your own plugin in a custom module to schedule actions relative to this date field. Copy the sample plugin (`src/Plugin/Action/EmailRegistrantsAction.php`) to your custom module in a similar path, rename it and modify the particulars as needed. Note that creating a custom action plugin of this type requires the ability to use the Drupal database API and have strong expertise in Drupal module development.
