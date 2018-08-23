=== Oasis Workflow Pro ===
Contributors: nuggetsol
Tags: workflow, work flow, review, assignment, publish, inbox, workflow history, audit
Requires at least: 4.4
Tested up to: 4.9.7
Stable tag: 5.6

Automate your WordPress Editorial Workflow with Oasis Workflow.

== Description ==

Any online publishing organization has one or several Managing Editors responsible for keeping the arrangement of editorial content flowing in an organized fashion.

Oasis Workflow plugin is designed to automate any workflow process using a simple, intuitive graphical user interface (GUI).

The plugin provides three processes:

1. Assignment - represents task related to content generation.

2. Review - represents task related to content review.

3. Publish - represents the actual "publish" task.

**Visual Work flow Designer**
 - Configure your work flow using the easy drag and drop designer interface. See screen shots for more detail.

**Role-Based routing definitions allow you to assign tasks dynamically**
 - By using role-based routing, you can ensure that your process moves forward as quickly as possible without sacrificing accountability.

**Inbox**
 - Users can view their current assignments and sign off their tasks once it's completed.

**Process history lets users retrace their steps**
 - For auditing purposes a record is maintained of all posts that are routed through a workflow process. The process history also captures the comments added by the user when they signed off the particular task.

**Reassign - How to pass the buck?**
 - What if you have been assigned a workflow task, but you feel you are not the appropriate person to complete it? No worry, you can assign the task to another person. 

**Due Date and Email reminders** help you to publish your articles on time.

**Out of the box workflow**
To get you started, the plugin comes with an out of the box workflow. You can also modify the workflow to suit your needs. 

