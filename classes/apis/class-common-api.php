<?php
namespace Me\Stenberg\Content\Staging\Apis;

use Exception;
use Me\Stenberg\Content\Staging\DB\Batch_DAO;
use Me\Stenberg\Content\Staging\DB\Message_DAO;
use Me\Stenberg\Content\Staging\DB\Post_DAO;
use Me\Stenberg\Content\Staging\DB\Postmeta_DAO;
use Me\Stenberg\Content\Staging\Factories\DAO_Factory;
use Me\Stenberg\Content\Staging\Importers\Batch_Importer_Factory;
use Me\Stenberg\Content\Staging\Managers\Batch_Mgr;
use Me\Stenberg\Content\Staging\Models\Batch;
use Me\Stenberg\Content\Staging\Models\Message;
use Me\Stenberg\Content\Staging\Models\Post;
use Me\Stenberg\Content\Staging\XMLRPC\Client;

class Common_API {

	/**
	 * @var Client
	 */
	private $client;

	/**
	 * @var Batch_Mgr
	 */
	private $batch_mgr;

	/**
	 * @var DAO_Factory
	 */
	private $dao_factory;

	/**
	 * @var Batch_DAO
	 */
	private $batch_dao;

	/**
	 * @var Message_DAO
	 */
	private $message_dao;

	/**
	 * @var Post_DAO
	 */
	private $post_dao;

	/**
	 * @var Postmeta_DAO
	 */
	private $postmeta_dao;

	/**
	 * Constructor.
	 */
	public function __construct( Client $client, DAO_Factory $dao_factory ) {
		$this->client       = $client;
		$this->dao_factory  = $dao_factory;
		$this->batch_dao    = $dao_factory->create( 'Batch' );
		$this->message_dao  = $dao_factory->create( 'Message' );
		$this->post_dao     = $dao_factory->create( 'Post' );
		$this->postmeta_dao = $dao_factory->create( 'Postmeta' );
		$this->batch_mgr    = new Batch_Mgr( $this, $dao_factory );
	}

	/* **********************************************************************
	 * Common API
	 * **********************************************************************/

	/**
	 * Find post by GUID.
	 *
	 * @param string $guid
	 * @return Post
	 */
	public function get_post_by_guid( $guid ) {
		return $this->post_dao->get_by_guid( $guid );
	}

	/**
	 * Find post ID by providing GUID.
	 *
	 * @param string $guid
	 *
	 * @return int
	 */
	public function get_post_id_by_guid( $guid ) {
		return $this->post_dao->get_id_by_guid( $guid );
	}

	/* **********************************************************************
	 * Batch API
	 * **********************************************************************/

	public function create_batch() {
		return new Batch();
	}

	public function get_batch( $id = null ) {
		return $this->batch_mgr->get( $id );
	}

	/**
	 * Prepare a batch.
	 *
	 * Here we take all available information about a batch and use it to
	 * populate the batch with actual content. Example on available
	 * information would be what post IDs should be included in the batch.
	 *
	 * Runs on content stage.
	 *
	 * @param Batch $batch
	 */
	public function prepare( $batch ) {

		// Hook in before batch is built
		do_action( 'sme_prepare', $batch );

		$this->batch_mgr->prepare( $batch );

		// Let third-party developers filter batch data.
		$batch->set_posts( apply_filters( 'sme_prepare_posts', $batch->get_posts() ) );
		$batch->set_attachments( apply_filters( 'sme_prepare_attachments', $batch->get_attachments() ) );
		$batch->set_users( apply_filters( 'sme_prepare_users', $batch->get_users() ) );

		// Hook in after batch has been built.
		do_action( 'sme_prepared', $batch );

		// Store prepared batch.
		$this->batch_dao->update_batch( $batch );
	}

	/**
	 * Perform pre-flight.
	 *
	 * @param Batch $batch
	 *
	 * @return array
	 */
	public function preflight( $batch ) {

		// Hook in before batch is sent
		do_action( 'sme_preflight', $batch );

		$request = array(
			'batch' => $batch,
		);

		$response = $this->client->request( 'smeContentStaging.verify', $request );

		// Hook in after batch has been transferred.
		$response = apply_filters( 'sme_preflighted', $response, $batch );

		// Get status received from production.
		$status = ( isset( $response['status'] ) ? $response['status'] : 2 );

		// Get messages received from production.
		$messages = ( isset( $response['messages'] ) ? $response['messages'] : array() );

		// Return status and messages.
		return array(
			'status'   => $status,
			'messages' => $messages,
		);
	}

