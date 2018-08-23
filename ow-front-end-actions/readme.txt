=== Oasis Workflow Front End Actions ===
Contributors: nuggetsol
Tags: workflow, work flow, review, front end workflow actions, front end sign off
Requires at least: 3.9
Tested up to: 4.7
Stable tag: 1.4

Oasis Workflow Front End Actions Add-on allows users to execute Workflow Actions from the front end of the website.

== Description ==
Oasis Workflow Front End Actions Add-on allows you to embed shortcode for managing Workflow actions from the front end. 

Use:

[ow_workflow_inbox]

To display the Workflow inbox page to logged in users. It should be placed on the page that you wish to display as a inbox page.
The users will be able to sign off from their tasks from this page. They will also be able to claim a task before signing off.

Use:

[ow_make_revision_link text="Make Revision" type="button" style="blue"]

To display the "Make Revision" button on any published page/post. The Make Revision will be available only to users who have the following capabilities:
ow_make_revision and/or ow_make_revision_others

The short code is compatible with other add-ons. 

== Installation ==
1. Download the plugin zip file to your desktop
2. Upload the plugin to WordPress
3. Activate your license by going to Workflow Admin --> Settings --> License Settings
4. Add short code [ow_workflow_inbox] to any page.
5. You are now ready to view and sign off your workflow tasks from the front end.

== Frequently Asked Questions ==

== Screenshots ==

== Changelog ==

= 1.4 =
* Removed the ajax loader that was hiding the whole page on some themes.
* Fixed the comments count, to be aligned with the backend.
* Made is compatible with WP 4.7

= 1.3 =
* Fixed the admin_notice utility function.
* Cleaned up the run_on_add_blog function.
* Added post_id to "make revision" short code.
* Fixed the loader icon to show/hide the closest one, when multiple "make revision" buttons are displayed.
* Fixed issue with plugin upgrade message showing up multiple times in a multi-site setup.

= 1.2 =
* Added support for attributes for the ow_workflow_inbox short code.
* You can now specify the attributes to display on the front end inbox
* Following is the full set of attributes - 
* [ow_workflow_inbox attributes=post_title, workflow, post_status, author, category, due_date, post_type, comment, priority]

= 1.1 =
* Added "Make Revision" to the short code list.
* Usage - [ow_make_revision_link text="Make Revision" type="button" style="blue"]

= 1.0 =
* Initial version
* Allow users to manage Oasis Workflow Actions from the front end of the website.
* Short code [ow_workflow_inbox] to display the inbox on the front end.
* Compatible with Oasis Workflow Teams Add-on.
