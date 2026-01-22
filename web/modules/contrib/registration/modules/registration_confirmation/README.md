CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Configuration


INTRODUCTION
------------

Registration Confirmation is a submodule of Registration that allows site builders to set up an automated confirmation email that is sent after registrations are completed.


CONFIGURATION
-------------

Configuring the confirmation email requires the following steps:

* Enable this module (registration\_confirmation).
* Edit your registration types and configure the new "Confirmation Email Settings" section for each type by checking the "Enable confirmation" box and entering a Subject and Message appropriate for that type.

A user who completes a registration will then receive a confirmation email immediately afterwards, subject to standard email delivery times. This is triggered upon the registration reaching complete state, and does not rely on cron processing.
