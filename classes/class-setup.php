<?php
namespace Me\Stenberg\Content\Staging;

use Me\Stenberg\Content\Staging\Controllers\Batch_Ctrl;
use Me\Stenberg\Content\Staging\XMLRPC\Client;

class Setup {

	private $batch_ctrl;
	private $xmlrpc_client;
	private $plugin_url;

	public function __construct( Batch_Ctrl $batch_ctrl, Client $xmlrpc_client, $plugin_url ) {
		$this->batch_ctrl    = $batch_ctrl;
		$this->xmlrpc_client = $xmlrpc_client;
		$this->plugin_url    = $plugin_url;
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
		wp_register_script( 'content-staging', $this->plugin_url . '/assets/js/content-staging.js', array( 'jquery' ), '1.0', false );

		// Register CSS stylesheet files for later use with wp_enqueue_style().
		wp_register_style( 'content-staging', $this->plugin_url . '/assets/css/content-staging.css', array(), '1.0' );

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

		// Arguments for batch importer post type
		$importer = array(
			'label'  => __( 'Batch Importers', 'sme-content-staging' ),
			'labels' => array(
				'singular_name'      => __( 'Batch Importers', 'sme-content-staging' ),
				'add_new_item'       => __( 'Add New Batch Importer', 'sme-content-staging' ),
				'edit_item'          => __( 'Edit Batch Importer', 'sme-content-staging' ),
				'new_item'           => __( 'New Batch Importer', 'sme-content-staging' ),
				'view_item'          => __( 'View Batch Importer', 'sme-content-staging' ),
				'search_items'       => __( 'Search Batch Importers', 'sme-content-staging' ),
				'not_found'          => __( 'No Batch Importers found', 'sme-content-staging' ),
				'not_found_in_trash' => __( 'No Batch Importers found in Trash', 'sme-content-staging' )
			),
			'description' => __( 'Batches are imported by Batch Importers.', 'sme-content-staging' ),
			'public'      => false,
			'supports'    => array( 'editor' ),
		);

		register_post_type( 'sme_content_batch', $batch );
		register_post_type( 'sme_batch_importer', $importer );


	}

	public function register_menu_pages() {
		add_menu_page( 'Content Staging', 'Content Staging', 'manage_options', 'sme-list-batches', array( $this->batch_ctrl, 'list_batches' ) );
		add_submenu_page( null, 'Edit Batch', 'Edit', 'manage_options', 'sme-edit-batch', array( $this->batch_ctrl, 'edit_batch' ) );
		add_submenu_page( null, 'Delete Batch', 'Delete', 'manage_options', 'sme-delete-batch', array( $this->batch_ctrl, 'confirm_delete_batch' ) );
		add_submenu_page( null, 'Quick Deploy Batch', 'Quick Deploy', 'manage_options', 'sme-quick-deploy-batch', array( $this->batch_ctrl, 'quick_deploy_batch' ) );
		add_submenu_page( null, 'Pre-Flight Batch', 'Pre-Flight', 'manage_options', 'sme-preflight-batch', array( $this->batch_ctrl, 'preflight_batch' ) );
		add_submenu_page( null, 'Deploy Batch', 'Deploy', 'manage_options', 'sme-send-batch', array( $this->batch_ctrl, 'deploy_batch' ) );
	}

	/**
	 * Display a "Deploy To Production" button whenever a post is updated.
	 */
	public function quick_deploy_batch() {
		if ( isset( $_GET['post'] ) && isset( $_GET['action'] ) && isset( $_GET['message'] ) && $_GET['action'] == 'edit' ) {
			?>
			<div class="updated">
				  <p><?php echo '<a href="' . admin_url( 'admin-post.php?action=sme-quick-deploy-batch&post_id=' . $_GET['post'] ) . '">Deploy To Production</a>'; ?></p>
			</div>
			<?php
		}
	}

	/**
	 * Register XML-RPC methods.
	 *
	 * @param array $methods
	 * @return array
	 */
	public function register_xmlrpc_methods( $methods ) {

		$methods['smeContentStaging.preflight']    = array( $this->batch_ctrl, 'preflight' );
		$methods['smeContentStaging.deploy']       = array( $this->batch_ctrl, 'deploy' );
		$methods['smeContentStaging.deployStatus'] = array( $this->batch_ctrl, 'deploy_status' );

		return $methods;
	}

	public function set_postmeta_post_relation_keys( $meta_keys ) {
		if ( ! in_array( '_thumbnail_id', $meta_keys ) ) {
			$meta_keys[] = '_thumbnail_id';
		}

		return $meta_keys;
	}

}