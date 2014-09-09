WordPress Content Staging
=========================

Environments
------------

You need to set up two environments:

* Content Staging (where content editors do their work).
* Production (the publicly accessible environment).

The Content Staging environment need access to xmlrpc.php on the Production environment (wordpress/xmlrpc.php).<br/>
The Production environment need access to the attachments directory (usually wordpress/wp-content/uploads) on the Content Staging environment.

Configuration
-------------

Add the following to your config file (e.g. wp-config.php) on your *Content Staging* environment:

	define( 'CONTENT_STAGING_SECRET_KEY', '_SAME_RANDOM_KEY_ON_BOTH_ENVIRONMENTS_' );
	define( 'CONTENT_STAGING_REMOTE_SERVER', 'https://www.YOUR-PRODUCTION-SITE.com' );
	define( 'CONTENT_STAGING_XMLRPC_TIMEOUT', 60 );

Add the following to your config file (e.g. wp-config.php) on your *Production* environment:

	define( 'CONTENT_STAGING_SECRET_KEY', '_SAME_RANDOM_KEY_ON_BOTH_ENVIRONMENTS_' );
	define( 'CONTENT_STAGING_REMOTE_SERVER', 'https://www.YOUR-CONTENT-STAGING-SITE.com' );
	define( 'CONTENT_STAGING_XMLRPC_TIMEOUT', 60 );

Installation
------------

Add the *content-staging* plugin to your plugins directory (e.g. wordpress/wp-content/plugins) on both environments.<br/>
Activate the plugin on both environments.

Hooks
-----

### Filter Hooks

**sme\_post\_relationship\_keys** <br/>
Postmeta keys whose records contains relations between posts.

**sme\_prepare\_post\_ids** <br/>
Add a post to the batch by providing the post ID. Runs before pre-flight.

**sme\_prepare\_custom\_data** <br/>
Add custom data to a batch. Runs just before data is sent from content stage to production during pre-flight. Your function should accept two args: $data (all custom data) and $batch_data (all data in batch).

**sme\_prepare\_posts** <br/>
Posts in a batch. Runs just before data is sent from content stage to production during pre-flight.

**sme\_prepare\_attachments** <br/>
Get URLs for attachments included in the bach. Runs just before data is sent from content stage to production during pre-flight.

**sme\_deploy\_attachments** <br/>
Runs just before attachments is imported to production.

### Action Hooks

**sme\_deploy\_custom\_data** <br/>
Do something with custom data third-party has sent to production.