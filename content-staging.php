<?php
/**
 * Plugin Name: Content Staging
 * Plugin URI: https://github.com/stenberg/content-staging
 * Description: Content Staging.
 * Author: Joakim Stenberg, Fredrik Hörte
 * Version: 1.2.0
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
require_once( 'classes/view/class-post-table.php' );
require_once( 'classes/xmlrpc/class-client.php' );
require_once( 'classes/class-api.php' );
require_once( 'classes/class-background-process.php' );
require_once( 'classes/class-object-watcher.php' );
require_once( 'classes/class-setup.php' );
require_once( 'classes/view/class-template.php' );
require_once( 'functions/helpers.php' );

/*
 * Import classes.
 */
use Me\Stenberg\Content\Staging\API;
use Me\Stenberg\Content\Staging\DB\Batch_Import_Job_DAO;
use Me\Stenberg\Content\Staging\DB\Post_Taxonomy_DAO;
use Me\Stenberg\Content\Staging\DB\Taxonomy_DAO;
use Me\Stenberg\Content\Staging\Helper_Factory;
use Me\Stenberg\Content\Staging\Setup;
use Me\Stenberg\Content\Staging\View\Template;
use Me\Stenberg\Content\Staging\Controllers\Batch_Ctrl;
use Me\Stenberg\Content\Staging\DB\Batch_DAO;
use Me\Stenberg\Content\Staging\DB\Post_DAO;
use Me\Stenberg\Content\Staging\DB\Postmeta_DAO;
use Me\Stenberg\Content\Staging\DB\Term_DAO;
use Me\Stenberg\Content\Staging\DB\User_DAO;
use Me\Stenberg\Content\Staging\Importers\Batch_Importer_Factory;
use Me\Stenberg\Content\Staging\Managers\Batch_Mgr;
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

		global $wpdb;
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

		// Data access objects.
		$job_dao           = new Batch_Import_Job_DAO( $wpdb );
		$post_dao          = new Post_DAO( $wpdb );
		$postmeta_dao      = new Postmeta_DAO( $wpdb );
		$term_dao          = new Term_DAO( $wpdb );
		$taxonomy_dao      = new Taxonomy_DAO( $wpdb, $term_dao );
		$post_taxonomy_dao = new Post_Taxonomy_DAO( $wpdb, $post_dao, $taxonomy_dao );
		$user_dao          = new User_DAO( $wpdb );
		$batch_dao         = new Batch_DAO( $wpdb, $user_dao );

		$helper = Helper_Factory::get_instance();
		$helper->add_dao( $job_dao );
		$helper->add_dao( $post_dao );
		$helper->add_dao( $postmeta_dao );
		$helper->add_dao( $term_dao );
		$helper->add_dao( $taxonomy_dao );
		$helper->add_dao( $user_dao );
		$helper->add_dao( $batch_dao );

		// XML-RPC client.
		$xmlrpc_client = new Client( $endpoint, CONTENT_STAGING_SECRET_KEY );

		// Managers.
		$batch_mgr        = new Batch_Mgr( $batch_dao, $post_dao, $post_taxonomy_dao, $postmeta_dao, $user_dao );
		$importer_factory = new Batch_Importer_Factory(
			$job_dao, $post_dao, $post_taxonomy_dao, $postmeta_dao, $taxonomy_dao, $term_dao, $user_dao
		);

		// Template engine.
		$template = new Template( dirname( __FILE__ ) . '/templates/' );

		// Controllers.
		$batch_ctrl = new Batch_Ctrl(
			$template, $batch_mgr, $xmlrpc_client, $importer_factory, $job_dao, $batch_dao, $post_dao
		);

		// APIs.
		$sme_content_staging_api = new API( $post_dao, $postmeta_dao );

		// Plugin setup.
		$setup = new Setup( $batch_ctrl, $xmlrpc_client, $plugin_url );

		// Actions.
		add_action( 'init', array( $setup, 'register_post_types' ) );
		add_action( 'init', array( $importer_factory, 'run_background_import' ) );
		add_action( 'admin_menu', array( $setup, 'register_menu_pages' ) );
		add_action( 'admin_notices', array( $setup, 'quick_deploy_batch' ) );
		add_action( 'admin_enqueue_scripts', array( $setup, 'load_assets' ) );
		add_action( 'admin_post_sme-save-batch', array( $batch_ctrl, 'save_batch' ) );
		add_action( 'admin_post_sme-quick-deploy-batch', array( $batch_ctrl, 'quick_deploy' ) );
		add_action( 'admin_post_sme-delete-batch', array( $batch_ctrl, 'delete_batch' ) );
		add_action( 'wp_ajax_sme_include_post', array( $batch_ctrl, 'include_post' ) );
		add_action( 'wp_ajax_sme_import_request', array( $batch_ctrl, 'import_request' ) );

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
