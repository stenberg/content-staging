<?php
namespace Me\Stenberg\Content\Staging\Controllers;

use Me\Stenberg\Content\Staging\DB\Batch_DAO;
use Me\Stenberg\Content\Staging\DB\Batch_Import_Job_DAO;
use Me\Stenberg\Content\Staging\Helper_Factory;
use Me\Stenberg\Content\Staging\Importers\Batch_Importer_Factory;
use Me\Stenberg\Content\Staging\Managers\Batch_Mgr;
use Me\Stenberg\Content\Staging\Models\Batch;
use Me\Stenberg\Content\Staging\Models\Batch_Import_Job;
use Me\Stenberg\Content\Staging\View\Batch_Table;
use Me\Stenberg\Content\Staging\DB\Post_DAO;
use Me\Stenberg\Content\Staging\View\Post_Table;
use Me\Stenberg\Content\Staging\View\Template;
use Me\Stenberg\Content\Staging\XMLRPC\Client;

class Batch_Ctrl {

	private $template;
	private $batch_mgr;
	private $xmlrpc_client;
	private $importer_factory;
	private $batch_import_job_dao;
	private $batch_dao;
	private $post_dao;

	public function __construct( Template $template, Client $xmlrpc_client, Batch_Importer_Factory $importer_factory ) {
		$this->template             = $template;
		$this->batch_mgr            = new Batch_Mgr();
		$this->xmlrpc_client        = $xmlrpc_client;
		$this->importer_factory     = $importer_factory;
		$this->batch_import_job_dao = Helper_Factory::get_instance()->get_dao( 'Batch_Import_Job' );
		$this->batch_dao            = Helper_Factory::get_instance()->get_dao( 'Batch' );
		$this->post_dao             = Helper_Factory::get_instance()->get_dao( 'Post' );
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

		// Get posts user can select to include in the batch.
		$posts       = $this->post_dao->get_published_posts( $order_by, $order, $per_page, $paged, $post_ids );
		$total_posts = $this->post_dao->get_published_posts_count();
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
		$batch    = $this->batch_mgr->get_batch( $batch_id, true );

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
		$batch = $this->batch_mgr->get_batch( $_GET['id'], true );

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
		$batch = $this->batch_mgr->get_batch( $_GET['id'], true );

		// Data to be passed to view.
		$data = array( 'batch' => $batch );

		// Render view.
		$this->template->render( 'delete-batch', $data );
	}

	/**
	 * Prepare batch for pre-flight.
	 *
	 * Send batch from content staging environment to production. Production
	 * will evaluate the batch and look for any issues that might cause
	 * trouble when user later on deploys the batch.
	 *
	 * Display any pre-flight messages that is returned by production.
	 *
	 * @param Batch $batch
	 */
	public function prepare( $batch = null ) {

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

		$job = new Batch_Import_Job();
		$job->set_batch( $batch );

		/*
		 * Let third party developers perform actions before pre-flight. This is
		 * most often when users would add custom data.
		 */
		do_action( 'sme_prepare', $job );

		// Send batch to production for verification.
		if ( $job->get_status() !== 2 ) {
			$this->send_verification_request( $job );
		}

		/*
		 * Let third party developers perform actions after pre-flight has
		 * completed.
		 */
		do_action( 'sme_prepared', $job->get_batch() );

		// Add batch data to database if pre-flight was successful.
		if ( $job->get_status() !== 2 ) {
			$this->batch_dao->update_batch( $job->get_batch() );
			$job->add_message( 'Pre-flight successful!', 'success' );
		}

		// Prepare data we want to pass to view.
		$data = array(
			'batch'      => $job->get_batch(),
			'messages'   => $job->get_messages(),
			'is_success' => ( $job->get_status() !== 2 ),
		);

		$this->template->render( 'preflight-batch', $data );
	}

