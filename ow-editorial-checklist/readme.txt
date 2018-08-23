=== Oasis Workflow Editorial Checklist ===
Contributors: nuggetsol
Tags: workflow, work flow, checklist, comments, editorial checklist, sign off checklist
Requires at least: 3.9
Tested up to: 4.7
Stable tag: 1.6

Automates content checklist before it moves to the next step in the workflow.

== Description ==

Editorial Checklist add-on allows you to define pre-publish or editorial checklist for your WordPress posts.

== Installation ==
1. Download the plugin zip file to your desktop
2. Upload the plugin to WordPress
3. Activate your license by going to Workflows --> Settings --> License
4. Create Condition Groups.
5. Assign condition groups to workflow steps.

== Frequently Asked Questions ==

== Screenshots ==

== Changelog ==

= 1.6 =
* New Feature - Now along with word count, you can specify letter count for checklist conditions.
* Added a new filter "ow_checklist_context_attribute" to allow customized content for word/letter count.
* Made is compatible with WP 4.7

= 1.5 =
* Fixed issue with "by pass" check list configuration not working.
* Delete all the condition groups and it's reference, when the plugin is uninstalled. Clean up the data.
* When a condition group is deleted, delete it from workflows too after user confirmation.

= 1.4 =
* Fixed upgrade script.
* Added new condition type called "checklist" condition to allow users to define custom checklist items.
* Added "Required?" checkbox when defining conditions.
* Added a way to assign condition group during submit to workflow.

= 1.3 =
* Removed redundant code from the utility class.
* Fixed issue with conditions not getting saved on the step information.

= 1.2 =
* Made the add-on compatible with the free version.
* Minor change on when to skip condition checklist.

= 1.1 =
* Added Settings tab, to toggle the checklist on/off.

= 1.0 =
* Initial version
* Allows to create condition groups for post content, title, excerpt.
* Allows to create condition groups for containing attributes like images, tags, categories, featured image and links.