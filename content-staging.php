<?php
/**
 * Plugin Name: Content Staging
 * Plugin URI: https://github.com/stenberg/content-staging
 * Description: Content Staging.
 * Author: Joakim Stenberg
 * Version: 2.0.1
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
require_once( 'classes/apis/class-common-api.php' );
require_once( 'classes/controllers/class-batch-ctrl.php' );
require_once( 'classes/controllers/class-batch-history-ctrl.php' );
require_once( 'classes/controllers/class-options-ctrl.php' );
require_once( 'classes/controllers/class-settings-ctrl.php' );
require_once( 'classes/db/class-dao.php' );
require_once( 'classes/db/class-batch-dao.php' );
require_once( 'classes/db/class-custom-dao.php' );
require_once( 'classes/db/class-message-dao.php' );
require_once( 'classes/db/class-option-dao.php' );
require_once( 'classes/db/class-post-dao.php' );
require_once( 'classes/db/class-post-taxonomy-dao.php' );
require_once( 'classes/db/class-postmeta-dao.php' );
require_once( 'classes/db/class-taxonomy-dao.php' );
require_once( 'classes/db/class-term-dao.php' );
require_once( 'classes/db/class-user-dao.php' );
require_once( 'classes/factories/class-dao-factory.php' );
require_once( 'classes/factories/class-xmlrpc-client-factory.php' );
require_once( 'classes/importers/class-batch-importer.php' );
require_once( 'classes/importers/class-batch-ajax-importer.php' );
require_once( 'classes/importers/class-batch-background-importer.php' );
require_once( 'classes/importers/class-batch-importer-factory.php' );
require_once( 'classes/listeners/class-benchmark.php' );
require_once( 'classes/listeners/class-import-message-listener.php' );
require_once( 'classes/listeners/class-common-listener.php' );
require_once( 'classes/listeners/class-delete-listener.php' );
require_once( 'classes/managers/class-batch-mgr.php' );
require_once( 'classes/managers/class-helper-factory.php' );
require_once( 'classes/models/class-model.php' );
require_once( 'classes/models/class-batch.php' );
require_once( 'classes/models/class-message.php' );
require_once( 'classes/models/class-option.php' );
require_once( 'classes/models/class-post.php' );
require_once( 'classes/models/class-post-env-diff.php' );
require_once( 'classes/models/class-taxonomy.php' );
require_once( 'classes/models/class-term.php' );
require_once( 'classes/models/class-user.php' );
require_once( 'classes/models/class-post-taxonomy.php' );
require_once( 'classes/view/class-batch-table.php' );
require_once( 'classes/view/class-batch-history-table.php' );
require_once( 'classes/view/class-post-table.php' );
require_once( 'classes/xmlrpc/class-client.php' );
require_once( 'classes/class-background-process.php' );
require_once( 'classes/class-object-watcher.php' );
require_once( 'classes/class-setup.php' );
require_once( 'classes/view/class-template.php' );
require_once( 'functions/helpers.php' );

/*
 * Import classes.
 */
use Me\Stenberg\Content\Staging\Apis\Common_API;
use Me\Stenberg\Content\Staging\Controllers\Batch_History_Ctrl;
use Me\Stenberg\Content\Staging\Controllers\Options_Ctrl;
use Me\Stenberg\Content\Staging\Factories\DAO_Factory;
use Me\Stenberg\Content\Staging\Factories\XMLRPC_Client_Factory;
use Me\Stenberg\Content\Staging\Listeners\Common_Listener;
use Me\Stenberg\Content\Staging\Listeners\Delete_Listener;
use Me\Stenberg\Content\Staging\Listeners\Import_Message_Listener;
use Me\Stenberg\Content\Staging\Setup;
use Me\Stenberg\Content\Staging\Controllers\Settings_Ctrl;
use Me\Stenberg\Content\Staging\View\Template;
use Me\Stenberg\Content\Staging\Controllers\Batch_Ctrl;
use Me\Stenberg\Content\Staging\Importers\Batch_Importer_Factory;

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

		/**
		 * @var Common_API $sme_content_staging_api
		 */
		global $sme_content_staging_api;

		/**
		 * @var wpdb $wpdb
		 */
		global $wpdb;

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

		// Managers / Factories.
		$dao_factory = new DAO_Factory( $wpdb );

		// Template engine.
		$template = new Template( dirname( __FILE__ ) . '/templates/' );

		// XMLRPC client.
		$xmlrpc_client_factory = new XMLRPC_Client_Factory();
		$xmlrpc_client         = $xmlrpc_client_factory->create();

		/*
		 * Content Staging API.
		 *
		 * Important! Do not change the name of this variable! It is used as a
		 * global in the helpers.php scripts so third-party developers have a
		 * way of working with the plugin using functions instead of classes.
		 */
		$sme_content_staging_api = new Common_API( $xmlrpc_client, $dao_factory );

		// Importer.
		$importer_factory = new Batch_Importer_Factory( $sme_content_staging_api, $dao_factory );

		// Controllers.
		$batch_ctrl = new Batch_Ctrl(
			$template, $importer_factory, $xmlrpc_client, $sme_content_staging_api, $dao_factory
		);

		$batch_history_ctrl = new Batch_History_Ctrl( $template );
		$settings_ctrl 		= new Settings_Ctrl( $template );
		$options_ctrl       = new Options_Ctrl( $template );

		// Listeners.
		$import_messages = new Import_Message_Listener( $sme_content_staging_api, $dao_factory );
		$common_listener = new Common_Listener( $sme_content_staging_api, $dao_factory );
		$delete_listener = new Delete_Listener( $template, $sme_content_staging_api );

		// Plugin setup.
		$setup = new Setup( $plugin_url, $batch_ctrl, $batch_history_ctrl, $settings_ctrl, $options_ctrl );

		// Actions.
		add_action( 'init', array( $setup, 'register_post_types' ) );
		add_action( 'init', array( $importer_factory, 'run_background_import' ) );
		add_action( 'admin_menu', array( $setup, 'register_menu_pages' ) );
		add_action( 'admin_notices', array( $setup, 'quick_deploy_batch' ) );
		add_action( 'admin_enqueue_scripts', array( $setup, 'load_assets' ) );

		// Routing.
		add_action( 'admin_post_sme-save-options', array( $options_ctrl, 'save' ) );
		add_action( 'admin_post_sme-save-batch', array( $batch_ctrl, 'save_batch' ) );
		add_action( 'admin_post_sme-quick-deploy-batch', array( $batch_ctrl, 'quick_deploy' ) );
		add_action( 'admin_post_sme-delete-batch', array( $batch_ctrl, 'delete_batch' ) );
		add_action( 'wp_ajax_sme_preflight_request', array( $batch_ctrl, 'preflight_status' ) );
		add_action( 'wp_ajax_sme_import_status_request', array( $batch_ctrl, 'import_status_request' ) );

		// Filters.
		add_filter( 'xmlrpc_methods', array( $setup, 'register_xmlrpc_methods' ) );
		add_filter( 'sme_post_relationship_keys', array( $setup, 'set_postmeta_post_relation_keys' ) );

		// Content Staging loaded.
		do_action( 'content_staging_loaded' );
	}

}

// Activation and deactivation hooks.
register_activation_hook( __FILE__, array( 'Content_Staging', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Content_Staging', 'deactivate' ) );

// Initialize plugin.
add_action( 'plugins_loaded', array( 'Content_Staging', 'init' ) );