You can find the complete list of features on the [support](http://oasisworkflow.com) site.

**Supported languages**
 - English
 - Spanish
 - French
 
**Translators**
* French (fr_FR) - [Baptiste Rieg](http://www.batrieg.com)

**If you need help setting up the roles, we recommend the [User Role Editor plugin](http://wordpress.org/extend/plugins/user-role-editor/ "User Role Editor plugin").**

Videos to help you get started with Oasis Workflow:

[youtube http://www.youtube.com/watch?v=PPBJns2p-zU]

[youtube http://www.youtube.com/watch?v=SuOCBf_mLpc]

== Installation ==

1. Download the plugin zip file to your desktop
2. Upload the plugin to WordPress
3. Activate your license by going to Workflow Admin --> Settings --> License Settings
4. Activate Oasis Workflow by going to Workflow Admin --> Settings --> Workflow Settings 
5. You are now ready to use Oasis Workflow! Build Your Workflow and start managing your editorial content flow.

== Frequently Asked Questions ==

For [Frequently Asked Questions](http://oasisworkflow.com/faq) plus documentation, plugin help, go [here](http://oasisworkflow.com)

== Screenshots ==

1. Visual Work flow designer
2. Role-based routing
3. Inbox
4. Sign off
5. Process history


== Changelog ==

= Version 5.6 =
* Made it compatible with ACF 5.6.10 and above.

= Version 5.5 =
* Fixed workflow submission with and without ACF plugin enabled.
* Modified code in Submit and Sign off Workflow to be IE 11 compatible.

= Version 5.4 =
* Enhanced Import of workflow to display importing details with success and errors.
* Enhanced workflow abort to allow users to add comments.
* Added new filter for setting up custom auto submit intervals - "owf_auto_submit_custom_interval".
* Added hook - owf_publish_past to allow for past publish date.
* Fixed validation for ACF required fields before submitting to workflow. Made it compatible with v5.x free version.
* Fixed issue with $_POST unset during revision process.
* Fixed article order issue with page hierarchy.

= Version 5.3 =
* Enhanced Export and Import of workflow( now includes teams and/or groups ) and various workflow settings.
* Added system information tab to the Tools menu. This will allow us to better serve our customers when analyzing an issue.
* Added "Duplicate Post" along with "Make Revision". "Duplicate Post" will simply duplicate the post.
* Disable the submit button on submit to workflow and sign-off popup until ajax call is completed.
* Removed assignment notification after claim process.
* FIXED: Fixed warnings when installed with PHP 7.x.
* FIXED: Auto submit to honor workflow applicable post types.
* FIXED: Issue with oasiswf_custom_placeholders_handler to check for whole word instead of partial text.
* FIXED: Removed assignment notification in case of self assignment.
* FIXED: Issue with private property on OW_Admin_Post causing a PHP fatal error.
* FIXED: Issue with translation when a post link is included in the assignment email.

= Version 5.2 =
* FIXED: Issue with WordPress revisions not getting created if content is not changed.
* FIXED: Issue with publish date/time to honor the site date/time.
* FIXED: Issue with Make Revision Overlay not showing up due to the owf_skip_workflow filter.
* Added a check to validate existence of "free" Oasis Workflow plugin.

= Version 5.1 =
* Added new filter - owf_skip_workflow, to define custom criteria for skipping the workflow on a post by post basis.
* Changed the "Assignment Emails" to be off by default. This is particularly useful, when you are setting up the plugin for the first time and wouldn't like emails to go out to real users.
* Change the "Revision Compare" tool to be more like the out of the box compare.
* Added a new link called "Update Published Post", to update published post with the "copy-of", when the revision workflow is aborted.
* FIXED: default functions for email subject.
* FIXED: Display row action "Make Revision" link as issue with Duplicate Post.
* FIXED: In some cases, the published email notification was going to incorrect user.
* FIXED: Compatibility issues with popup plugins and Make Revision functionality.
* FIXED: Issue with revision copy - non-html content inside <> brackets was being stripped.
* FIXED: ACF Pro Validation with Workflow support.

= Version 5.0 =
* Sort assignee list by name during submit to workflow, sign-off and reassign.
* Added a new filter called "owf_unset_postmeta", to unset any post meta attributes during the revision process.
* Modified Workflow Dashboard/Widget CSS to have a better UX.
* Added "Teams" to reporting, so that user can know which team the post is assigned to.
* Refactored "assignee" list code to be simple and efficient.
* Added "%post_submitter%" placeholder to assignment and reminder emails.
* FIXED: Viewing "404 page not found" issue with scheduled revision.
* FIXED: redirect of page conflicting with WP Security Audit Log plugin.
* FIXED: issue with revision post permalink if prefix and suffix are empty.
* FIXED: {post_title} link for Revised Post Publish Notification email.
* FIXED: Sign off and Submit to Workflow popup to show only the required fields.
* FIXED: Delete postmeta fields when the revision of the post is deleted.
* FIXED: Warnings for workflow settings while creating multisite.

= Version 4.9 =
* Auto Submit Enhancements - Read the updated documentation at - https://www.oasisworkflow.com/documentation/working-with-workflows/auto-submit-to-workflow
* Added filter for redirecting a user to a custom url after submitting post to workflow.
* Added filter to remove sign-off date from assignment/reminder emails.
* Fixed issue with PSN plugin to trigger email during custom post status transition for revision of post.
* Fixed issue with permalink breaking during post status update.
* Added security fixes by sanitizing user input.
* Removed "sslverify => false" on wp_remote_post calls to fix security issues.
* Fixed the CSS to display submit to workflow, sign-off, reassign popups without needing to scroll.
* Fixed display of custom status on the post edit page.
* Fixed revision check to ignore posts sitting in trash.
* Fixed issue with "Reassign" when using with Teams add-on.

= Version 4.8 =
* Enhanced Reports by adding more sorting options on the "Current Assignments" and "Workflow Submissions" report.
* Improved performance of the reports by optimizing the queries and refactoring the display logic.
* Improved performance of the history page by optimizing the queries.
* Cached query results to avoid duplicate queries for a given request.
* Fixed assignee list logic for reassign feature.
* Enhanced Post revision history by adding "copy-of" revisions to the published post. This will further help in audit process. This is an optional feature and can be turned on from "Document Revision" Settings page.
* Allow users to update the publish date(change a deadline) during the workflow. Users will "publish_posts" and/or "publish_pages" can change the deadline during workflow.
* Fixed an issue with reminder email - delete reminder email once the user has completed his/her review assignment.

= Version 4.7 =
* Removed wp_email related filters, since they were overriding all the emails and not just workflow related emails.
* Added translation support for some un-translated strings.
* Fixed an issue related to users with custom roles not showing up in the assignee list.

= Version 4.6 =
* New Feature - Digest Email - combines all assignment/reminder emails into one single message and hence reduce the number of emails your users get from the workflow system.
* New Feature - Externalized the various system emails, so that you can configure the subject, content and recipients.
* Added a hook for Pre Claim Validation. This will allow developers to implement custom validation on task claim.
* Fixed issue with Jetpack Publicize not getting invoked when task is signed off from the Inbox page.
* Fixed issue with slug/permalink - modified permalink was getting updated with the original one when signing off the task.
* Fixed issue with "Submit to Workflow" redirect not honoring the original URL.
* Fixed issue with duplicate post publish notifications for new post as well as revised post updates.


= Version 4.5 =
* Enhanced Dashboard Widget to show the number to current tasks.
* New Feature - Allow workflow designer to define default due date on a per step basis.
* New Feature: Allow Administrator to set Workflow Inbox as the landing page for selected roles.
* Fixed status drop down showing "hello" as a option on bulk edit.
* Fixed status drop down not showing custom post statuses.
* Fixed issue with sign off popup not closing in certain cases.
* Added jquery-ui CSS overrides for the calendar widget.
* Fixed the editorial comments count issue, when working with contextual comments add-on.
* Fixed issue with due date validator for non-english languages.
* Changed the URL for post/page from $post->guid to use get_permalink().
* Redirect to the post/page list page after submit to workflow.
* Made is compatible with WP 4.7

= Version 4.4 =
* Added a new setting under Workflow tab, to specify the roles that will participate in the workflow.
* The above, fixes performance issue during workflow step create/update by only listing the users for roles specified on the Workflow Settings tab.
* Added a new setting to show/hide the publish date on "Submit to Workflow". If hidden, the publish date will be set to the current date.
* When publishing, "Immediately" will be checked by default if the publish date is not in future.
* Added filter for adding/removing columns from the inbox page.
* Added month and year drop down to the date picker for easy navigation.
* Fixed an issue with "Claim" when post was claimed from the post edit page.
* Fixed issue related to capability/user role for auto submit. Now auto submit will run as an administrator.
* Fixed auto submit for "team" submission.
* Fixed validation related to due dates specified for a date later than 1st Jan 2017.
* Fixed issue with post status not getting updated on submit to workflow.

= Version 4.3 =
* Fixed issue with last step of workflow not doing editorial checklist validation.
* Fixed issue with immediate and scheduled update of revision related to timezone settings.
* Fixed issue with sign off popup not working with certain versions of IE.
* Fixed issue with capability check for custom post types.
* Fixed "assign to all" logic to cover a corner case which was causing none of the users to get the assignment.
* Added hook to delete contextual comments when a post is deleted.
* Fixed the loader icon to show/hide the closest one, when multiple "make revision" buttons are displayed.
* Fixed a compatibility issue with PODS plugin for "Make Revision" process.
* Fixed issue with contextual comments not showing up after reassign action.
* Added a confirmation dialog when deleting a workflow.
* Fixed issue with plugin upgrade message showing up multiple times in a multi-site setup.
* Added filter to add/remove actions from Inbox page.

= Version 4.2 =
* Performance improvements - We got rid of multiple AJAX calls, so you should see actions like "Submit to Workflow", "Sign off" to perform better.
* Removed the 1-minute intentional delay during revision update. Now Revision updates will be immediate or scheduled.
* Reduced dependency on WP-Cron for revision updates. Only Scheduled updates will use wp-cron.
* Fixed issue with HTML tags (if present in post title) where getting stripped during revision create.
* Fixed XSS vulnerability for request parameters.
* Enhancement - Mouse Over on Connection Info - This will allow you to quickly see the post status transition from one step to another.
* Fixed date format and display issues related to non-English dates.
* Fixed issues related to special characters like [,],(,) for post revision suffix and prefix.
* Modified publish step, to allow for publish dates in the past.

= Version 4.1 =
* Changed the Oasis Workflow system related meta keys to start with underscore, so that they are not visible on the UI.
* Added support for Multi-role users.
* Fixed "Submitted By" being blank on Auto Submit.
* Moved "Review Settings" from Workflow Settings page to the "Review" process. This will help to have much better control on the review process.
* Fixed - Show out of of the box statuses on the post edit page.
* Fixed - When "Save Draft" is clicked, the post was getting published.
* Changed logic to remove suffix/prefix from the revision. Instead of using substring, now we are using preg_replace.
* Fixed View link from within Inbox page. It was throwing a 404 error.
* Added a filter for get_users_in_step as owf_get_users_in_step()

= Version 4.0 =
* WE RECOMMEND TAKING A DATABASE BACKUP BEFORE UPGRADING TO THIS VERSION *
* Enhancement - Major Upgrade - Post Status transition moved from "Step Info" to "Connection Info". This will allow for better flexibility in defining post status transitions when moving from one step to the next.
* Enhancement - Allow Post Status change on "Submit to Workflow". 
* New Feature - Workflow Summary Dashboard Widget.
* Fixed issue with custom post statuses not showing up on "edit" post.
* Fixed "current revision by" not showing up in revision compare.

= Version 3.9 =
* Fixed revision trash and restore to reconnect with the original article so that the revision is never orphaned.
* Revision Compare - added option to either do a raw HTML compare or text/content compare.
* Fixed the Inbox page from scrolling back to the top, when working on a task which is not displayed in the above-the-fold list.
* Fixed custom fields issue - when deleted from the revision, should also get deleted from the original during revision publish. 
* Removed dependency on PHP Sessions due to intermittent support across several server environments.

= Version 3.8 =
* New Feature - Major change - You can now pre-assign users along with roles during the workflow definition.
* New Feature - Assignee selection on the Workflow Step is changed to a more user friendly drop down using select2.js.
* New Feature - You can now sort and filter workflows by various parameters like start date, end date, title, number of posts/pages in the workflow.
* New Feature - View Workflow description on mouse over of the workflow title.
* New Feature - Display custom categories - The category field on the inbox page and %category% placeholder will show custom categories.
* Fixed Export of Workflows not working for non-English locales.
* Fixed issue with make revision not working when the article contains some special characters.
* Added help icon on workflow step to help new users on how to edit a step.
* Added "Priority" to Workflow Terminology settings page, so that you can call it the way you want it to be.
* When a user is deleted, if there are tasks assigned to the user, the plugin will either delete the user's tasks OR abort the workflow whichever is appropriate.
* "Assign to all" will be checked by default for new step definitions.
* You can choose to send the "submitter" a notification when a post/page is submitted to a workflow.
* Performance improvement - Tweaked SQL queries to increase the performance for Posts Lists page and in general other page loads.

= Version 3.7 =
* New Feature - Task Priority. You can now assign priority (low, normal, high, urgent) to workflow task during sign off.
* New Feature - Added placeholder for Post Author to the assignment and reminder emails.
* New Feature - Added custom filter - ow_workflow_menu_position, to change "Workflows" menu position in the Admin UI.
* New Feature - Multi-user reassign. If assigned to multiple users for assignment and/or publish process, "Claim" will be shown to those users.
* Fixed issue with duplicate nonces creating unnecessary long urls and causing 414 errors in some cases.
* Fixed issue with role ids having spaces. 
* Fixed issue with assignment and reminder emails in some cases not working due to line breaks not being parsed correctly.
* Combined Workflow and Step name into one field on the Inbox page.
* Read more about this release at -  https://www.oasisworkflow.com/oasis-workflow-pro-v3-7-released

= Version 3.6 = 
* New Feature - Custom Statuses.
* New Feature - Assign to all. A way to assign to all the users in a given role for a given step. This will hide the user selection on the sign off process and instead assign to all the users in that role.
* New Feature - Two new custom capabilities - "ow submit to workflow and "ow sign off from step" - This will allow you to control who can/cannot submit and who can/cannot sign off.
* Fixed issue with revision updates not happening in certain environments, caused due to race condition between copy of the revised contents and revision delete.
* Fixed issue with publish emails being sent multiple times.
* Fixed issue with revision update emails not being sent at all.

= Version 3.5 =
* Added Export/Import Workflows to easily copy workflows between environments or sites.
* Fixed issue with WP error "You are not allowed to edit this post" on revision update.
* Added custom capability check to multi abort action.
* Fixed duplicate Post Author appearing in the list of available users.
* Updated the plugin updater to fix duplicate upgrade message in multi-site environments.
* Added Dutch locale files.

= Version 3.4 =
* Introducing custom capabilities. Find more at - https://www.oasisworkflow.com/documentation/oasis-workflow-custom-capabilities-explained
* Removed all the role based settings and now they are controlled using custom capabilities.
* Added "Make Revision" overlay to prevent users from accidentally updating the published version and then realizing that they need to revise first and then make changes.
* Revision Compare will also show the taxonomy compare. Also, added support for our new add-on which will display comparison for ACF attributes.
* Fixed issue with comments showing up twice in email.

= Version 3.3 =
* Added support for Advanced Custom Fields (ACF) plugin to invoke ACF validation before "Submit to Workflow" and/or "Sign off".
* Added Javascript hooks to invoke custom functionality before displaying the "Submit to Workflow" and/or "Sign off" popup.
* Changed background color for inbox items which are past due date.
* Escaped the workflow step names.
* Change menu permissions to allow anyone with "edit_theme_options" to be able to create/edit workflows/teams.
* Change the Workflow Designer to be more user friendly with help text and contextual help.

= Version 3.2 =
* Fixed step assignment and reminder message to retain WYSIWYG formatting.
* Compare Revision will now open into a new window allowing the post to be saved automatically before compare.
* Fixed the issue with "No users found for the given role" poppping up on unrelated pages.

= Version 3.1 =
* Fixed sign off comments formatting for email and UI.
* Added a config under Document Revision Settings page to control the roles that can "revise" articles.

= Version 3.0 =
* WE RECOMMEND TAKING A DATABASE BACKUP BEFORE UPGRADING TO THIS VERSION *
* YOU WILL BE REQUIRED TO RE-ACTIVATE THE PLUGIN AFTER UPGRADE, SINCE WE HAVE CHANGED THE BASE PLUGIN FILE NAME *
* Code refactored for better maintenance and ease of extension.
* Major multisite related change -
* For a multisite installation, you can now control the workflow configuration and workflows at the site level. The Workflow Admin menu is moved from Network Admin to Site Admin.
* Merged Workflows and Workflow Admin into one menu option called "Workflows".
* Fixed issue related to hiding "Update" button if the revision process is not activated. If the revision process is not activated, the Publish section will behave OOTB.
* End date on the workflows is not required any more. If not specified it will be considered to be valid for ever.

= Version 2.7 =
* Fixed CSRF and SQL injection security related issues.
* Users can view the history of the post/page from within their inbox with just one click.
* Users can view all the comments posted on the post from within their inbox. This will help the user to easily remember the context for the various comments/changes. 
* Added a new settings tab for configuring workflow terminology. You can now specify your own terminology for various actions.
* Made is compatible with WP 4.3
* Added email config to send notification email to the author when the workflow is aborted.
* Minor UI changes on the step info setup popup.

= Version 2.6 =
* Fixed CSRF and SQL injection security related issues.
* Default the workflow if there is only one applicable workflow.
* Added multi-select and multi-push/pull for the assignee and available users.
* Added comments to reassign action.
* Added more sort options on the inbox.

= Version 2.5 =
* Fixed auto submit to look into allowed post types only.
* Fixed abort and multi-abort functionality and reporting.
* Allow for past publish date when signing off the last task.
* Removed self review when working with teams.
* Fixed CSS for popups.

= Version 2.4 =
* Fixed issue with menu position.
* Fixed issue with emails sent to all instead of assignees when a user updates an article outside the workflow.
* Fixed issue with revision updating the original published date/time.
* Fixed issue with plugin updater.
* Fixed pagination on all the screens.

= Version 2.3 =
* Added email alert to inform the current assignees if anyone changed the article outside the workflow.
* Added role based access to "delete history".
* Fixed display for WP 4.2 compatibility.
* Changed number of posts per Inbox page to 40.
* Fixed date format to support other date formats.
* Fixed email to support multi-byte characters.
* Fixed security issue related to potential XSS attack caused due to add_query_arg() and remove_query_arg() WP functions.

= Version 2.2 =
* Added more options to configure your workflow. Users can now specify the "roles" and "post types" applicable to a specific workflow
* Allow privately published articles to be revised using "make revision" oasis workflow feature.
* Reports are now available to all users and not just admins.
* Added sorting on the Workflow Inbox page. Users can now sort their workflow inbox via post title.
* Added config to "compare revision" button. You can hide the "compare" button, if not required. 
* Fixed "revision compare" classname conflict with other plugins
* Fixed status change issue on "submit to workflow"
* Fixed reports to show all post types including custom post types
* Fixed default ordering on the inbox page.
* Fixed self review issue with "teams" add on.

= Version 2.1 =
* Enhanced Auto Submit functionality to allow for keyword search in tags and categories apart from the post title.
* Added configuration in auto submit, to allow to control the keyword search
* Added "Delete Copy" config to delete the "Copy of" (revision) after the revision has been copied to the original article.
* Changed "revision exists" popup to provide a link to the revision.
* Added "self review" to the workflows
* Added new hooks for "submit to workflow", "sign off", "workflow complete".
* Added "Abort Workflow" option to the workflow inbox page.
* Added "Email Settings" tab for better control of emails
* Added revision compare tool. This will help to compare contents of the revision to the original article.
* Fixed issue when "review" being the last step, workflow history was not showing "workflow completed".

= Version 2.0.2 =
* Added configuration to control which roles can/cannot abort the workflows.
* Made the roles drop down to be multi-site compatible. Now you will be able to see roles from all the sites.
* Fixed issue with teams-addon preventing the weblinks dialog to appear.
* Fixed formatting issues on submit workflow popup.
* Fixed "clear date" function on submit step popup.

= Version 2.0.1 =
* fixed dd/mm/yyyy format for future publish date

= Version 2.0.0 =
* Added "Delete/Purge" History feature
* Made the date formats to be in sync with the Wordpress Date/Time Settings
* bug fixes

= Version 1.0.11 =
* Fixed "workflow copy" and "save as new version"
* Added a null check for teams
* Fixed cron and is_plugin_active issue

= Version 1.0.10 =
* Made Auto Submit compatible with Teams Add on
* Fixed compatibility issues with Wordpress 4.1
* Fixed publish email notification after revision is published.
* Added Italian translation
* bug fixes

= Version 1.0.9 =
* Fixed self-review enabled due to "oasis workflow teams" add-on
* Fixed future date issue related to timezones
* Added "scheduledrev" post status, to display scheduled revisions until the revisions are copied over to the original article
* Fixed issue with "publish" button showing up, when the item is still in the workflow.
* Fixed post revision schedule.

= Version 1.0.8 =
* Added configuration to revise children articles, by default its off.
* View other user's inbox - configuration
* Added intelligence to decision making on review step.
* Default Due Date configuration.
* Make Revision available for scheduled articles.
* Specify a date/time for the revision to update the original/published article (scheduling a revision update)
* Allow only one active revision.
* Turn on/off revision functionality.
* Introducing Oasis Workflow Teams Add-on (more details on the site)
* Made the plugin compatible with Visual Composer plugin - http://vc.wpbakery.com/
* Made the plugin compatible with WPML - http://wpml.org/
* Add a custom role called - Post Author.
* Fixed Reassign to show all the users applicable to the given step.
* Moved "Reports" under Workflows menu option.
* UI related fixes.

= Version 1.0.7 =
* fixed compatibility issues with Wordpress 4.0
* added missing calendar images
* removed "quick edit" from Workflow Inbox
* removed history graphic from post page for compatibility reasons

= Version 1.0.6 =
* Load the JS and CSS scripts only when needed. This helps with compatibility issues with other plugins.
* Allow setting of future publish date on submit to workflow.
* Allow setting a workflow for "New" and/or "Revised" posts/pages.
* Multi-Abort functionality.
* Added additional placeholders for email.
* Workflow Process can now be selectively applied to certain post types.
* Email author when the post is published.
* fixed german translations.
* fixed compatibility issues with Wordpress 3.9

= Version 1.0.5 =
* fixed issue with workflow history discrepancies and abort workflow action.
* fixed meta copy function for revise action.
* fixed DB related issues with NULL and NOT NULL.
* fixed multisite issue related to switch and restore blog.

= Version 1.0.4 =
* major fixes for supporting "workflow for published content".
* added two new hooks for updating published content via workflow.
* added german translation files
* fixed the issues with Strict PHP - non static function called in static fashion
* fixed update datetime issue with the workflow
* changed post title to be a simple text in the subject line   

= Version 1.0.3 =
* fixed the "make revision" functionality
* fixed to remove a warning message related to mysql_real_escape_string() 

= Version 1.0.2 =
* Added workflow support for updating published content.
* made publish step a multi-user assignment step with claim process.
* fixed issue with permalink being changed after publish from the inbox page.
* fixed the issue with unnecessary call to post_publish hook.
* after sign off, the user will be redirected to the inbox page.

= Version 1.0.1 =
* Added copy workflow and copy step functionality.
* Visual indication of the first step on the workflow in light blue color.

= Version 1.0.0 =
* Initial Pro version