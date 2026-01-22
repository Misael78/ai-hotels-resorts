CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Configuration


INTRODUCTION
------------

This module provides support for changing the host entity for existing registrations. It allows any user with the appropriate permissions to select a new host from a list of possible hosts and then make additional edits to the registration.

This is a developer module. No possible hosts are provided by default. You will need an additional custom or contributed module to provide a list of possible hosts.


CONFIGURATION
-------------

1. Enable the module at Administration > Extend.
2. Install an additional module that provides a list of possible hosts. For example, [Commerce Registration](https://www.drupal.org/project/commerce_registration) provides other variations of the same product as possible hosts.
3. Visit the global registration settings page at /admin/structure/registration-settings. Review the settings for the change host form at the bottom of the settings page, and make any required updates.
4. Give a 'change host' permission to the appropriate roles. A role must have both change host and edit access to a registration to change the host when using the multistep workflow. A role only needs the change host permission to change the host when using the single step workflow.
5. Edit your registration types and configure the new "Allow data loss" setting for each type as needed.
