=== Content Staging ===
Contributors: stenberg.me
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=6L9DXMHNE3A6Q
Tags: staging, stage, deploy, deploying, sync, syncing, environment, environments, database, databases, enterprise
Requires at least: 3.7
Tested up to: 4.2.2
Stable tag: 2.0.1
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Content Staging makes it easy to work with content in a private environment before deploying posts, images, users, terms etc. to your live site.

== Description ==

With Content Staging you can prepare content in your staging environment, hidden away from the eye of the public. When
your content is ready to be released you simply push it to your live site.

Content Staging was built to support professional web sites where content editors need to push large amounts of posts per
batch.

You organize your posts into different batches. You can have as many or as few batches as you want and you are in full
control to decide what posts goes into what batch. Deploying one of the batches to your live site is easy - any content
related to selected posts is automatically included in the batch (e.g. images, users, terms) and synced to your
production environment.

Content Staging comes with full support for your multi-site setup.

== Installation ==

Upload `content-staging` to the `/wp-content/plugins/` directory on both your environments (staging and production).

Activate the plugin through the 'Plugins' menu in WordPress on both your staging site and your live site.

*Notice:* Its important that the /xmlrpc.php file is accessible on both environment (usually placed in root directory of
your WordPress installation).

Once activated, go to Content Staging -> Settings to setup the remote server details. You must generate a secret key and copy it to your remote server, as well as define the endpoint for your remote server. 

== Frequently Asked Questions ==

= How can I extend the Content Staging plugin? =

Have a look at https://github.com/stenberg/content-staging for information on available hooks and code examples for
third-party developers.

== Screenshots ==

1. Create as many or as few content batches as you wish.
2. Select what posts, pages or custom post types you want to include in your batch.
3. Pre-Flight your batch to make sure it is ready for deployment.
4. Deploy your batch from staging environment to your live site.

== Changelog ==

= 2.0.1 =
* Fix undefined constant.

= 2.0.0 =
* Faster import.
* Settings page.
* New import message system.
* Decide what post statuses are allowed in batch.
* Support custom importer.
* Select importer.
* Delete batch after import.
* Custom headers in XMLRPC client.
* Only published posts in batch history.
* Remove import job post type (only use batches).
* Unique batch GUIDs.
* Filter deploy messages.
* Stage backend URL in batch.
* Delayed import.
* Handle taxonomies with incorrect term IDs.
* Display deploy messages to user.
* Fix user permission when differing table prefix.
* Set environment.
* Bug fixes.

= 1.2.2 =
* Improved post comparison between content stage and production (better understanding of new vs. updated posts).
* Possible to set pre-flight messages before batch is sent to production for verification (using sme_prepare action hook).

= 1.2.1 =
* History view over previously deployed batches.
* Possible to keep batches that have already been synced.
* New hook, sme_imported, triggered when import on production is completed.
* New hook, sme_deployed, triggered when deploy from stage is completed.

= 1.2.0 =
* Sync term hierarchy.
* Selected posts always placed on top of the 'Edit Batch' list.
* A Batch title is now auto-generated if no title has been set by user.
* Sort batches by creator.
* Display loader while importing batch.
* Improved error reporting.
* Link to post in Edit Batch view.
* New hooks.
* Improved batch summary after deploy.
* Sync category removed from post.

= 1.1.1 =
* Fix creating image directories on production.

= 1.1 =
* New AJAX importer to use when Background importer is not an option.
* Pagination on Edit Batch page.
