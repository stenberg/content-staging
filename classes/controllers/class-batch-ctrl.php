<?php
namespace Me\Stenberg\Content\Staging\Controllers;

use Exception;
use Me\Stenberg\Content\Staging\Apis\Common_API;
use Me\Stenberg\Content\Staging\DB\Batch_DAO;
use Me\Stenberg\Content\Staging\DB\Post_DAO;
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
	 * @var Batch_Mgr
	 */
	private $batch_mgr;

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
	 * @param Template $template
	 * @param Batch_Importer_Factory $importer_factory
	 */
	public function __construct( Template $template, Batch_Importer_Factory $importer_factory ) {
		$this->template         = $template;
		$this->batch_mgr        = new Batch_Mgr();
		$this->importer_factory = $importer_factory;
		$this->api              = Helper_Factory::get_instance()->get_api( 'Common' );
		$this->xmlrpc_client    = Helper_Factory::get_instance()->get_client();
		$this->batch_dao        = Helper_Factory::get_instance()->get_dao( 'Batch' );
		$this->post_dao         = Helper_Factory::get_instance()->get_dao( 'Post' );
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

		$status  = apply_filters( 'sme_batch_list_statuses', array( 'publish' ) );
		$count   = $this->batch_dao->count( $status );
		$batches = $this->batch_dao->get_batches( $status, $order_by, $order, $per_page, $paged );

		// Prepare table of batches.
		$table        = new Batch_Table();
		$table->items = $batches;
		$table->set_bulk_actions( array( 'delete' => 'Delete' ) );
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

		$batch = $this->batch_mgr->get( $batch_id );

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
		$post_ids = $this->batch_dao->get_post_meta( $batch->get_id(), 'sme_selected_post_ids', true );

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

		$data = array(
			'batch'    => $batch,
			'label'    => $label,
			'filters'  => $filters,
			'table'    => $table,
			'post_ids' => implode( ',', $post_ids ),
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
		$batch    = $this->batch_mgr->get( $batch_id );

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

		// Check that the current request carries a valid nonce.
		check_admin_referer( 'sme-delete-batch', 'sme_delete_batch_nonce' );

		// Get batch ID from URL query param.
		$batch = $this->batch_mgr->get( $_GET['id'] );

		// Delete batch.
		$this->batch_dao->delete_batch( $batch );

		// Redirect user.
		wp_redirect( admin_url( 'admin.php?page=sme-list-batches' ) );
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
		$batch = $this->batch_mgr->get( $_GET['id'] );

		// Data to be passed to view.
		$data = array( 'batch' => $batch );

		// Render view.
		$this->template->render( 'delete-batch', $data );
	}

	/**
	 * Prepare batch for pre-flight.
	 *
	 * Batch will be sent from content stage to production.
	 * Display messages returned by production.
	 *
	 * Runs on content stage.
	 */
	public function prepare() {

		// Make sure a query param ID exists in current URL.
		if ( ! isset( $_GET['id'] ) ) {
			wp_die( __( 'No batch ID has been provided.', 'sme-content-staging' ) );
		}

		// Get batch from database.
		$batch = $this->batch_dao->find( $_GET['id'] );

		// Populate batch with actual data.
		$this->api->prepare( $batch );

		// Send batch to production.
		$response = $this->api->transfer( $batch );

		// Get status received from production.
		$prod_status = ( isset( $response['status'] ) ? $response['status'] : 1 );

		// Get status from content stage.
		$stage_status = $this->api->get_preflight_status( $batch->get_id() );

		// Final status.
		$status = ( $stage_status != 2 ) ? $prod_status : $stage_status;

		// Get messages received from production.
		$prod_messages = ( isset( $response['messages'] ) ? $response['messages'] : array() );

		// Get messages from content stage.
		$stage_messages = $this->api->get_preflight_messages( $batch->get_id() );

		// Merge messages from content stage and production.
		$messages = array_merge( $prod_messages, $stage_messages );

		// Convert message objects into arrays.
		$messages = $this->api->convert_messages( $messages );

		// Render page.
		$this->template->render(
			'preflight-batch',
			array(
				'batch'    => $batch,
				'status'   => $status,
				'messages' => $messages,
			)
		);
	}

	/**
	 * Store batch on production. Triggered during pre-flight.
	 *
	 * Runs on production.
	 *
	 * @param array $args
	 *
	 * @return array
	 */
	public function store( array $args ) {

		if ( $messages = $this->xmlrpc_client->handle_request( $args ) ) {
			return $messages;
		}

		$result = $this->xmlrpc_client->get_request_data();

		// Check if a batch has been provided.
		if ( ! isset( $result['batch'] ) || ! ( $result['batch'] instanceof Batch ) ) {
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

		// Store batch on production.
		return $this->api->store( $batch );
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
	public function preflight() {

		// Get batch ID.
		$batch_id = intval( $_POST['batch_id'] );

		// Get batch GUID.
		$batch_guid = $_POST['batch_guid'];

		// Hook in before pre-flight is carried out on production.
		do_action( 'sme_preflight', $batch_guid );

		// Pre-flight batch.
		$result = $this->api->preflight( $batch_guid );

		// Pre-flight status.
		$status = 2;

		// Get status from production.
		$prod_status = ( isset( $result['status'] ) ) ? $result['status'] : 2;

		// Get status from content stage.
		$stage_status = $this->api->get_preflight_status( $batch_id );

		// Ensure no pre-flight status is not set to failed.
		if ( $prod_status != 2 && $stage_status != 2 ) {
			$status = 3;
		}

		// Get production messages.
		$prod_messages = ( isset( $result['messages'] ) ) ? $result['messages'] : array();

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
			'messages' => $messages,
		);

		// Filter response.
		$response = apply_filters( 'sme_preflighted', $response, $batch_guid );

		// Convert message objects into arrays.
		$response['messages'] = $this->api->convert_messages( $response['messages'] );

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
		if ( ! isset( $result['batch_guid'] ) ) {
			$message = new Message();
			$message->set_level( 'error' );
			$message->set_message( 'No batch GUID provided.' );

			return $this->xmlrpc_client->prepare_response(
				array(
					'status' => 2,
					'messages' => array( $message ),
				)
			);
		}

		// Get batch GUID.
		$batch_guid = $result['batch_guid'];

		// Check if a production version of this batch exists.
		$batch = $this->batch_dao->get_by_guid( $batch_guid );

		// Batch could not be found.
		if ( ! $batch ) {
			$message = new Message();
			$message->set_level( 'error' );
			$message->set_message( sprintf( 'No batch with GUID %s found.', $batch_guid ) );

			return $this->xmlrpc_client->prepare_response(
				array(
					'status' => 2,
					'messages' => array( $message ),
				)
			);
		}

		// Hook in when batch is ready for pre-flight.
		do_action( 'sme_verify', $batch );

		// What different type of data needs verification?
		$types = array( 'attachments', 'users', 'posts' );

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

		$batch = $this->batch_mgr->get();

		$batch->set_title( 'Quick Deploy ' . current_time( 'mysql' ) );
		$this->batch_dao->insert( $batch );

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
		$messages = ( isset( $response['messages'] ) ? $response['messages'] : 0 );

		// Render page.
		$this->template->render(
			'deploy-batch',
			array(
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

		$batch->set_id( null );
		$revision = $this->batch_dao->get_by_guid( $batch->get_guid() );

		if ( $revision !== null ) {
			/*
			 * Updating existing batch on production.
			 */
			$batch->set_id( $revision->get_id() );
			$this->batch_dao->update_batch( $batch );

			unset( $revision );
		} else {
			/*
			 * Creating new batch on production.
			 */
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

		$request = array(
			'batch_id' => $batch_id,
		);

		$this->xmlrpc_client->request( 'smeContentStaging.importStatus', $request );
		$response = $this->xmlrpc_client->get_response_data();
		$response = apply_filters( 'sme_deploy_status', $response );

		// Deploy status.
		$status = 2;

		// Get status from production.
		$prod_status = ( isset( $response['status'] ) ) ? $response['status'] : 2;

		// Get status from content stage.
		$stage_status = $this->api->get_deploy_status( $batch_id );

		// Ensure no pre-flight status is not set to failed.
		if ( $prod_status != 2 && $stage_status != 2 ) {
			$status = $prod_status;
		}

		// Get production messages.
		$prod_messages = ( isset( $response['messages'] ) ) ? $response['messages'] : array();

		// Get content stage messages.
		$stage_messages = $this->api->get_deploy_messages( $batch_id );

		// All pre-flight messages.
		$messages = array_merge( $prod_messages, $stage_messages );

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
	 * Add a post ID to batch.
	 *
	 * Triggered by an AJAX call.
	 */
	public function include_post() {

		if ( ! isset( $_POST['include'] ) || ! isset( $_POST['batch_id'] ) || ! isset( $_POST['post_id'] ) ) {
			die();
		}

		$batch_id    = null;
		$post_id     = intval( $_POST['post_id'] );
		$is_selected = false;

		if ( $_POST['batch_id'] ) {
			$batch_id = intval( $_POST['batch_id'] );
		}

		if ( $_POST['include'] === 'true' ) {
			$is_selected = true;
		}

		// Get batch.
		$batch = $this->batch_mgr->get( $batch_id );

		// Create new batch if needed.
		if ( ! $batch->get_id() ) {
			$this->batch_dao->insert( $batch );
		}

		// Get IDs of posts already included in the batch.
		$post_ids = $this->batch_dao->get_post_meta( $batch->get_id(), 'sme_selected_post_ids', true );

		if ( ! $post_ids ) {
			$post_ids = array();
		}

		if ( $is_selected ) {
			// Add post ID.
			$post_ids[] = $post_id;
		} else {
			// Remove post ID.
			if ( ( $key = array_search( $post_id, $post_ids ) ) !== false ) {
				unset( $post_ids[$key] );
			}
		}

		$post_ids = array_unique( $post_ids );

		// Update batch meta with IDs of posts user selected to include in batch.
		$this->batch_dao->update_post_meta( $batch->get_id(), 'sme_selected_post_ids', $post_ids );

		header( 'Content-Type: application/json' );
		echo json_encode( array( 'batchId' => $batch->get_id() ) );

		die(); // Required to return a proper result.
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
		$post_ids = array();

		// Check if any posts to include in batch has been selected.
		if ( isset( $request_data['post_ids'] ) && $request_data['post_ids'] ) {
			$post_ids = array_map( 'intval', explode( ',', $request_data['post_ids'] ) );
		}

		// Update batch meta with IDs of posts user selected to include in batch.
		$this->batch_dao->update_post_meta( $batch->get_id(), 'sme_selected_post_ids', $post_ids );
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
		}

		// Verify selected part of batch.
		foreach ( $batch_chunk as $item ) {
			do_action( 'sme_verify_' . $type, $item, $batch );
		}
	}

}