CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Configuration


INTRODUCTION
------------

Registration Administrative Overrides is a submodule of Registration that allows administrators to override limits set by registrations settings. For example, you can configure your site to allow an administrator to be able to register other users to an event even after regular event registration has closed. Another common use case is to allow an administrator to create a registration that would exceed the capacity limit for a given host entity.


CONFIGURATION
-------------

Configuring overrides requires the following steps:

* Enable this module (registration\_admin\_overrides).
* Edit your registration types and configure the new "Administrative Override settings" section for each type by checking the boxes for the desired overrides. The possible overrides are host entity status, maximum spaces per registration, host entity capacity, registration open date and registration close date.
* Assign "Administer registrations" permission **and** one or more "Override" permissions to roles that should be able to override settings. The "Override" permissions are listed in their own section on the Permissions page under the heading **Registration Administrative Overrides**. The override permissions that are granted should correspond to the overrides you configured for your registration types.

Once the steps are complete, a user who is logged in and has the appropriate role(s) assigned to their account will be able to register in certain cases when a regular user would be unable to.
