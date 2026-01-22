CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Configuration


INTRODUCTION
------------

Registration Inline Entity Form is a submodule of Registration that allows site builders to choose an inline form widget for editing registration fields instead of the standard widget. The inline form widget enables a site editor to configure registration settings for a host entity directly on the host entity edit form. For example, a content type with a registration field using the inline form widget would allow event capacity and other registration settings to be entered when editing content of that type, instead of having to visit a separate settings page.

CONFIGURATION
-------------

Configuring the inline form widget requires the following steps:

* Enable this module (registration\_inline\_entity\_form).
* Edit the form display for an entity type and bundle that has a registration field. For example, if the host entity is an Event content type, browse to /admin/structure/types/manage/event/form-display.
* Use the select box for the registration field to choose "**Inline Entity Form - Settings**" as the field widget.
* Optionally use the field widget gear icon to configure the field widget to be collapsible and thus rendered as an HTML 5 details element. If the widget is not collapsible, it will be rendered as a fieldset.

Once the steps are complete, a site editor will see a Registration Settings element when editing a host entity, positioned directly after the standard Registration Type element that normally appears for a registration field. The settings field will only appear if the host entity is configured for registration (i.e. a registration type is selected) and the user has permission to edit registration settings.

If a user does not have permissions to edit registration settings via core Registration module permissions, this module provides permissions that allow editing registration settings using only the inline form. This can be useful in a scenario where site editors should be able to edit settings on host entity edit forms, but should not be able to manage registrations.
