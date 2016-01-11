<?php
namespace Me\Stenberg\Content\Staging;

use Me\Stenberg\Content\Staging\Controllers\Batch_Ctrl;
use Me\Stenberg\Content\Staging\Controllers\Batch_History_Ctrl;
use Me\Stenberg\Content\Staging\Controllers\Options_Ctrl;
use Me\Stenberg\Content\Staging\Controllers\Settings_Ctrl;

class Setup {

	private $plugin_url;
	private $batch_ctrl;
	private $batch_history_ctrl;
	private $settings_ctrl;
	private $options_ctrl;

	public function __construct( $plugin_url, Batch_Ctrl $batch_ctrl, Batch_History_Ctrl $batch_history_ctrl,
								 Settings_Ctrl $settings_ctrl, Options_Ctrl $options_ctrl ) {
		$this->plugin_url         = $plugin_url;
		$this->batch_ctrl         = $batch_ctrl;
		$this->batch_history_ctrl = $batch_history_ctrl;
		$this->settings_ctrl      = $settings_ctrl;
		$this->options_ctrl       = $options_ctrl;
	}

	/**
	 * Load assets.
	 */
	public function load_assets() {

		/*
		 * Register script files to be linked to a page later on using the
		 * wp_enqueue_script() function, which safely handles any script
		 * dependencies.
		 */
		wp_register_script( 'content-staging', $this->plugin_url . '/assets/js/content-staging.js', array( 'jquery' ), '1.2.7', false );

		// Register CSS stylesheet files for later use with wp_enqueue_style().
		wp_register_style( 'content-staging', $this->plugin_url . '/assets/css/content-staging.css', array(), '1.0.4' );

		/*
		 * Link script files to the generated page at the right time according to
		 * the script dependencies.
		 */
		wp_enqueue_script( 'content-staging' );

		// Add/enqueue CSS stylesheet files to the WordPress generated page.
		wp_enqueue_style( 'content-staging' );
	}

	/**
	 * Create custom post types.
	 *
	 * Should only be invoked through the 'init' action. It will not work if
	 * called before 'init' and aspects of the newly created post type will
	 * work incorrectly if called later.
	 */
	public function register_post_types() {

		// Arguments for content batch post type
		$batch = array(
			'label'  => __( 'Content Batches', 'sme-content-staging' ),
			'labels' => array(
				'singular_name'      => __( 'Content Batch', 'sme-content-staging' ),
				'add_new_item'       => __( 'Add New Content Batch', 'sme-content-staging' ),
				'edit_item'          => __( 'Edit Content Batch', 'sme-content-staging' ),
				'new_item'           => __( 'New Content Batch', 'sme-content-staging' ),
				'view_item'          => __( 'View Content Batch', 'sme-content-staging' ),
				'search_items'       => __( 'Search Content Batches', 'sme-content-staging' ),
				'not_found'          => __( 'No Content Batches found', 'sme-content-staging' ),
				'not_found_in_trash' => __( 'No Content Batches found in Trash', 'sme-content-staging' )
			),
			'description' => __( 'Content is divided into batches. Content Batches is a post type where each Content Batch is its own post.', 'sme-content-staging' ),
			'public'      => false,
			'supports'    => array( 'title', 'editor' ),
		);

		register_post_type( 'sme_content_batch', $batch );
	}

	public function register_menu_pages() {
		add_menu_page( 'Content Staging', 'Content Staging', apply_filters( 'sme-list-batches-capability', 'manage_options' ), 'sme-list-batches', array( $this->batch_ctrl, 'list_batches' ) );
		add_submenu_page( 'sme-list-batches', 'History', 'History', apply_filters( 'sme-batch-history-capability', 'manage_options' ), 'sme-batch-history', array( $this->batch_history_ctrl, 'init' ) );
		add_submenu_page( 'sme-list-batches', 'Settings', 'Settings', apply_filters( 'sme-settings-capability', 'manage_options' ), 'sme-settings', array( $this->settings_ctrl, 'init' ) );
		add_submenu_page( 'sme-list-batches', 'WordPress Options', 'WordPress Options', apply_filters( 'sme-wp-options-capability', 'manage_options' ), 'sme-wp-options', array( $this->options_ctrl, 'init' ) );
		add_submenu_page( null, 'Edit Batch', 'Edit', apply_filters( 'sme-edit-batch-capability', 'manage_options' ), 'sme-edit-batch', array( $this->batch_ctrl, 'edit_batch' ) );
		add_submenu_page( null, 'Delete Batch', 'Delete', apply_filters( 'sme-delete-batch-capability', 'manage_options' ), 'sme-delete-batch', array( $this->batch_ctrl, 'confirm_delete_batch' ) );
		add_submenu_page( null, 'Pre-Flight Batch', 'Pre-Flight', apply_filters( 'sme-preflight-batch-capability', 'manage_options' ), 'sme-preflight-batch', array( $this->batch_ctrl, 'prepare' ) );
		add_submenu_page( null, 'Quick Deploy Batch', 'Quick Deploy', apply_filters( 'sme-quick-deploy-batch-capability', 'manage_options' ), 'sme-quick-deploy-batch', array( $this->batch_ctrl, 'quick_deploy' ) );
		add_submenu_page( null, 'Deploy Batch', 'Deploy', apply_filters( 'sme-send-batch-capability', 'manage_options' ), 'sme-send-batch', array( $this->batch_ctrl, 'deploy' ) );
	}

	/**
	 * Display a "Deploy To Production" button whenever a post is updated.
	 */

	public function quick_deploy_batch( $messages ) {
		global $post;

		$post_ID = $post->ID;
		$post_type = get_post_type( $post_ID );

		$obj = get_post_type_object( $post_type );
		$singular = $obj->labels->singular_name;

		foreach ( $messages[$post_type] as $key => $message ) {
			$messages[$post_type][$key] = $message . ' or <a href="' . admin_url( 'admin-post.php?action=sme-quick-deploy-batch&post_id=' . $_GET['post'] ) . '">Deploy To Production</a>';
		}

		return $messages;
	}

	/**
	 * Register XML-RPC methods.
	 *
	 * @param array $methods
	 * @return array
	 */
	public function register_xmlrpc_methods( $methods ) {

		$methods['smeContentStaging.verify'] = array( $this->batch_ctrl, 'verify' );
		$methods['smeContentStaging.import'] = array( $this->batch_ctrl, 'import' );
		$methods['smeContentStaging.importStatus'] = array( $this->batch_ctrl, 'import_status' );

		return $methods;
	}

	public function set_postmeta_post_relation_keys( $meta_keys ) {
		if ( ! in_array( '_thumbnail_id', $meta_keys ) ) {
			$meta_keys[] = '_thumbnail_id';
		}

		return $meta_keys;
	}

}
