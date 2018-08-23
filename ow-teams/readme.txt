=== Oasis Workflow Pro Teams ===
Contributors: nuggetsol
Tags: workflow, work flow, review, teams, automatic assignments, manage teams
Requires at least: 3.9
Tested up to: 4.9.1
Stable tag: 3.0

Oasis Workflow Teams Add-on helps you create Teams and assign Team members to the team.

== Description ==
Oasis Workflow Teams Add-on helps you create Teams and assign Team members to the team. These Teams are then available in the workflows and instead of assigning tasks to specific users, you can simply assign the task to the Team.
The plugin will take care of creating assignments for the specific users in the team that have the same role as defined in the workflow step.

The Teams Add-on has the following features:
1.Easy to use
2.Clean user interface.
3.Allows creating more than one team with different members.
4.Supports Custom Roles

Videos to help you get started with Oasis Workflow Teams Add on:

== Installation ==
1. Download the plugin zip file to your desktop
2. Upload the plugin to WordPress
3. Activate your license by going to Workflows --> Settings --> License
4. Create Teams.
5. Activate Oasis Workflow Teams by going to Workflow --> Settings 
6. You are now ready to use the Teams Add on! Build Your Teams and easily manage the assignments.

== Frequently Asked Questions ==

== Screenshots ==

== Changelog ==

= 3.0 =
* FIXED: "Save Team" button was missing when creating a new team due to missing capabilities.

= 2.9 =
* Added new capability - ow_view_teams, to allow viewing of team.
* FIXED: To edit a team, ow_create_teams was also required. Now, only ow_edit_teams capability is required to edit a team.

= 2.8 =
* Added report filters to show teams on the Workflow Reports.
* FIXED: warnings when selecting a team.
* FIXED: Warnings when creating multisite.
* Integrated Teams with Submit to Workflow, to show the team members. This will allow the user to select individual team members, if they do not want to select all.
* Added team specific capabilities - ow_create_teams, ow_edit_teams and ow_delete_teams.

= 2.7 =
* Fixed performance issue by only listing the users for roles specified on the Workflow settings tab.
* Fixed XSS vulnerability for request parameters.
* Enhancement - Linked Teams to Workflows.
* Enhancement - Linked Teams to auto submit engine.

= 2.6 =
* Fixed a corner case, when teams are disabled for new posts, but there are still some posts which were submitted to teams.
* Fixed issue with plugin upgrade message showing up multiple times in a multi-site setup.

= 2.5 =
* Fixed uninstall script

= 2.4 =
* Changed the base plugin file to adhere to other add-ons standard
* Made Teams compatible with the free version.
* Changed the Oasis Workflow system related meta keys to start with underscore, so that they are not visible on the UI.
* Added welcome message on plugin activation.

= 2.3 =
* Change user assignment logic to adhere to changes in v3.8 of Oasis Workflow Pro for "user" and "role" assignments.

= 2.2 =
* Change menu permissions to allow anyone with "edit_theme_options" to be able to create/edit workflows/teams.

= 2.1 =
* Fixed issue with teams DB tables not getting created when "adding a new blog" to a multisite.

= 2.0 =
* WE RECOMMEND TAKING A DATABASE BACKUP BEFORE UPGRADING TO THIS VERSION *
* Code refactored for better maintainability and extension.
* Moved the Team Settings as a tab under the Workflows -> Settings menu.

= 1.4 =
* Fixed issue related to residual code from self review.

= 1.3 =
* Added changes to delete the user from the team, when the user is deleted from the system.
* Added check to not allow duplicate team members in the team.
* Fixed issue with multi-role assignment for teams.

= 1.2 =
* Fixed security issue related to potential XSS attack caused due to add_query_arg() and remove_query_arg() WP functions.
* Fixed issue with plugin updater.

= 1.1 =
* Fixed deactivate/activate of the teams add on related to oasis workflow pro activate/deactivate
* Fixed teams user query for multi-role and control characters.

= 1.0.2 =
* Added support for Auto Submit.
* Added support for Italian Language.

= 1.0.1 =
* Fixed self review
* Fixed multi-site settings issue.
* Fixed upgrade/deactivation issue.

= 1.0.0 =
* Initial version
* Allows to create teams and add team members to work with workflows.
* Teams Add on is compatible with Oasis Workflow Pro 1.0.8 and above.
* Teams Add on is NOT yet compatible with the Auto Submit feature of Oasis Workflow Pro.
* Switch on/off the teams usage anytime during the workflow.





