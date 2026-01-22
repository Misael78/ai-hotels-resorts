CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Configuration


INTRODUCTION
------------

Registration Wait List is a submodule of Registration that supports overflow registrations on a wait list. The wait list is activated once the host entity reaches capacity if it is enabled in the host entity registration settings.


CONFIGURATION
-------------

The following tasks should be performed after this module is enabled.

* Edit the registration settings for host entities that you wish to enable the wait list for. Check the "Enable wait list" box and set the capacity for the wait list. You can optionally configure "autofill" and displaying a message after a registration is wait listed. When enabled, the autofill feature will automatically move wait listed registrations to an active state (typically "complete") when spots become available due to registration deletions or cancellations.

* (Optional) Edit your registration types and configure an email to send when a registration is placed on the wait list.

* (Optional) Edit your registration types and configure the autofill priority. See [this issue](https://www.drupal.org/project/registration/issues/3521435) for more information.
