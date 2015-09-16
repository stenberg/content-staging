<?php
namespace Me\Stenberg\Content\Staging\Controllers;

use Me\Stenberg\Content\Staging\Apis\Common_API;
use Me\Stenberg\Content\Staging\DB\Batch_DAO;
use Me\Stenberg\Content\Staging\DB\Post_DAO;
use Me\Stenberg\Content\Staging\Factories\DAO_Factory;
use Me\Stenberg\Content\Staging\Helper_Factory;
use Me\Stenberg\Content\Staging\Importers\Batch_Importer_Factory;
use Me\Stenberg\Content\Staging\Managers\Batch_Mgr;
use Me\Stenberg\Content\Staging\Models\Batch;
use Me\Stenberg\Content\Staging\Models\Message;
use Me\Stenberg\Content\Staging\View\Batch_Table;
use Me\Stenberg\Content\Staging\View\Post_Table;
use Me\Stenberg\Content\Staging\View\Template;
use Me\Stenberg\Content\Staging\XMLRPC\Client;

class Batch_Ctrl {

	/**
	 * @var Template
	 */
	private $template;

	/**
	 * @var Client
	 */
	private $xmlrpc_client;

	/**
	 * @var Batch_Importer_Factory
	 */
	private $importer_factory;

	/**
	 * @var Common_API
	 */
	private $api;

	/**
	 * @var Batch_DAO
	 */
	private $batch_dao;

	/**
	 * @var Post_DAO
	 */
	private $post_dao;

	/**
	 * Constructor.
	 *
	 * @param Template               $template
	 * @param Batch_Importer_Factory $importer_factory
	 * @param Client                 $xmlrpc_client
	 * @param Common_API             $api
	 * @param DAO_Factory            $dao_factory
	 */
	public function __construct( Template $template, Batch_Importer_Factory $importer_factory, Client $xmlrpc_client,
								 Common_API $api, DAO_Factory $dao_factory ) {
		$this->template         = $template;
		$this->importer_factory = $importer_factory;
		$this->xmlrpc_client    = $xmlrpc_client;
		$this->api              = $api;
		$this->batch_dao        = $dao_factory->create( 'Batch' );
		$this->post_dao         = $dao_factory->create( 'Post' );

		// Action hooks.
		add_action( 'admin_post_sme_delete_batches', array( $this, 'delete_batches' ) );
		add_action( 'admin_notices', array( $this, 'delete_batches_notice' ) );
	}