	/**
	 * Deploy a batch from content stage to production.
	 *
	 * Runs on content stage when a deploy request has been received.
	 *
	 * @param Batch $batch
	 * @param bool  $auto_import If set to false the batch will be sent to production but the import
	 *                           will not be triggered.
	 *
	 * @return array
	 */
	public function deploy( Batch $batch, $auto_import = true ) {

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

		// Hook in before deploy.
		do_action( 'sme_deploy', $batch );

		// Start building request to send to production.
		$request = array(
			'batch'       => $batch,
			'auto_import' => $auto_import,
		);

		$response = $this->client->request( 'smeContentStaging.import', $request );

		// Batch deploy in progress.
		$response = apply_filters( 'sme_deploying', $response, $batch );

		// Get status received from production.
		$status = ( isset( $response['status'] ) ? $response['status'] : 1 );

		// Get messages received from production.
		$messages = ( isset( $response['messages'] ) ? $response['messages'] : array() );

		// Delete batch after deploy.
		$delete_batch = apply_filters( 'sme_delete_batch_after_deploy', true );

		/*
		 * Batch has been deployed and should no longer be accessible by user,
		 * delete it (not actually deleting the batch, just setting it to draft
		 * to make it invisible to users).
		 */
		if ( $delete_batch === true ) {
			$this->batch_dao->delete_batch( $batch );
		}

		// Return status and messages.
		return array(
			'status'   => $status,
			'messages' => $messages,
		);
	}

	/**
	 * Trigger batch import.
	 *
	 * Runs on production.
	 *
	 * @param Batch $batch
	 */
	public function import( Batch $batch ) {

		do_action( 'sme_import', $batch );

		$message = sprintf(
			'Prepare import on %s (ID: <span id="sme-batch-id">%s</span>)',
			get_bloginfo( 'name' ),
			$batch->get_id()
		);

		$this->add_deploy_message( $batch->get_id(), $message, 'info', 100 );

		$factory  = new Batch_Importer_Factory( $this, $this->dao_factory );
		$importer = $factory->get_importer( $batch );

		$importer->run();
	}

	/* **********************************************************************
	 * Status API
	 * **********************************************************************/

	/**
	 * Set status for a batch.
	 *
	 * @deprecated
	 *
	 * @param int $batch_id
	 * @param bool
	 */
	public function set_batch_status( $batch_id, $status ) {
		update_post_meta( $batch_id, '_sme_batch_status', intval( $status ) );
	}

	/**
	 * Get status for a batch.
	 *
	 * @deprecated
	 *
	 * @param $batch_id
	 * @return bool
	 */
	public function is_valid_batch( $batch_id ) {
		$status = get_post_meta( $batch_id, '_sme_batch_status', true );
		if ( ! $status || $status == 1 ) {
			return true;
		}
		return false;
	}

	/**
	 * Set status for pre-flight.
	 *
	 * @param int $batch_id
	 * @param int $status
	 */
	public function set_preflight_status( $batch_id, $status = 0 ) {
		update_post_meta( $batch_id, '_sme_preflight_status', $status );
	}

	/**
	 * Set status for deploy.
	 *
	 * @param int $batch_id
	 * @param int $status
	 */
	public function set_deploy_status( $batch_id, $status = 0 ) {
		update_post_meta( $batch_id, '_sme_deploy_status', $status );
	}

	/**
	 * Get pre-flight status.
	 *
	 * @param int $batch_id
	 *
	 * @return int
	 */
	public function get_preflight_status( $batch_id ) {
		$status = get_post_meta( $batch_id, '_sme_preflight_status', true );
		return intval( $status );
	}

	/**
	 * Get deploy status.
	 *
	 * @param int $batch_id
	 *
	 * @return int
	 */
	public function get_deploy_status( $batch_id ) {
		$status = get_post_meta( $batch_id, '_sme_deploy_status', true );
		return intval( $status );
	}

	/**
	 * Delete pre-flight status for a specific batch.
	 *
	 * @param int $batch_id
	 *
	 * @return bool
	 */
	public function delete_preflight_status( $batch_id ) {
		return delete_post_meta( $batch_id, '_sme_preflight_status' );
	}

	/**
	 * Delete deploy status for a specific batch.
	 *
	 * @param int $batch_id
	 *
	 * @return bool
	 */
	public function delete_deploy_status( $batch_id ) {
		return delete_post_meta( $batch_id, '_sme_deploy_status' );
	}

