<?php
namespace Me\Stenberg\Content\Staging\Controllers;

use Me\Stenberg\Content\Staging\DB\Batch_DAO;
use Me\Stenberg\Content\Staging\Managers\Batch_Mgr;
use Me\Stenberg\Content\Staging\Models\Batch;
use Me\Stenberg\Content\Staging\View\Batch_Table;
use Me\Stenberg\Content\Staging\DB\Post_DAO;
use Me\Stenberg\Content\Staging\View\Post_Table;
use Me\Stenberg\Content\Staging\View\Template;
use Me\Stenberg\Content\Staging\XMLRPC\Client;

class Batch_Ctrl {

	private $template;
	private $batch_mgr;
	private $xmlrpc_client;
	private $batch_dao;
	private $post_dao;

	public function __construct( Template $template, Batch_Mgr $batch_mgr, Client $xmlrpc_client,
								 Batch_DAO $batch_dao, Post_DAO $post_dao ) {
		$this->template      = $template;
		$this->batch_mgr     = $batch_mgr;
		$this->xmlrpc_client = $xmlrpc_client;
		$this->batch_dao     = $batch_dao;
		$this->post_dao      = $post_dao;
	}

	/**
	 * Get available batches and display them to the user.
	 */
	public function list_batches() {

		$order_by = 'post_modified';
		$order    = 'desc';
		$per_page = 10;
		$paged    = 1;

		if ( isset( $_GET['orderby'] ) ) {
			$order_by = $_GET['orderby'];
		}

		if ( isset( $_GET['order'] ) ) {
			$order = $_GET['order'];
		}

		if ( isset( $_GET['per_page'] ) ) {
			$per_page = $_GET['per_page'];
		}

		if ( isset( $_GET['paged'] ) ) {
			$paged = $_GET['paged'];
		}

		$total_batches = $this->batch_dao->get_published_content_batches_count();
		$batches       = $this->batch_dao->get_published_content_batches( $order_by, $order, $per_page, $paged );

		// Prepare table of batches.
		$table        = new Batch_Table();
		$table->items = $batches;

		$table->set_pagination_args(
			array(
				'total_items' => $total_batches,
				'per_page'    => $per_page,
			)
		);
		$table->prepare_items();

		$data = array(
			'table' => $table,
		);

		$this->template->render( 'list-batches', $data );
	}

	/**
	 * Edit a content batch. Lets the user decide what posts to put in the
	 * batch.
	 */
	public function edit_batch() {

		$batch_id = null;
		$order_by = 'post_modified';
		$order    = 'desc';
		$per_page = 50;
		$paged    = 1;

		// Make sure a query param ID exists in current URL.
		if ( ! isset( $_GET['id'] ) ) {
			wp_die( __( 'No batch ID has been provided.', 'sme-content-staging' ) );
		}

		// Get batch ID from URL query param.
		if ( $_GET['id'] > 0 ) {
			$batch_id = intval( $_GET['id'] );
		}

		$batch = $this->batch_mgr->get_batch( $batch_id, true );

		if ( isset( $_GET['orderby'] ) ) {
			$order_by = $_GET['orderby'];
		}

		if ( isset( $_GET['order'] ) ) {
			$order = $_GET['order'];
		}

		if ( isset( $_GET['per_page'] ) ) {
			$per_page = $_GET['per_page'];
		}

		if ( isset( $_GET['paged'] ) ) {
			$paged = $_GET['paged'];
		}

		// Get posts.
		$posts = $this->post_dao->get_published_posts( $order_by, $order, $per_page, $paged );
		$posts = $this->sort_posts( $posts );

		// Get posts that has previously been added to batch.
		$batch->set_content( unserialize( $batch->get_content() ) );

		/*
		 * Check if any posts exist in batch, if not, reset.
		 */
		if ( ! is_array( $batch->get_content() ) ) {
			$batch->set_content( array() );
		}

		// Create and prepare table of posts.
		$table        = new Post_Table( $batch );
		$table->items = $posts;
		$table->prepare_items();

		$data = array(
			'batch' => $batch,
			'table' => $table,
		);

		$this->template->render( 'edit-batch', $data );
	}