	/**
	 * Get available batches and display them to the user.
	 */
	public function list_batches() {

		$order_by = 'post_modified';
		$order    = 'desc';
		$per_page = 50;
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

		$status  = apply_filters( 'sme_batch_list_statuses', array( 'publish' ) );
		$count   = $this->batch_dao->count( $status );
		$batches = $this->batch_dao->get_batches( $status, $order_by, $order, $per_page, $paged );

		// Prepare table of batches.
		$table        = new Batch_Table();
		$table->items = $batches;
		$table->set_bulk_actions( array( 'sme_delete_batches' => 'Delete' ) );
		$table->set_pagination_args(
			array(
				'total_items' => $count,
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

		$batch = $this->api->get_batch( $batch_id );

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

		// Get IDs of posts user has selected to include in this batch.
		$post_ids = $this->batch_dao->get_post_meta( $batch->get_id(), 'sme_selected_post' );

		/*
		 * When fetching post IDs an empty string could be returned if no
		 * post meta record with the given key exist since before. To
		 * ensure the system can rely on us working with an array we perform a
		 * check setting $post_ids to array if it is currently an empty.
		 */
		if ( ! $post_ids ) {
			$post_ids = array();
		}

		// Get selected posts.
		$selected_posts = array();
		$chunks         = array_chunk( $post_ids, $per_page );
		if ( isset( $chunks[( $paged - 1 )] ) ) {
			$use_post_ids   = $chunks[( $paged - 1 )];
			$selected_posts = $this->post_dao->find_by_ids( $use_post_ids );
		}

		$status = apply_filters( 'sme_post_list_statuses', array( 'publish' ) );

		// Get posts user can select to include in the batch.
		$posts       = $this->post_dao->get_posts( $status, $order_by, $order, $per_page, $paged, $post_ids );
		$total_posts = $this->post_dao->get_posts_count( $status );
		$posts       = array_merge( $selected_posts, $posts );

		// Create and prepare table of posts.
		$table        = new Post_Table( $batch );
		$table->items = $posts;
		$table->set_pagination_args(
			array(
				'total_items' => $total_posts,
				'per_page'    => $per_page,
			)
		);
		$table->prepare_items();

		$type = get_post_type_object( 'sme_content_batch' );
		if ( ! $batch->get_id() ) {
			$label = $type->labels->new_item;
		} else {
			$label = $type->labels->edit_item;
		}

		// Custom filters for finding posts to include in batch.
		$filters = apply_filters( 'sme_post_filters', $filters = '', $table );

		// Get WordPress options settings for this batch.
		$wp_options = $this->get_wp_options_settings( $batch );

		$data = array(
			'batch'      => $batch,
			'label'      => $label,
			'filters'    => $filters,
			'table'      => $table,
			'post_ids'   => implode( ',', $post_ids ),
			'wp_options' => $wp_options,
		);

		$this->template->render( 'edit-batch', $data );
	}

	/**
	 * Save batch data user has submitted through form.
	 */
	public function save_batch() {

		// Check that the current request carries a valid nonce.
		check_admin_referer( 'sme-save-batch', 'sme_save_batch_nonce' );

		// Make sure a query param ID exists in current URL.
		if ( ! isset( $_GET['id'] ) ) {
			wp_die( __( 'No batch ID has been provided.', 'sme-content-staging' ) );
		}

		// Get batch.
		$batch_id = $_GET['id'] > 0 ? intval( $_GET['id'] ) : null;
		$batch    = $this->api->get_batch( $batch_id );

		/*
		 * Make it possible for third-party developers to modify 'Save Batch'
		 * behaviour.
		 */
		do_action( 'sme_save_batch', $batch );

		// Handle input data.
		$updated = '';
		if ( isset( $_POST['submit'] ) ) {
			$this->handle_edit_batch_form_data( $batch, $_POST );
			$updated = '&updated';
		}

		/*
		 * Make it possible for third-party developers to modify do something
		 * with the newly saved batch before user is redirected.
		 */
		do_action( 'sme_saved_batch', $batch );

		// Default redirect URL on successful batch update.
		$redirect_url = admin_url( 'admin.php?page=sme-edit-batch&id=' . $batch->get_id() . $updated );

		// Set different redirect URL if user has requested a pre-flight.
		if ( isset( $_POST['submit'] ) && $_POST['submit'] === 'Pre-Flight Batch' ) {
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

		// Get batch ID from URL query param.
		$batch_id = $_GET['id'];

		// Delete batch.
		$this->batch_dao->delete_by_id( $batch_id );

		// Redirect user.
		wp_redirect( admin_url( 'admin.php?page=sme-list-batches' ) );
		exit();
	}

	/**
	 * Delete multiple batches.
	 */
	public function delete_batches() {

		if ( ! current_user_can( 'delete_posts' ) ) {
			wp_die();
		}

		// Check that referring URL has been provided.
		if ( ! isset( $_POST['_wp_http_referer'] ) ) {
			wp_die();
		}

		// Get referring URL.
		$url = $_POST['_wp_http_referer'];

		if ( isset( $_POST['batches'] ) && ! empty( $_POST['batches'] ) ) {
			$batches = array_map( 'intval', $_POST['batches'] );

			// Delete batches.
			array_walk( $batches, array( $this->batch_dao, 'delete_by_id' ) );
		}

		// Get query params from URL.
		$query = parse_url( $url, PHP_URL_QUERY );

		// Add query param 'deleted' to referring URL.
		if ( $query && ! strpos( $query, 'deleted' ) ) {
			$url .= '&deleted';
		} else if ( ! $query ) {
			$url .= '?deleted';
		}

		wp_redirect( $url );
		exit;
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
		$batch = $this->api->get_batch( $_GET['id'] );

		// Data to be passed to view.
		$data = array( 'batch' => $batch );

		// Render view.
		$this->template->render( 'delete-batch', $data );
	}

	/**
	 * Render admin notice confirming that batches has been deleted.
	 */
	public function delete_batches_notice() {

		// Look for query params in URL.
		if ( ! isset( $_GET['deleted'] ) || ! isset( $_GET['page'] ) || $_GET['page'] !== 'sme-list-batches' ) {
			return;
		}

		$this->template->render( 'delete-batches-notice' );
	}

	/**
	 * Pre-flight batch.
	 *
	 * Production will evaluate the batch and look for any issues that might
	 * cause trouble when user later on deploys the batch.
	 *
	 * Display any pre-flight messages that is returned by production.
	 *
	 * Runs on content stage.
	 */
	public function prepare() {

		// Make sure a query param ID exists in current URL.
		if ( ! isset( $_GET['id'] ) ) {
			wp_die( __( 'No batch ID has been provided.', 'sme-content-staging' ) );
		}

		// Get batch ID.
		$batch_id = $_GET['id'];

		// Get batch from database.
		$batch = $this->batch_dao->find( $batch_id );

		// Populate batch with actual data.
		$this->api->prepare( $batch );

		// Pre-flight batch.
		$result = $this->api->preflight( $batch );

		// Get status from production.
		$prod_status = ( isset( $result['status'] ) ) ? $result['status'] : 2;

		// Get production messages.
		$prod_messages = ( isset( $result['messages'] ) ) ? $result['messages'] : array();

		// Get status from content stage.
		$stage_status = $this->api->get_preflight_status( $batch->get_id() );

		// Ensure no pre-flight status is not set to failed.
		$status = ( $prod_status != 2 && $stage_status != 2 ) ? $prod_status : 2;

		// Get content stage messages.
		$stage_messages = $this->api->get_preflight_messages( $batch->get_id() );

		// All pre-flight messages.
		$messages = array_merge( $prod_messages, $stage_messages );

		// Set success message.
		if ( $status == 3 ) {
			$message = new Message();
			$message->set_level( 'success' );
			$message->set_message( 'Pre-flight successful!' );
			$message->set_code( 201 );

			array_push( $messages, $message );
		}

		// Deploy button.
		$deploy_btn = array(
			'disabled' => 'disabled',
		);

		// Enable deploy button.
		if ( $status == 3 ) {
			unset( $deploy_btn['disabled'] );
		}

		// Render page.
		$this->template->render(
			'preflight-batch',
			array(
				'batch'      => $batch,
				'status'     => $status,
				'messages'   => $messages,
				'deploy_btn' => $deploy_btn,
			)
		);
	}

	/**
	 * Get pre-flight status for a batch.
	 *
	 * Production will evaluate the batch and look for any issues that might
	 * cause trouble when user later on deploys the batch.
	 *
	 * Display any pre-flight messages that is returned by production.
	 *
	 * Runs on content stage.
	 */
	public function preflight_status() {

		// Get batch ID.
		$batch_id = ( isset( $_POST['batch_id'] ) ) ? intval( $_POST['batch_id'] ) : null;

		// Get batch GUID.
		$batch_guid = ( isset( $_POST['batch_guid'] ) ) ? $_POST['batch_guid'] : null;

		$response = array(
			'status'   => 0,
			'messages' => array(),
		);

		$response = apply_filters( 'sme_preflight_status', $response, $batch_id );

		// Get status from production.
		$prod_status = ( isset( $response['status'] ) ) ? $response['status'] : 2;

		// Get production messages.
		$prod_messages = ( isset( $response['messages'] ) ) ? $response['messages'] : array();

		// Get status from content stage.
		$stage_status = $this->api->get_preflight_status( $batch_id );

		// Ensure no pre-flight status is not set to failed.
		$status = ( $prod_status != 2 && $stage_status != 2 ) ? $prod_status : 2;

		// Get content stage messages.
		$stage_messages = $this->api->get_preflight_messages( $batch_id );

		// All pre-flight messages.
		$messages = array_merge( $prod_messages, $stage_messages );

		// Set success message.
		if ( $status == 3 ) {
			$message = new Message();
			$message->set_level( 'success' );
			$message->set_message( 'Pre-flight successful!' );
			$message->set_code( 201 );

			array_push( $messages, $message );
		}

		// Prepare response.
		$response = array(
			'status'   => $status,
			'messages' => $this->api->convert_messages( $messages ),
		);

		header( 'Content-Type: application/json' );
		echo json_encode( $response );

		die(); // Required to return a proper result.
	}

	/**
	 * Perform pre-flight checks to ensure that batch is ready for deploy.
	 *
	 * Runs on production when a pre-flight request has been received.
	 *
	 * @param array $args
	 *
	 * @return string
	 */
	public function verify( array $args ) {

		if ( $messages = $this->xmlrpc_client->handle_request( $args ) ) {
			return $messages;
		}

		$result = $this->xmlrpc_client->get_request_data();

		// Check if a batch has been provided.
		if ( ! isset( $result['batch'] ) || ! $result['batch'] instanceof Batch ) {
			$message = new Message();
			$message->set_level( 'error' );
			$message->set_message( 'Invalid batch.' );

			return $this->xmlrpc_client->prepare_response(
				array(
					'status'   => 2,
					'messages' => array( $message ),
				)
			);
		}

		// Get batch.
		$batch = $result['batch'];

		// Check if a production version of this batch exists.
		$batch_id = $this->batch_dao->get_id_by_guid( $batch->get_guid() );

		// Replace batch content stage ID with production ID.
		$batch->set_id( $batch_id );

		// Hook in before batch is stored.
		do_action( 'sme_store', $batch );

		// Create new batch or update existing one.
		if ( ! $batch_id ) {
			$this->batch_dao->insert( $batch );
		} else {
			$this->batch_dao->update_batch( $batch );
		}

		// Hook in when batch is ready to be verified.
		do_action( 'sme_verify', $batch );

		// What different type of data needs verification?
		$types = array( 'attachments', 'users', 'posts', 'options' );

		// Go through each data type.
		foreach ( $types as $type ) {
			$this->verify_by_type( $batch, $type );
		}

		// Verify custom data.
		foreach ( $batch->get_custom_data() as $addon => $data ) {
			do_action( 'sme_verify_' . $addon, $data, $batch );
		}

		// Get pre-flight status.
		$status = $this->api->get_preflight_status( $batch->get_id() );

		// Get all messages set during verification of this batch.
		$messages = $this->api->get_preflight_messages( $batch->get_id() );

		// Prepare response.
		$response = array(
			'status'   => ( $status ) ? $status : 3,
			'messages' => $messages,
		);

		// Hook in when batch is ready for pre-flight.
		$response = apply_filters( 'sme_verified', $response, $batch );

		// Get status received from production.
		$status = ( isset( $response['status'] ) ? $response['status'] : 2 );

		// Get messages received from production.
		$messages = ( isset( $response['messages'] ) ? $response['messages'] : array() );

		// Prepare response.
		$response = array(
			'status'   => $status,
			'messages' => $messages,
		);

		// Prepare and return the XML-RPC response data.
		return $this->xmlrpc_client->prepare_response( $response );
	}

	/**
	 * Send post directly to production.
	 */
	public function quick_deploy() {

		// Make sure a query param 'post_id' exists in current URL.
		if ( ! isset( $_GET['post_id'] ) ) {
			wp_die( __( 'No post ID has been provided.', 'sme-content-staging' ) );
		}

		// Get as integer.
		$post_id = intval( $_GET['post_id'] );

		$batch = $this->api->get_batch();

		$batch->set_title( 'Quick Deploy ' . current_time( 'mysql' ) );
		$this->batch_dao->insert( $batch );

		$this->batch_dao->add_post_meta( $batch->get_id(), 'sme_selected_post', $post_id );

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
	 */
	public function deploy() {

		// Check that the current request carries a valid nonce.
		check_admin_referer( 'sme-deploy-batch', 'sme_deploy_batch_nonce' );
		$batch = null;

		// Requested batch ID.
		$batch_id = intval( $_GET['id'] );

		// Get batch.
		$batch = $this->batch_dao->find( $batch_id );

		// Make sure a valid batch has been requested.
		if ( ! $batch instanceof Batch || $batch->get_status() !== 'publish' ) {
			wp_die( __( 'No batch found.', 'sme-content-staging' ) );
		}

		// Deploy the batch.
		$response = $this->api->deploy( $batch );

		// Get messages received from production.
		$status   = isset( $response['status'] )   ? $response['status']   : 0;
		$messages = isset( $response['messages'] ) ? $response['messages'] : array();

		// Render page.
		$this->template->render(
			'deploy-batch',
			array(
				'status'   => $status,
				'messages' => $messages,
			)
		);
	}

	/**
	 * Runs on production when an import request is received.
	 *
	 * @param array $args
	 *
	 * @return string
	 */
	public function import( array $args ) {

		$this->xmlrpc_client->handle_request( $args );
		$result = $this->xmlrpc_client->get_request_data();

		// Get batch.
		$batch = ( isset( $result['batch'] ) ) ? $result['batch'] : null;

		// Check if a batch has been provided.
		if ( ! $batch instanceof Batch ) {
			$message = new Message();
			$message->set_level( 'error' );
			$message->set_message( 'No batch has been sent from content stage to production.' );

			$response = array(
				'status' => 2,
				'messages' => array( $message ),
			);

			return $this->xmlrpc_client->prepare_response( $response );
		}

		// Get production batch ID (if this is an existing batch).
		$batch_id = $this->batch_dao->get_id_by_guid( $batch->get_guid() );
		$batch->set_id( $batch_id );

		if ( $batch_id !== null ) {
			$this->batch_dao->update_batch( $batch );
		} else {
			$this->batch_dao->insert( $batch );
		}

		// If auto import is set to true, then start the batch import immediately.
		if ( ! isset( $result['auto_import'] ) || $result['auto_import'] ) {
			$this->api->import( $batch );
		}

		$response = array(
			'status'   => $this->api->get_deploy_status( $batch->get_id() ),
			'messages' => $this->api->get_deploy_messages( $batch->get_id() ),
		);

		// Prepare and return the XML-RPC response data.
		return $this->xmlrpc_client->prepare_response( $response );
	}

	/**
	 * Output the status of an ongoing import together with any messages
	 * generated during import.
	 *
	 * Triggered by an AJAX call.
	 *
	 * Runs on staging environment.
	 */
	public function import_status_request() {

		$batch_id = intval( $_POST['batch_id'] );
		$response = $this->api->import_status_request( $batch_id );

		// Deploy status.
		$status = isset ( $response['status'] ) ? $response['status'] : 2;

		// Get status from content stage.
		$stage_status = $this->api->get_deploy_status( $batch_id );

		// Use stage status if stage verification has failed.
		if ( $stage_status == 2 ) {
			$status = $stage_status;
		}

		// Get content stage messages.
		$stage_messages = $this->api->get_deploy_messages( $batch_id );

		// All pre-flight messages.
		$messages = array_merge( $response['messages'], $stage_messages );

		// Deploy has finished.
		if ( $status == 3 ) {
			do_action( 'sme_deployed' );
		}

		// Convert message objects into arrays.
		$messages = $this->api->convert_messages( $messages );

		header( 'Content-Type: application/json' );
		echo json_encode(
			array(
				'status'    => $status,
				'messages'  => $messages,
			)
		);

		die(); // Required to return a proper result.
	}

	/**
	 * Runs on production when a import status request is received.
	 *
	 * @param array $args
	 * @return string
	 */
	public function import_status( array $args ) {
		$this->xmlrpc_client->handle_request( $args );

		$result = $this->xmlrpc_client->get_request_data();
		$batch  = $this->batch_dao->find( intval( $result['batch_id'] ) );

		$importer = $this->importer_factory->get_importer( $batch );
		$importer->status();

		$response = array(
			'status'   => $this->api->get_deploy_status( $batch->get_id() ),
			'messages' => $this->api->get_deploy_messages( $batch->get_id() ),
		);

		$response = apply_filters( 'sme_import_status_response', $response, $batch );

		// Get status.
		$status = ( isset( $response['status'] ) ) ? $response['status'] : 2;

		// Get messages.
		$messages = ( isset( $response['messages'] ) ) ? $response['messages'] : array();

		// Prepare and return the XML-RPC response data.
		return $this->xmlrpc_client->prepare_response(
			array(
				'status'   => $status,
				'messages' => $messages,
			)
		);
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
		if ( isset( $request_data['batch_title'] ) && $request_data['batch_title'] ) {
			$batch->set_title( $request_data['batch_title'] );
		} else {
			$batch->set_title( 'Batch ' . date( 'Y-m-d H:i:s' ) );
		}

		if ( $batch->get_id() <= 0 ) {
			// Create new batch.
			$this->batch_dao->insert( $batch );
		} else {
			// Update existing batch.
			$batch->set_status( 'publish' );
			$this->batch_dao->update_batch( $batch );
		}

		// IDs of posts user has selected to include in this batch.
		$selected_post_ids = array();

		// Check if any posts to include in batch has been selected.
		if ( isset( $request_data['post_ids'] ) && $request_data['post_ids'] ) {
			$selected_post_ids = array_map( 'intval', explode( ',', $request_data['post_ids'] ) );
		}

		// Set whether WordPress options should be included in batch or not.
		$this->should_include_wp_options( $batch, $request_data );

		// Posts that was previously in this batch.
		$old_post_ids = $this->batch_dao->get_post_meta( $batch->get_id(), 'sme_selected_post' );

		// Post IDs to add to this batch.
		$add_post_ids = array_diff( $selected_post_ids, $old_post_ids );

		// Post IDs to remove from this batch.
		$remove_post_ids = array_diff( $old_post_ids, $selected_post_ids );

		// Add post IDs to batch.
		foreach ( $add_post_ids as $post_id ) {
			$this->batch_dao->add_post_meta( $batch->get_id(), 'sme_selected_post', $post_id );
		}

		// Remove post IDs from batch.
		foreach ( $remove_post_ids as $post_id ) {
			$this->batch_dao->delete_post_meta( $batch->get_id(), 'sme_selected_post', $post_id );
		}
	}

	/**
	 * Should batch include WordPress options.
	 *
	 * @param Batch $batch
	 * @param array $request
	 */
	private function should_include_wp_options( Batch $batch, $request ) {

		$include_wp_options = 'no';

		if ( isset( $request['include_wp_options'] ) ) {
			$include_wp_options = 'yes';
		}

		update_post_meta( $batch->get_id(), '_sme_include_wp_options', $include_wp_options );
	}

	/**
	 * Get WordPress options settings.
	 *
	 * @param Batch $batch
	 * @return array Associative array containing string values only.
	 * The following keys will always be available:
	 * - checked
	 * - title
	 * - description
	 */
	private function get_wp_options_settings( Batch $batch ) {

		$settings = array(
			'title'       => '',
			'description' => '',
			'checked'     => '',
		);

		$settings['title'] = __( 'WordPress Options', 'sme-content-staging' );

		$settings['description'] = sprintf(
			wp_kses(
				__(
					'Include WordPress options in batch. Select what options to sync on the <a href="%s">Content Staging WordPress Options</a> page.',
					'sme-content-staging'
				),
				array( 'a' => array( 'href' => array() ) )
			),
			esc_url( admin_url( 'admin.php?page=sme-wp-options' ) )
		);

		// Should WordPress options be included in batch.
		$wp_options_included = get_post_meta( $batch->get_id(), '_sme_include_wp_options', true );

		if ( $wp_options_included === 'yes' ) {
			$settings['checked'] = 'checked="checked"';
		}

		return $settings;
	}

	/**
	 * Pre-flight checks for a specific part of a batch.
	 *
	 * @param Batch $batch
	 * @param string $type
	 */
	private function verify_by_type( Batch $batch, $type ) {

		// The data we want to verify.
		$batch_chunk = array();

		// Get data we want to verify.
		switch ( $type ) {
			case 'attachments':
				$batch_chunk = $batch->get_attachments();
				break;
			case 'users':
				$batch_chunk = $batch->get_users();
				break;
			case 'posts':
				$batch_chunk = $batch->get_posts();
				break;
			case 'options':
				$batch_chunk = $batch->get_options();
		}

		// Verify selected part of batch.
		foreach ( $batch_chunk as $item ) {
			do_action( 'sme_verify_' . $type, $item, $batch );
		}
	}

}