	/* **********************************************************************
	 * Message API
	 * **********************************************************************/

	/**
	 * Add a message.
	 *
	 * Messages will be displayed to the user through the UI.
	 *
	 * @param int    $post_id Post this message belongs to.
	 * @param string $message The message.
	 * @param string $type    What type of message this is, can be any of:
	 *                        info, warning, error, success
	 * @param string $group   Group this message is part of, use this to categorize different kind
	 *                        of messages, e.g. preflight, deploy, etc.
	 * @param int    $code    Set a specific code for this message.
	 *
	 * @throws Exception
	 */
	public function add_message( $post_id, $message, $type = 'info', $group = null, $code = 0 ) {

		// Supported message types.
		$types = array( 'info', 'warning', 'error', 'success' );
		$types = apply_filters( 'sme_message_types', $types );

		if ( ! in_array( $type, $types ) ) {
			throw new Exception( 'Unsupported message type: ' . $type );
		}

		$key = $this->get_message_key( $group );

		$value = array(
			'message' => $message,
			'level'   => $type,
			'code'    => $code,
		);

		$this->postmeta_dao->add_post_meta( $post_id, $key, $value );
	}

	/**
	 * Add a pre-flight message.
	 *
	 * @see add_message()
	 *
	 * @param int    $post_id
	 * @param string $message
	 * @param string $type
	 * @param int    $code
	 */
	public function add_preflight_message( $post_id, $message, $type = 'info', $code = 0 ) {
		$this->add_message( $post_id, $message, $type, 'preflight', $code );
	}

	/**
	 * Add a deploy message.
	 *
	 * @see add_message()
	 *
	 * @param int    $post_id
	 * @param string $message
	 * @param string $type
	 * @param int    $code
	 */
	public function add_deploy_message( $post_id, $message, $type = 'info', $code = 0 ) {
		$this->add_message( $post_id, $message, $type, 'deploy', $code );
	}

	/**
	 * Get messages for a specific post.
	 *
	 * @param int    $post_id
	 * @param bool   $new_only Only fetch messages that has not been fetched before.
	 * @param string $type     Not supported at the moment.
	 * @param string $group
	 * @param int    $code     Not supported at the moment.
	 *
	 * @return array
	 */
	public function get_messages( $post_id, $new_only = true, $type = null, $group = null, $code = 0 ) {
		$messages = $this->message_dao->get_by_post_id( $post_id, $new_only, $type, $group, $code );
		return apply_filters( 'sme_get_messages', $messages );
	}

	/**
	 * @see get_messages()
	 *
	 * @param  int    $post_id
	 * @param  bool   $new_only
	 * @param  string $type
	 * @param  int    $code
	 *
	 * @return array
	 */
	public function get_preflight_messages( $post_id, $new_only = true, $type = null, $code = 0 ) {
		$messages = $this->get_messages( $post_id, $new_only, $type, 'preflight', $code );
		return apply_filters( 'sme_get_preflight_messages', $messages );
	}

	/**
	 * @see get_messages()
	 *
	 * @param int    $post_id
	 * @param bool   $new_only
	 * @param string $type
	 * @param int    $code
	 *
	 * @return array
	 */
	public function get_deploy_messages( $post_id, $new_only = true, $type = null, $code = 0 ) {
		$messages = $this->get_messages( $post_id, $new_only, $type, 'deploy', $code );
		return apply_filters( 'sme_get_deploy_messages', $messages );
	}

	/**
	 * Delete messages for a specific post.
	 *
	 * @param int    $post_id
	 * @param string $type    Not supported at the moment.
	 * @param string $group
	 * @param int    $code    Not supported at the moment.
	 *
	 * @return bool
	 */
	public function delete_messages( $post_id, $type = null, $group = null, $code = 0 ) {

		$key = $this->get_message_key( $group );

		if ( $group ) {
			delete_post_meta( $post_id, $key );
			return true;
		}

		$meta = get_post_meta( $post_id );

		foreach ( $meta as $meta_key => $values ) {
			if ( strpos( $meta_key, $key ) === 0 ) {
				delete_post_meta( $post_id, $meta_key );
			}
		}

		return true;
	}