	/**
	 * Send post directly to production.
	 */
	public function quick_deploy_batch() {

		// Make sure a query param 'post_id' exists in current URL.
		if ( ! isset( $_GET['post_id'] ) ) {
			wp_die( __( 'No post ID has been provided.', 'sme-content-staging' ) );
		}

		$batch = $this->batch_mgr->get_batch( null, true );

		$batch->set_title( 'Quick Deploy ' . current_time( 'mysql' ) );
		$batch->set_content( serialize( array( $_GET['post_id'] ) ) );
		$batch->set_id( $this->batch_dao->insert_batch( $batch ) );

		// Redirect user to pre-flight page.
		wp_redirect( admin_url( 'admin.php?page=sme-preflight-batch&id=' . $batch->get_id() ) );
		exit();
	}

	/**
	 * Confirm that we want to delete a batch.
	 */
	public function confirm_delete_batch() {

		// Make sure a query param ID exists in current URL.
		if ( ! isset( $_GET['id'] ) ) {
			wp_die( __( 'No batch ID has been provided.', 'sme-content-staging' ) );
		}

		// Get batch ID from URL query param.
		$batch = $this->batch_mgr->get_batch( $_GET['id'], true );

		// Delete batch.
		if ( isset( $_POST['delete'] ) && $_POST['delete'] === 'delete' ) {
			$this->batch_dao->delete_batch( $batch );
			wp_redirect( admin_url( 'admin.php?page=sme-list-batches' ) );
			exit();
		}

		// Data to be passed to view.
		$data = array( 'batch' => $batch );

		// Render view.
		$this->template->render( 'delete-batch', $data );
	}

	/**
	 * Pre-flight batch.
	 *
	 * Send batch from content staging environment to production. Production
	 * will evaluate the batch and look for any issues that might cause
	 * trouble when user later on deploys the batch.
	 *
	 * Display any pre-flight messages that is returned by production.
	 *
	 * @param Batch $batch
	 */
	public function preflight_batch( $batch = null ) {

		// Make sure a query param ID exists in current URL.
		if ( ! isset( $_GET['id'] ) && ! $batch ) {
			wp_die( __( 'No batch ID has been provided.', 'sme-content-staging' ) );
		}

		if ( ! $batch ) {
			$batch = $this->batch_mgr->get_batch( $_GET['id'] );
		}

		// Get data in batch and let third-party developers filter data.
		$posts       = apply_filters( 'sme_posts', $batch->get_posts() );
		$attachments = apply_filters( 'sme_attachment_urls', $batch->get_attachments() );
		$terms       = apply_filters( 'sme_terms', $batch->get_terms() );
		$users       = apply_filters( 'sme_users', $batch->get_users() );

		$batch_data = array(
			'batch_guid'    => $batch->get_guid(),
			'batch_title'   => $batch->get_title(),
			'batch_creator' => $batch->get_creator_id(),
			'users'         => $users,
			'posts'         => $posts,
			'attachments'   => $attachments,
			'terms'         => $terms,
			'custom'        => array(),
		);

		$custom_data = apply_filters( 'sme_custom_data', array(), $batch_data );

		$batch_data['custom'] = $custom_data;

		$request = array(
			'body'   => $batch_data,
			'action' => 'preflight'
		);

		$this->xmlrpc_client->query( 'content.staging', $request );
		$response = $this->xmlrpc_client->get_response_data();

		/*
		 * @todo Here batch data is prepared to be used in a form so it can
		 * easily be sent to sending page. In this case a better option
		 * would probably be to e.g. store data in database and send
		 * something else with the form.
		 *
		 * Another option is to prepare the data in a different way, e.g.
		 * encoding the same way we do before sending it off with XMLRPC.
		 */
		$data = array(
			'batch_id'   => $batch->get_id(),
			'batch_data' => base64_encode( serialize( $batch_data ) ),
			'response'   => $response,
		);

		$this->template->render( 'preflight-batch', $data );
	}

	/**
	 * Deploy batch.
	 *
	 * Send batch from content staging environment to production. Data is
	 * stored on the production environment.
	 *
	 * Display any messages that is returned by production.
	 */
	public function deploy_batch() {

		// Determine plugin path and plugin URL of this plugin.
		$plugin_path = dirname( __FILE__ );
		$plugin_url  = plugins_url( basename( $plugin_path ), $plugin_path );

		/*
		 * Batch data is sent through a form on the pre-flight page and picked up
		 * here. Decode data.
		 */
		$batch_data = unserialize( base64_decode( $_POST['batch_data'] ) );

		$request = array(
			'body'   => $batch_data,
			'action' => 'send',
		);

		$this->xmlrpc_client->query( 'content.staging', $request );
		$response = $this->xmlrpc_client->get_response_data();

		$data = array(
			'response' => $response,
		);

		$this->template->render( 'deploy-batch', $data );
	}

