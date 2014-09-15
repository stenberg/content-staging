<?php
namespace Me\Stenberg\Content\Staging\Controllers;

use Me\Stenberg\Content\Staging\Background_Process;
use Me\Stenberg\Content\Staging\DB\Batch_DAO;
use Me\Stenberg\Content\Staging\DB\Batch_Importer_DAO;
use Me\Stenberg\Content\Staging\Managers\Batch_Mgr;
use Me\Stenberg\Content\Staging\Models\Batch;
use Me\Stenberg\Content\Staging\Models\Batch_Importer;
use Me\Stenberg\Content\Staging\View\Batch_Table;
use Me\Stenberg\Content\Staging\DB\Post_DAO;
use Me\Stenberg\Content\Staging\View\Post_Table;
use Me\Stenberg\Content\Staging\View\Template;
use Me\Stenberg\Content\Staging\XMLRPC\Client;

class Batch_Ctrl {

	private $template;
	private $batch_mgr;
	private $xmlrpc_client;
	private $batch_importer_dao;
	private $batch_dao;
	private $post_dao;

	public function __construct( Template $template, Batch_Mgr $batch_mgr, Client $xmlrpc_client,
								 Batch_Importer_DAO $batch_importer_dao, Batch_DAO $batch_dao, Post_DAO $post_dao ) {
		$this->template           = $template;
		$this->batch_mgr          = $batch_mgr;
		$this->xmlrpc_client      = $xmlrpc_client;
		$this->batch_importer_dao = $batch_importer_dao;
		$this->batch_dao          = $batch_dao;
		$this->post_dao           = $post_dao;
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

		$batch = $this->batch_mgr->get_batch( $batch_id );

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

		// Get posts user can select to include in the batch.
		$posts = $this->post_dao->get_published_posts( $order_by, $order, $per_page, $paged );
		$posts = $this->sort_posts( $posts );

		// Get IDs of posts user has selected to include in this batch.
		$post_ids = $this->batch_dao->get_post_meta( $batch->get_id(), 'sme_selected_post_ids', true );

		/*
		 * When fetching post IDs an empty string could be returned if no
		 * post meta record with the given key exist since before. To prevent
		 * ensure the system can rely on us working with an array we perform a
		 * check setting $post_ids to array if it is currently an empty.
		 */
		if ( ! $post_ids ) {
			$post_ids = array();
		}

		// Create and prepare table of posts.
		$table        = new Post_Table( $batch, $post_ids );
		$table->items = $posts;
		$table->prepare_items();

		$data = array(
			'batch' => $batch,
			'table' => $table,
		);

		$this->template->render( 'edit-batch', $data );
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
	 * @todo The complete batch is prepared to be sent through a form to the
	 * 'Deploy Batch' page. This could potentially result in problems with
	 * the PHP 'post_max_size'. In this case a better option might be to e.g.
	 * store data in database and send something else with the form.
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

		// Let third-party developers filter batch data.
		$batch->set_posts( apply_filters( 'sme_prepare_posts', $batch->get_posts() ) );
		$batch->set_attachments( apply_filters( 'sme_prepare_attachments', $batch->get_attachments() ) );
		$batch->set_users( apply_filters( 'sme_prepare_users', $batch->get_users() ) );

		// Let third-party developers add custom data to batch.
		do_action( 'sme_prepare_custom_data', $batch );

		$request = array(
			'batch'  => $batch,
		);

		$this->xmlrpc_client->query( 'smeContentStaging.preflight', $request );
		$response = $this->xmlrpc_client->get_response_data();

		// Pre-flight status.
		$is_success = true;

		// Check if pre-flight messages contains any errors.
		foreach ( $response as $message ) {
			if ( $message['level'] == 'error' ) {
				$is_success = false;
			}
		}

		// Prepare data we want to pass to view.
		$data = array(
			'batch'      => $batch,
			'batch_data' => base64_encode( serialize( $batch ) ),
			'messages'   => $response,
			'is_success' => $is_success,
		);

		$this->template->render( 'preflight-batch', $data );
	}