	/**
	 * @see delete_messages()
	 *
	 * @param int    $post_id
	 * @param string $type
	 * @param int    $code
	 *
	 * @return bool
	 */
	public function delete_preflight_messages( $post_id, $type = null, $code = 0 ) {
		return $this->delete_messages( $post_id, $type, 'preflight', $code );
	}

	/**
	 * @see delete_messages()
	 *
	 * @param int    $post_id
	 * @param string $type
	 * @param int    $code
	 *
	 * @return bool
	 */
	public function delete_deploy_messages( $post_id, $type = null, $code = 0 ) {
		return $this->delete_messages( $post_id, $type, 'deploy', $code );
	}

	/**
	 * Convert message objects into arrays or from arrays into objects.
	 *
	 * @param array $messages
	 * @param bool  $to_array
	 *
	 * @return array
	 */
	public function convert_messages( $messages, $to_array = true ) {

		$result = array();

		if ( ! is_array( $messages ) ) {
			return $result;
		}

		// Convert message objects into arrays.
		if ( $to_array ) {
			foreach ( $messages as $message ) {
				if ( $message instanceof Message ) {
					array_push( $result, $message->to_array() );
				}
			}

			return $result;
		}

		// Convert messages into objects.
		foreach ( $messages as $message ) {
			$msg = new Message();

			$msg->set_id( ( isset( $message['id'] ) ? $message['id'] : null ) );
			$msg->set_message( ( isset( $message['message'] ) ? $message['message'] : '' ) );
			$msg->set_level( ( isset( $message['level'] ) ? $message['level'] : 'info' ) );
			$msg->set_code( ( isset( $message['code'] ) ? $message['code'] : 0 ) );

			array_push( $result, $msg );
		}

		return $result;
	}

	/**
	 * Filter out error messages from array of messages.
	 *
	 * @param array $messages
	 *
	 * @return array
	 */
	public function error_messages( array $messages ) {
		$errors = array_filter(
			$messages, function( Message $message ) {
				return ( $message->get_level() == 'error' );
			}
		);

		return $errors;
	}

	/**
	 * Get meta_key to use when searching for records in wp_postmeta.
	 *
	 * @param string $type
	 *
	 * @return string
	 */
	private function get_message_key( $type = null ) {

		// Default meta_key in wp_postmeta table.
		$key = '_sme_message';

		// Append _$type to $key if a $type has been set.
		if ( $type ) {
			$key .= '_' . $type;
		}

		return $key;
	}

	/* **********************************************************************
	 * Import API
	 * **********************************************************************/

	/**
	 * Ask Production for current deploy status and messages.
	 *
	 * Runs on Content Stage.
	 *
	 * @param int $batch_id
	 *
	 * @return array
	 */
	public function import_status_request( $batch_id ) {

		$request = array(
			'batch_id' => $batch_id,
		);

		$response = $this->client->request( 'smeContentStaging.importStatus', $request );
		$response = apply_filters( 'sme_deploy_status', $response );

		// Get production deploy status.
		$status = ( isset( $response['status'] ) ) ? $response['status'] : 2;

		// Get production deploy messages.
		$messages = ( isset( $response['messages'] ) ) ? $response['messages'] : array();

		return array(
			'status'   => $status,
			'messages' => $messages,
		);
	}

	/**
	 * Generate an import key that can be used in background imports.
	 *
	 * @param Batch $batch
	 *
	 * @return string
	 *
	 * @throws Exception
	 */
	public function generate_import_key( Batch $batch ) {

		if ( ! $batch->get_id() || ! $batch->get_modified_gmt() ) {
			throw new Exception( 'Failed generating batch import key.' );
		}

		$key = md5( $batch->get_id() . '-' . $batch->get_modified_gmt() . '-' . rand( 0, 100000 ) );
		update_post_meta( $batch->get_id(), '_sme_import_key', $key );

		return $key;
	}

	/**
	 * Get generated background import key.
	 *
	 * @param int $batch_id
	 *
	 * @return string
	 */
	public function get_import_key( $batch_id ) {
		return get_post_meta( $batch_id, '_sme_import_key', true );
	}

	/* **********************************************************************
	 * Settings API
	 * **********************************************************************/

	/**
	 * Check if we are currently on Content Stage or Production.
	 *
	 * @return bool
	 */
	public function is_content_stage() {

		if ( defined( 'CONTENT_STAGING_IS_STAGE' ) ) {
			return CONTENT_STAGING_IS_STAGE;
		}

		$is_stage = get_option( 'sme_cs_is_stage' );

		return $is_stage ? true : false;
	}

}