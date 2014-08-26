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
	 * Create the content batch post type.
	 * Should only be invoked through the 'init' action. It will not work if
	 * called before 'init' and aspects of the newly created post type will
	 * work incorrectly if called later.
	 */
	public function register_post_type() {

		$args = array(
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
			'supports'    => array( 'title', 'editor' )
		);

		register_post_type( 'sme_content_batch', $args );
	}

	public function register_menu_pages() {
		add_menu_page( 'Content Staging', 'Content Staging', 'manage_options', 'sme-list-batches', array( $this->batch_ctrl, 'list_batches' ) );
		add_submenu_page( null, 'Batch', 'Batch', 'manage_options', 'sme-edit-batch', array( $this->batch_ctrl, 'edit_batch' ) );
		add_submenu_page( null, 'Quick Deploy', 'Quick Deploy', 'manage_options', 'sme-quick-deploy-batch', array( $this->batch_ctrl, 'quick_deploy_batch' ) );
		add_submenu_page( null, 'Pre-Flight', 'Pre-Flight', 'manage_options', 'sme-preflight-batch', array( $this->batch_ctrl, 'preflight_batch' ) );
		add_submenu_page( null, 'Send', 'Send', 'manage_options', 'sme-send-batch', array( $this->batch_ctrl, 'deploy_batch' ) );
	}

	public function quick_deploy_batch() {
		if ( isset( $_GET['post'] ) && isset( $_GET['action'] ) && isset( $_GET['message'] ) && $_GET['action'] == 'edit' ) {
			?>
			<div class="updated">
				  <p><?php echo '<a href="' . admin_url( 'admin.php?page=sme-quick-deploy-batch&post_id=' . $_GET['post'] ) . '">Send To Production</a>'; ?></p>
			</div>
			<?php
		}
	}

	/**
	 * Register XML-RPC method.
	 *
	 * @param array $methods
	 * @return array
	 */
	public function register_xmlrpc_method( $methods ) {
		return array_merge( $methods, array( 'content.staging' => array( $this->xmlrpc_client, 'request' ) ) );
	}

	public function set_postmeta_post_relation_keys( $meta_keys ) {
		if ( ! in_array( '_thumbnail_id', $meta_keys ) ) {
			$meta_keys[] = '_thumbnail_id';
		}

		return $meta_keys;
	}

}