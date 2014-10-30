<?php
/**
 * Plugin Name: Content Staging
 * Plugin URI: https://github.com/stenberg/content-staging
 * Description: Content Staging.
 * Author: Joakim Stenberg, Fredrik Hörte
 * Version: 1.2.1
 * License: GPLv2
 */

/**
 * Copyright 2014 Joakim Stenberg (email: stenberg.me@gmail.com)
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/*
 * Include files.
 */
require_once( ABSPATH . WPINC . '/class-IXR.php' );
require_once( ABSPATH . WPINC . '/class-wp-http-ixr-client.php' );
require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
require_once( 'classes/controllers/class-batch-ctrl.php' );
require_once( 'classes/controllers/class-batch-history-ctrl.php' );
require_once( 'classes/db/class-dao.php' );
require_once( 'classes/db/class-batch-dao.php' );
require_once( 'classes/db/class-batch-import-job-dao.php' );
require_once( 'classes/db/class-post-dao.php' );
require_once( 'classes/db/class-post-taxonomy-dao.php' );
require_once( 'classes/db/class-postmeta-dao.php' );
require_once( 'classes/db/class-taxonomy-dao.php' );
require_once( 'classes/db/class-term-dao.php' );
require_once( 'classes/db/class-user-dao.php' );
require_once( 'classes/importers/class-batch-importer.php' );
require_once( 'classes/importers/class-batch-ajax-importer.php' );
require_once( 'classes/importers/class-batch-background-importer.php' );
require_once( 'classes/importers/class-batch-importer-factory.php' );
require_once( 'classes/managers/class-batch-mgr.php' );
require_once( 'classes/managers/class-helper-factory.php' );
require_once( 'classes/models/class-model.php' );
require_once( 'classes/models/class-batch.php' );
require_once( 'classes/models/class-batch-import-job.php' );
require_once( 'classes/models/class-post.php' );
require_once( 'classes/models/class-taxonomy.php' );
require_once( 'classes/models/class-term.php' );
require_once( 'classes/models/class-user.php' );
require_once( 'classes/models/class-post-taxonomy.php' );
require_once( 'classes/view/class-batch-table.php' );
require_once( 'classes/view/class-batch-history-table.php' );
require_once( 'classes/view/class-post-table.php' );
require_once( 'classes/xmlrpc/class-client.php' );
require_once( 'classes/class-api.php' );
require_once( 'classes/class-background-process.php' );
require_once( 'classes/class-object-watcher.php' );
require_once( 'classes/class-router.php' );
require_once( 'classes/class-setup.php' );
require_once( 'classes/view/class-template.php' );
require_once( 'functions/helpers.php' );

/*
 * Import classes.
 */
use Me\Stenberg\Content\Staging\API;
use Me\Stenberg\Content\Staging\Controllers\Batch_History_Ctrl;
use Me\Stenberg\Content\Staging\Router;
use Me\Stenberg\Content\Staging\Setup;
use Me\Stenberg\Content\Staging\View\Template;
use Me\Stenberg\Content\Staging\Controllers\Batch_Ctrl;
use Me\Stenberg\Content\Staging\Importers\Batch_Importer_Factory;
use Me\Stenberg\Content\Staging\XMLRPC\Client;

/**
 * Class Content_Staging
 */
class Content_Staging {

	/**
	 * Actions performed during plugin activation.
	 */
	public static function activate() {
	}

	/**
	 * Actions performed during plugin deactivation.
	 */
	public static function deactivate() {
	}

	/**
	 * Initialize the plugin.
	 */
	public static function init() {

		global $sme_content_staging_api;

		// Determine plugin URL and plugin path of this plugin.
		$plugin_path = dirname( __FILE__ );
		$plugin_url  = plugins_url( basename( $plugin_path ), $plugin_path );

		// Include add-ons.
		if ( $handle = @opendir( $plugin_path . '/addons' ) ) {
			while ( false !== ( $entry = readdir( $handle ) ) ) {
				$file = $plugin_path . '/addons/' . $entry . '/' .$entry . '.php';
				if ( $entry != '.' && $entry != '..' && file_exists( $file ) ) {
					require_once( $file );
				}
			}
			closedir( $handle );
		}

		// Set endpoint.
		$endpoint = apply_filters( 'sme_endpoint', CONTENT_STAGING_ENDPOINT );

		// XML-RPC client.
		$client = new Client( $endpoint, CONTENT_STAGING_SECRET_KEY );

		// Managers / Factories.
		$importer_factory = new Batch_Importer_Factory();

		// Template engine.
		$template = new Template( dirname( __FILE__ ) . '/templates/' );

		// Controllers.
		$batch_ctrl         = new Batch_Ctrl( $template, $client, $importer_factory );
		$batch_history_ctrl = new Batch_History_Ctrl( $template );

		// APIs.
		$sme_content_staging_api = new API();

		// Direct requests to the correct entry point.
		$router = new Router( $batch_ctrl, $batch_history_ctrl );

		// Plugin setup.
		$setup = new Setup( $router, $client, $plugin_url );

		// Actions.
		add_action( 'init', array( $setup, 'register_post_types' ) );
		add_action( 'init', array( $importer_factory, 'run_background_import' ) );
		add_action( 'admin_menu', array( $setup, 'register_menu_pages' ) );
		add_action( 'admin_notices', array( $setup, 'quick_deploy_batch' ) );
		add_action( 'admin_enqueue_scripts', array( $setup, 'load_assets' ) );
		add_action( 'admin_post_sme-save-batch', array( $router, 'batch_save' ) );
		add_action( 'admin_post_sme-quick-deploy-batch', array( $router, 'batch_deploy_quick' ) );
		add_action( 'admin_post_sme-delete-batch', array( $router, 'batch_delete' ) );
		add_action( 'wp_ajax_sme_include_post', array( $router, 'ajax_batch_add_post' ) );
		add_action( 'wp_ajax_sme_import_request', array( $router, 'ajax_batch_import' ) );

		// Filters.
		add_filter( 'xmlrpc_methods', array( $setup, 'register_xmlrpc_methods' ) );
		add_filter( 'sme_post_relationship_keys', array( $setup, 'set_postmeta_post_relation_keys' ) );
	}

}

// Activation and deactivation hooks.
register_activation_hook( __FILE__, array( 'Content_Staging', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Content_Staging', 'deactivate' ) );

// Initialize plugin.
add_action( 'plugins_loaded', array( 'Content_Staging', 'init' ) );
