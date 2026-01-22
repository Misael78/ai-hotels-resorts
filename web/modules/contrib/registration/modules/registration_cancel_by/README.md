CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Configuration


INTRODUCTION
------------

Registration Cancel By is a submodule of Registration that adds the ability to set a "cancel by" date for registrations.


REQUIREMENTS
------------

This module requires the Registration Workflow submodule.


CONFIGURATION
-------------

After enabling this module, configure the form display for registration settings at /admin/structure/registration-settings/form-display. Move the "Cancel by date" field into an appropriate location. The recommended location is right after the Open and Close dates.

Then set the permission in the "Registration Cancel By" section of the Permissions page for the appropriate roles. Typically, the permission "Bypass the 'cancel by' date" should be assigned to roles used to administer registrations, so that site administrators can cancel registrations at any time.

The "Cancel by date" field will now appear on the registration settings form for each host entity. When this field is set, the Cancel operation will be hidden for registrations for that host entity after the date is reached (except for users withe bypass permission). Note that you can effectively disable cancel entirely for a given host entity by setting the Cancel by date to the same value as the Open date.