	/**
	 * Runs on production when a pre-flight request has been received.
	 *
	 * @param array $args
	 * @return string
	 */
	public function preflight( array $args ) {

		$this->xmlrpc_client->handle_request( $args );
		$result = $this->xmlrpc_client->get_request_data();

		// Check if a batch has been provided.
		if ( ! isset( $result['batch'] ) || ! ( $result['batch'] instanceof Batch ) ) {
			return $this->xmlrpc_client->prepare_response(
				array( 'error' => array( 'Invalid batch!' ) )
			);
		}

		// Get batch.
		$batch = $result['batch'];

		// Create importer.
		$importer = new Batch_Importer();
		$importer->set_batch( $batch );

		foreach ( $batch->get_posts() as $post ) {
			// Check if parent post exist on production or in batch.
			if ( ! $this->parent_post_exists( $post, $batch->get_posts() ) ) {
				$importer->add_message(
					'Post ID ' . $post->get_id() . ' is missing its parent post (ID ' . $post->get_post_parent() . '). Parent post does not exist on production and is not part of this batch',
					'error'
				);
			}
		}

		foreach ( $batch->get_attachments() as $attachment ) {
			foreach ( $attachment['sizes'] as $size ) {
				// Check if attachment exists on content stage.
				if ( ! $this->attachment_exists( $size) ) {
					$importer->add_message(
						'Attachment <a href="' . $size . '" target="_blank">' . $size . '</a> is missing on content stage and will not be deployed to production.',
						'warning'
					);
				}
			}
		}

		// Pre-flight custom data.
		foreach ( $importer->get_batch()->get_custom_data() as $addon => $data ) {
			do_action( 'sme_verify_' . $addon, $data, $importer );
		}

		// Check if pre-flight was successful.
		$is_success = true;

		foreach ( $importer->get_messages() as $message ) {
			if ( $message['level'] == 'error' ) {
				$is_success = false;
				break;
			}
		}

		if ( $is_success ) {
			$importer->add_message( 'Pre-flight successful!', 'success' );
		}

		// Prepare and return the XML-RPC response data.
		return $this->xmlrpc_client->prepare_response( $importer->get_messages() );
	}

	/**
	 * Send post directly to production.
	 */
	public function quick_deploy_batch() {

		// Make sure a query param 'post_id' exists in current URL.
		if ( ! isset( $_GET['post_id'] ) ) {
			wp_die( __( 'No post ID has been provided.', 'sme-content-staging' ) );
		}

		// Get as integer.
		$post_id = intval( $_GET['post_id'] );

		$batch = $this->batch_mgr->get_batch();

		$batch->set_title( 'Quick Deploy ' . current_time( 'mysql' ) );
		$batch->set_content( serialize( array( $_GET['post_id'] ) ) );
		$this->batch_dao->insert_batch( $batch );

		$this->batch_dao->update_post_meta( $batch->get_id(), 'sme_selected_post_ids', array( $post_id ) );

		// Redirect user to pre-flight page.
		wp_redirect( admin_url( 'admin.php?page=sme-preflight-batch&id=' . $batch->get_id() ) );
		exit();
	}

	/**
	 * Deploy batch.
	 *
	 * Send batch from content staging environment to production. Data is
	 * stored on the production environment.
	 *
	 * Display any messages that is returned by production.
	 *
	 * @todo Consider sending form data to a different method responsible for
	 * saving the pre-flighted batch data. When data has been saved, redirect
	 * user here, fetch data from database and send it to production. This
	 * would more closely resemble how we handle e.g. editing a batch.
	 */
	public function deploy_batch() {

		// Check that the current request carries a valid nonce.
		check_admin_referer( 'sme-deploy-batch', 'sme_deploy_batch_nonce' );

		// Determine plugin path and plugin URL of this plugin.
		$plugin_path = dirname( __FILE__ );
		$plugin_url  = plugins_url( basename( $plugin_path ), $plugin_path );

		/*
		 * Batch data is sent through a form on the pre-flight page and picked up
		 * here. Decode data.
		 */
		$batch = unserialize( base64_decode( $_POST['batch_data'] ) );

		$request = array(
			'batch'  => $batch,
		);

		$this->xmlrpc_client->query( 'smeContentStaging.deploy', $request );
		$response = $this->xmlrpc_client->get_response_data();

		/*
		 * Batch has been deployed and should no longer be accessible by user,
		 * delete it (not actually deleting the batch, just setting it to draft
		 * to make it invisible to users).
		 */
		$this->batch_dao->delete_batch( $batch );

		$data = array(
			'response' => $response,
		);

		$this->template->render( 'deploy-batch', $data );
	}

	/**
	 * Runs on production when a deploy request has been received.
	 *
	 * @todo Checking if a batch has been provided is duplicated from
	 * pre-flight, fix!
	 *
	 * Store background process ID.
	 *
	 * @param array $args
	 * @return string
	 */
	public function deploy( array $args ) {

		$this->xmlrpc_client->handle_request( $args );
		$result = $this->xmlrpc_client->get_request_data();

		// ----- Duplicated -----

		// Check if a batch has been provided.
		if ( ! isset( $result['batch'] ) || ! ( $result['batch'] instanceof Batch ) ) {
			return $this->xmlrpc_client->prepare_response(
				array( 'error' => array( 'Invalid batch!' ) )
			);
		}

		// Get batch.
		$batch = $result['batch'];

		// ----- Duplicated -----

		$importer = new Batch_Importer();
		$importer->set_batch( $batch );
		$this->batch_importer_dao->insert_importer( $importer );

		// Default site path.
		$site_path = '/';

		// Site path in multi-site setup.
		if ( is_multisite() ) {
			$site      = get_blog_details();
			$site_path = $site->path;
		}

		// Trigger import script.
		$import_script = dirname( dirname( dirname( __FILE__ ) ) ) . '/scripts/import-batch.php';
		$background_process = new Background_Process(
			'php ' . $import_script . ' ' . ABSPATH . ' ' . get_site_url() . ' ' . $importer->get_id() . ' ' . $site_path . ' ' . $importer->get_key()
		);

		if ( file_exists( $import_script ) ) {
			$background_process->run();
		}

		// @todo store background process ID: $background_process->get_pid();

		$response = array(
			'info' => array(
				'Import of batch has been started. Importer ID: <span id="sme-batch-importer-id">' . $importer->get_id() . '</span>',
			)
		);

		// Prepare and return the XML-RPC response data.
		return $this->xmlrpc_client->prepare_response( $response );
	}

