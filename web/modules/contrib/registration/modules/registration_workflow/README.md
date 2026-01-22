CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Configuration


INTRODUCTION
------------

Registration Workflow is a submodule of Registration that adds permissions and operations for workflow transitions.


CONFIGURATION
-------------

After enabling this module, permissions in the "Registration Workflow" section of the Permissions page should be set for the appropriate roles.

By default, users need update access to the registration, plus the relevant permission for the transition, to access a transition for a given registration. To change this so only the transition permission is needed, visit the global registration settings page at /admin/structure/registration-settings, and update the appropriate field in the "Registration workflow" section of the page. You can also prevent users from completing their own registrations using the relevant field, if desired.

Users with the appropriate permissions will see actions corresponding to the available transitions when viewing a registration or viewing a registration listing. Currently, permissions provided by this module only control workflow operations, and do not alter the states shown on the registration form.

Site builders can customize the workflow states and transitions using the Drupal core admin interface at Configuration > Workflow.

**Note**: This module triggers a "workflow:transition" event within ECA if you have the [ECA Workflow](https://www.drupal.org/project/eca) module installed.