	/**
	 * Save batch data user has submitted through form.
	 */
	public function save_batch() {

		$batch_id = null;

		// Check that the current request carries a valid nonce.
		check_admin_referer( 'sme-save-batch', 'sme_save_batch_nonce' );

		// Make sure post data has been provided.
		if ( ! isset( $_POST['submit'] ) ) {
			wp_die( __( 'No data been provided.', 'sme-content-staging' ) );
		}

		// Make sure a query param ID exists in current URL.
		if ( ! isset( $_GET['id'] ) ) {
			wp_die( __( 'No batch ID has been provided.', 'sme-content-staging' ) );
		}

		// Get batch ID from URL query param.
		if ( $_GET['id'] > 0 ) {
			$batch_id = intval( $_GET['id'] );
		}

		// Get batch.
		$batch = $this->batch_mgr->get_batch( $batch_id, true );

		// Handle input data.
		$this->handle_edit_batch_form_data( $batch, $_POST );

		// Default redirect URL on successful batch update.
		$redirect_url = admin_url( 'admin.php?page=sme-edit-batch&id=' . $batch_id . '&updated' );

		// Set different redirect URL if user has requested a pre-flight.
		if ( $_POST['submit'] === 'Pre-Flight Batch' ) {
			$redirect_url = admin_url( 'admin.php?page=sme-preflight-batch&id=' . $batch->get_id() );
		}

		// Redirect user.
		wp_redirect( $redirect_url );
		exit();
	}

	/**
	 * Delete a batch.
	 */
	public function delete_batch() {

		// Make sure a query param ID exists in current URL.
		if ( ! isset( $_GET['id'] ) ) {
			wp_die( __( 'No batch ID has been provided.', 'sme-content-staging' ) );
		}

		// Make sure user has sent in a request to delete batch.
		if ( ! isset( $_POST['delete'] ) || $_POST['delete'] !== 'delete' ) {
			wp_die( __( 'Failed deleting batch.', 'sme-content-staging' ) );
		}

		// Check that the current request carries a valid nonce.
		check_admin_referer( 'sme-delete-batch', 'sme_delete_batch_nonce' );

		// Get batch ID from URL query param.
		$batch = $this->batch_mgr->get_batch( $_GET['id'], true );

		// Delete batch.
		$this->batch_dao->delete_batch( $batch );

		// Redirect user.
		wp_redirect( admin_url( 'admin.php?page=sme-list-batches' ) );
		exit();
	}

	/**
	 * Create/update a batch based on input data submitted by user from the
	 * Edit Batch page.
	 *
	 * @param Batch $batch
	 * @param array $request_data Input data from the user. Should contain
	 * two array keys:
	 * 'batch_title' - Title of this batch.
	 * 'posts' - Posts to include in this batch.
	 */
	private function handle_edit_batch_form_data( Batch $batch, $request_data ) {

		// Check if a title has been set.
		if ( isset( $request_data['batch_title'] ) ) {
			$batch->set_title( $request_data['batch_title'] );
		}

		// Check if any posts to include in batch has been selected.
		if ( isset( $request_data['posts'] ) && is_array( $request_data['posts'] ) ) {
			$batch->set_content( serialize( $request_data['posts'] ) );
		}

		if ( $batch->get_id() <= 0 ) {
			// Create new batch.
			$batch->set_id( $this->batch_dao->insert_batch( $batch ) );
		} else {
			// Update existing batch.
			$this->batch_dao->update_batch( $batch );
		}
	}

	/**
	 * Sort array of posts so posts of post type 'page' comes first followed
	 * by post type 'post' and then remaining post types are sorted by
	 * post type alphabetical.
	 *
	 * @param array $posts
	 * @return array
	 */
	private function sort_posts( $posts ) {

		$pages = array();
		$blog_posts = array();
		$others = array();

		foreach ( $posts as $post ) {
			if ( $post->get_post_type() == 'page' ) {
				$pages[] = $post;
			} else if ( $post->get_post_type() == 'post' ) {
				$blog_posts[] = $post;
			} else {
				$others[] = $post;
			}
		}

		return array_merge( $pages, $blog_posts, $others );
	}

}