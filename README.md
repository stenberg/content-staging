WordPress Content Staging
=========================

Environments
------------

You need to set up two environments:

* Content Staging (where content editors do their work).
* Production (the publicly accessible environment).

The Content Staging environment need access to xmlrpc.php on the Production environment (wordpress/xmlrpc.php).

The Production environment need access to the attachments directory (usually wordpress/wp-content/uploads) on the Content Staging environment.

Installation
------------

Add the *content-staging* plugin to your plugins directory (e.g. wordpress/wp-content/plugins) on both environments.

Activate the plugin on both environments.

Configuration
-------------

### Alternative 1 - WP Admin

Go into **Content Staging** > **Settings** to add your staging key (one can be generated from the page) as well as your production site endpoint URL.'

**Important** - Make sure you copy your staging key to your production site.

### Alternative 2 - WP Config File

You can override settings done in WP Admin by defining specific constants in your config file (e.g. your wp-config.php file).

Add the following to your config file on your *Content Staging* environment:

	define( 'CONTENT_STAGING_SECRET_KEY', '_SAME_RANDOM_KEY_ON_BOTH_ENVIRONMENTS_' );
	define( 'CONTENT_STAGING_ENDPOINT', 'https://www.YOUR-PRODUCTION-SITE.com' );
	define( 'CONTENT_STAGING_TRANSFER_TIMEOUT', 60 );

Add the following to your config file on your *Production* environment:

	define( 'CONTENT_STAGING_SECRET_KEY', '_SAME_RANDOM_KEY_ON_BOTH_ENVIRONMENTS_' );
	define( 'CONTENT_STAGING_ENDPOINT', 'https://www.YOUR-CONTENT-STAGE.com' );

*Important!* Make sure to add these configuration values *before* any *require* statements, e.g. before:

	require_once( ABSPATH . 'wp-settings.php' );

Deploy Process
--------------

A batch goes through five different steps on its way to the production environment. These steps are:

* **Create** - Runs on *content stage*. User creates a batch and decides what posts to include.
* **Prepare** - Runs on *content stage*. Prepare batch data that we want to send to production.
* **Verify** - Runs on *production*. Verifies that batch data can be imported on production.
* **Deploy** - Runs on *content stage*. Send batch data to production.
* **Import** - Runs on *production*. Imports batch data.

Hooks
-----

Many of the hooks follow a naming schema that indicates at what point in the deployment process they are triggered:

| Environment   | When                      | Hook Prefix     |
| ------------- | ------------------------- | --------------- |
| Content Stage | Before batch is populated | sme_prepare     |
| Content Stage | After batch is populated  | sme_prepared    |
| Content Stage | Before pre-flight         | sme_preflight   |
| Production    | During pre-flight         | sme_store       |
| Production    | During pre-flight         | sme_verify      |
| Production    | After pre-flight          | sme_verified    |
| Content Stage | After pre-flight          | sme_preflighted |
| Content Stage | Before deploy             | sme_deploy      |
| Content Stage | During deploy             | sme_deploying   |
| Production    | During deploy             | sme_import      |
| Production    | After deploy              | sme_imported    |
| Content Stage | After deploy              | sme_deployed    |

For a complete list of hooks, search the content-staging directory for *do_action* and *apply_filters*.

Custom Data
-----------

Adding custom data to a batch is pretty straightforward, here is a simple example:

	/**
	 * Prepare custom data to be sent from content stage to production.
	 */
	function my_custom_data( $batch ) {
		// Give your custom data a unique name.
		$name = 'my_custom_data';

		// Some data you want to add to the batch.
		$data = 'Hello World';

		// Add your custom data to the batch.
		$batch->add_custom_data( $name, $data );
	}

	// Hook in your custom data.
	add_action( 'sme_prepare', 'my_custom_data' );

	/**
	 * Import custom data on production when batch is deployed.
	 */
	 function import_custom_data( $data ) {
	 	// Do something with your custom data.
	 }

	 // Notice how we add the name of your custom data to the import hook.
	 add_action( 'sme_import_my_custom_data', 'import_custom_data' );

Messages and Deploy Status
--------------------------

During pre-flight you might want to pass messages from the production environment back to content stage so they can be displayed to the user. Doing so is quite easy, here's an example for you:

	/**
	 * Made up function, this could be whatever.
	 */
	function my_custom_image_data( $batch ) {

		/**
         * @var Common_API $sme_content_staging_api
         */
         global $sme_content_staging_api;

		// Add a message to the batch.
		$sme_content_staging_api->add_preflight_message( $batch->get_id(), 'This rocks!', 'success' );
	}

	// Here we use a hook that is triggered in the end of the pre-flight (on production).
	add_action( 'sme_verified', 'my_custom_image_data' );

The same thing is possible when deploying content. In addition you can also fail a batch deploy:

	/**
	 * Made up function, this could be whatever.
	 */
	function my_custom_image_data( $batch ) {

        /**
         * @var Common_API $sme_content_staging_api
         */
         global $sme_content_staging_api;

		// Add a message to the batch.
		$sme_content_staging_api->add_deploy_message( $batch->get_id(), 'Oh no, something went wrong!', 'error' );

		// Mark batch as failed (2 = Fail).
		$sme_content_staging_api->set_deploy_status( $batch->get_id(), 2 );
	}

	// This time we use a hook that is triggered in the end of the deploy process (on production).
	add_action( 'sme_imported', 'my_custom_image_data' );