	/**
	 * Triggered by an AJAX call. Returns the status of the import together
	 * with any messages generated during import.
	 */
	public function get_import_status() {

		$importer_id = intval( $_POST['importer_id'] );

		$request = array(
			'importer_id' => $importer_id,
		);

		$this->xmlrpc_client->query( 'smeContentStaging.deployStatus', $request );
		$response = $this->xmlrpc_client->get_response_data();

		header( 'Content-Type: application/json' );
		echo json_encode( $response );

		die(); // Required to return a proper result.
	}

	/**
	 * Runs on production when a deploy status request has been received.
	 *
	 * @param array $args
	 * @return string
	 */
	public function deploy_status( array $args ) {

		$response = array(
			'status'   => 0,
			'messages' => array(),
		);

		$this->xmlrpc_client->handle_request( $args );
		$result = $this->xmlrpc_client->get_request_data();

		// Check if a batch has been provided.
		if ( ! isset( $result['importer_id'] ) ) {
			$response['messages']['error'] = array( 'No batch importer has been provided!' );
			return $this->xmlrpc_client->prepare_response( $response );
		}

		// Get batch importer ID.
		$importer_id = intval( $result['importer_id'] );

		// Get batch importer.
		$importer = $this->batch_importer_dao->get_importer_by_id( $importer_id );

		// Create response.
		$response['status']   = $importer->get_status();
		$response['messages'] = $importer->get_messages();

		// Prepare and return the XML-RPC response data.
		return $this->xmlrpc_client->prepare_response( $response );
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
		$redirect_url = admin_url( 'admin.php?page=sme-edit-batch&id=' . $batch->get_id() . '&updated' );

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

		// IDs of posts user has selected to include in this batch.
		$post_ids = array();

		// Check if a title has been set.
		if ( isset( $request_data['batch_title'] ) ) {
			$batch->set_title( $request_data['batch_title'] );
		}

		// Check if any posts to include in batch has been selected.
		if ( isset( $request_data['posts'] ) && is_array( $request_data['posts'] ) ) {
			$post_ids = $request_data['posts'];
		}

		if ( $batch->get_id() <= 0 ) {
			// Create new batch.
			$this->batch_dao->insert_batch( $batch );
		} else {
			// Update existing batch.
			$this->batch_dao->update_batch( $batch );
		}

		// Update batch meta with IDs of posts user selected to include in batch.
		$this->batch_dao->update_post_meta( $batch->get_id(), 'sme_selected_post_ids', $post_ids );
	}

	/**
	 * Sort array of posts so posts of post type 'page' comes first followed
	 * by post type 'post' and then remaining post types are sorted by
	 * post type alphabetical.
	 *
	 * @param array $posts
	 * @return array
	 */
	private function sort_posts( array $posts ) {

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

	/**
	 * Make sure parent post exist (if post has any) either in production
	 * database or in batch.
	 *
	 * @param array $post
	 * @param array $posts
	 * @return bool True if parent post exist (or post does not have a parent), false
	 *              otherwise.
	 */
	private function parent_post_exists( $post, $posts ) {

		// Check if the post has a parent post.
		if ( $post->get_post_parent() <= 0 ) {
			return true;
		}

		// Check if parent post exist on production server.
		if ( $this->post_dao->get_post_by_guid( $post->get_post_parent_guid() ) ) {
			return true;
		}

		// Parent post is not on production, look in this batch for parent post.
		foreach ( $posts as $item ) {
			if ( $item->get_id() == $post->get_post_parent() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if an attachment exists on remote server.
	 *
	 * @param string $attachment
	 * @return bool
	 */
	private function attachment_exists( $attachment ) {
		$ch = curl_init( $attachment );
		curl_setopt( $ch, CURLOPT_NOBODY, true );
		curl_exec( $ch );
		$code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		curl_close($ch);

		if ( $code == 200 ) {
			return true;
		}

		return false;
	}
}