	/**
	 * Runs on production when a pre-flight request has been received.
	 *
	 * @param array $args
	 * @return string
	 */
	public function verify( array $args ) {

		if ( $messages = $this->xmlrpc_client->handle_request( $args ) ) {
			return $messages;
		}

		$result = $this->xmlrpc_client->get_request_data();

		// Check if a batch has been provided.
		if ( ! isset( $result['batch'] ) || ! ( $result['batch'] instanceof Batch ) ) {
			return $this->xmlrpc_client->prepare_response(
				array( array( 'level' => 'error', 'message' => 'Invalid batch!' ) )
			);
		}

		// Get batch.
		$batch = $result['batch'];

		// Create importer.
		$job = new Batch_Import_Job();
		$job->set_batch( $batch );

		/*
		 * Let third party developers perform actions before any pre-flight
		 * checks are done.
		 */
		do_action( 'sme_verify', $job );

		foreach ( $batch->get_posts() as $post ) {
			// Check if parent post exist on production or in batch.
			if ( ! $this->parent_post_exists( $post, $batch->get_posts() ) ) {
				$job->add_message(
					sprintf(
						'Post <a href="%s" target="_blank">%s</a> has a parent post that does not exist on production and is not part of this batch. Include post <a href="%s" target="_blank">%s</a> in this batch to resolve this issue.',
						$batch->get_backend() . 'post.php?post=' . $post->get_id() . '&action=edit',
						$post->get_title(),
						$batch->get_backend() . 'post.php?post=' . $post->get_parent()->get_id() . '&action=edit',
						$post->get_parent()->get_title()
					),
					'error'
				);
			}
		}

		// Pre-flight custom data.
		foreach ( $job->get_batch()->get_custom_data() as $addon => $data ) {
			do_action( 'sme_verify_' . $addon, $data, $job );
		}

		/*
		 * Let third party developers perform actions before pre-flight data is
		 * returned from production to content stage.
		 */
		do_action( 'sme_verified', $job );

		// Prepare and return the XML-RPC response data.
		return $this->xmlrpc_client->prepare_response( $job->get_messages() );
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

		$batch = $this->batch_mgr->get_batch();

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
	 *
	 * @todo Consider sending form data to a different method responsible for
	 * saving the pre-flighted batch data. When data has been saved, redirect
	 * user here, fetch data from database and send it to production. This
	 * would more closely resemble how we handle e.g. editing a batch.
	 */
	public function deploy() {

		// Check that the current request carries a valid nonce.
		check_admin_referer( 'sme-deploy-batch', 'sme_deploy_batch_nonce' );
		$batch = null;

		/*
		 * Batch data is sent through a form on the pre-flight page and picked up
		 * here. Decode data.
		 */
		if ( ! isset( $_GET['id'] )
			 || ! ( $batch = $this->batch_dao->find( $_GET['id'] ) )
			 || $batch->get_status() != 'publish' ) {
			wp_die( __( 'No batch found.', 'sme-content-staging' ) );
		}

		$batch = unserialize( base64_decode( $batch->get_content() ) );

		/*
		 * Give third-party developers the option to import images before batch
		 * is sent to production.
		 */
		do_action( 'sme_deploy_custom_attachment_importer', $batch->get_attachments(), $batch );

		/*
		 * Make it possible for third-party developers to alter the list of
		 * attachments to deploy.
		 */
		$batch->set_attachments(
			apply_filters( 'sme_deploy_attachments', $batch->get_attachments(), $batch )
		);

		$request = array(
			'batch'  => $batch,
		);

		$this->xmlrpc_client->query( 'smeContentStaging.import', $request );
		$response = $this->xmlrpc_client->get_response_data();

		/*
		 * Batch has been deployed and should no longer be accessible by user,
		 * delete it (not actually deleting the batch, just setting it to draft
		 * to make it invisible to users).
		 */
		$this->batch_dao->delete_batch( $batch );

		$data = array(
			'messages' => $response['messages'],
		);

		$this->template->render( 'deploy-batch', $data );
	}

	/**
	 * Runs on production when a deploy request has been received.
	 *
	 * @param array $args
	 * @return string
	 */
	public function import( array $args ) {

		$job           = null;
		$importer_type = null;
		$this->xmlrpc_client->handle_request( $args );
		$result = $this->xmlrpc_client->get_request_data();

		if ( isset( $result['job_id'] ) ) {
			$job = $this->batch_import_job_dao->find( intval( $result['job_id'] ) );
		}

		if ( ! $job ) {
			$job = $this->create_import_job( $result );
		}

		if ( $job->get_status() !== 2 ) {

			if ( isset( $result['importer'] ) ) {
				$importer_type = $result['importer'];
			}

			$importer = $this->importer_factory->get_importer( $job, $importer_type );

			if ( $job->get_status() === 0 ) {
				$job->add_message(
					sprintf(
						'Starting batch import...<span id="sme-batch-importer-type" class="hidden">%s</span>',
						$importer->get_type()
					),
					'info'
				);
				$this->batch_import_job_dao->update_job( $job );
			}

			$importer->run();
		}

		$response = array(
			'status'   => $job->get_status(),
			'messages' => $job->get_messages(),
		);

		// Prepare and return the XML-RPC response data.
		return $this->xmlrpc_client->prepare_response( $response );
	}

	/**
	 * Output the status of an import job together with any messages
	 * generated during import.
	 *
	 * Triggered by an AJAX call.
	 *
	 * Runs on staging environment.
	 */
	public function import_request() {

		$request = array(
			'job_id'   => intval( $_POST['job_id'] ),
			'importer' => $_POST['importer'],
		);

		$this->xmlrpc_client->query( 'smeContentStaging.import', $request );
		$response = $this->xmlrpc_client->get_response_data();

		if ( isset( $response['status'] ) && $response['status'] > 1 ) {
			do_action( 'sme_deployed' );
		}

		header( 'Content-Type: application/json' );
		echo json_encode( $response );

		die(); // Required to return a proper result.
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
		$batch = $this->batch_mgr->get_batch( $batch_id, true );

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
	 * Send a batch to production for verification. Batch is checked for
	 * potential issues on production and messages that can be displayed to
	 * the user is returned to content stage.
	 *
	 * @param Batch_Import_Job $job
	 */
	private function send_verification_request( Batch_Import_Job $job ) {
		$request = array(
			'batch' => $job->get_batch(),
		);

		$this->xmlrpc_client->query( 'smeContentStaging.verify', $request );
		$messages = $this->xmlrpc_client->get_response_data();

		foreach ( $messages as $message ) {
			if ( $message['level'] == 'error' ) {
				$job->set_status( 2 );
			}
			$job->add_message( $message['message'], $message['level'] );
		}
	}

	/**
	 * Checks running on content stage before a batch is sent to production
	 * for verification.
	 *
	 * @param Batch $batch
	 * @return array
	 */
	private function prepare_checks( Batch $batch ) {
		$messages = array();

		foreach ( $batch->get_attachments() as $attachment ) {
			foreach ( $attachment['items'] as $item ) {
				$url = $attachment['url'] . '/' . $item;
				// Check if attachment exists on content stage.
				if ( ! $this->attachment_exists( $url ) ) {
					$messages[] = array(
						'level'   => 'warning',
						'message' => 'Attachment <a href="' . $url . '" target="_blank">' . $url . '</a> is missing on content stage and will not be deployed to production.',
					);
				}
			}
		}

		return $messages;
	}

	/**
	 * Runs on production when an import status request has been received.
	 *
	 * @param array $result
	 * @return Batch_Import_Job
	 */
	private function create_import_job( $result ) {

		$job = new Batch_Import_Job();

		// Check if a batch has been provided.
		if ( ! isset( $result['batch'] ) || ! ( $result['batch'] instanceof Batch ) ) {
			$job->add_message( 'Failed creating import job.', 'error' );
			$job->set_status( 2 );
			return $job;
		}

		$job->set_batch( $result['batch'] );
		$this->batch_import_job_dao->insert( $job );
		$job->add_message(
			sprintf(
				'Created import job ID: <span id="sme-batch-import-job-id">%s</span>',
				$job->get_id()
			),
			'info'
		);
		return $job;
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
		if ( $post->get_parent() === null ) {
			return true;
		}

		// Check if parent post exist on production server.
		if ( $this->post_dao->get_by_guid( $post->get_parent()->get_guid() ) ) {
			return true;
		}

		// Parent post is not on production, look in this batch for parent post.
		foreach ( $posts as $item ) {
			if ( $item->get_id() == $post->get_parent()->get_id() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if an attachment exists.
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