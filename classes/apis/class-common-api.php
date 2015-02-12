<?php
namespace Me\Stenberg\Content\Staging\Apis;

use Exception;
use Me\Stenberg\Content\Staging\DB\Batch_DAO;
use Me\Stenberg\Content\Staging\DB\Post_DAO;
use Me\Stenberg\Content\Staging\DB\Postmeta_DAO;
use Me\Stenberg\Content\Staging\Helper_Factory;
use Me\Stenberg\Content\Staging\Importers\Batch_Importer_Factory;
use Me\Stenberg\Content\Staging\Managers\Batch_Mgr;
use Me\Stenberg\Content\Staging\Models\Batch;
use Me\Stenberg\Content\Staging\Models\Post;
use Me\Stenberg\Content\Staging\XMLRPC\Client;

class Common_API {

	/**
	 * @var Client
	 */
	private $client;

	/**
	 * @var Batch_DAO
	 */
	private $batch_dao;

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
	public function __construct() {
		$this->client       = Helper_Factory::get_instance()->get_client();
		$this->batch_dao    = Helper_Factory::get_instance()->get_dao( 'Batch' );
		$this->post_dao     = Helper_Factory::get_instance()->get_dao( 'Post' );
		$this->postmeta_dao = Helper_Factory::get_instance()->get_dao( 'Postmeta' );
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

	/* **********************************************************************
	 * Batch API
	 * **********************************************************************/

	public function create_batch() {
		return new Batch();
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
	public function prepare_batch( $batch ) {

		$mgr = new Batch_Mgr();
		$mgr->prepare( $batch );

		// Let third-party developers filter batch data.
		$batch->set_posts( apply_filters( 'sme_prepare_posts', $batch->get_posts() ) );
		$batch->set_attachments( apply_filters( 'sme_prepare_attachments', $batch->get_attachments() ) );
		$batch->set_users( apply_filters( 'sme_prepare_users', $batch->get_users() ) );

		/*
		 * Delete any previous pre-flight or deploy messages and deploy status
		 * just in case this batch has been imported once before.
		 */
		$this->delete_messages( $batch->get_id() );
		$this->delete_deploy_status( $batch->get_id() );

		/*
		 * Let third party developers perform actions before pre-flight. This is
		 * most often where third-party developers would add custom data.
		 */
		do_action( 'sme_prepare', $batch );
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

		// Start building request to send to production.
		$request = array(
			'batch'       => $batch,
			'auto_import' => $auto_import,
		);

		$this->client->request( 'smeContentStaging.import', $request );
		$response = $this->client->get_response_data();

		// Batch deploy in progress.
		do_action( 'sme_deploying', $batch );

		/*
		 * Batch has been deployed and should no longer be accessible by user,
		 * delete it (not actually deleting the batch, just setting it to draft
		 * to make it invisible to users).
		 */
		$this->batch_dao->delete_batch( $batch );

		return $response;
	}

	/**
	 * Trigger batch import on production.
	 *
	 * @param Batch $batch
	 */
	public function import( Batch $batch ) {
		$factory  = new Batch_Importer_Factory( $this, $this->batch_dao );
		$importer = $factory->get_importer( $batch );
		do_action( 'sme_import', $batch );
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
	 * @param int $batch_id
	 * @param int $status
	 */
	public function set_deploy_status( $batch_id, $status = 0 ) {
		update_post_meta( $batch_id, '_sme_deploy_status', $status );
	}

	/**
	 * @param int $batch_id
	 *
	 * @return int
	 */
	public function get_deploy_status( $batch_id ) {
		return get_post_meta( $batch_id, '_sme_deploy_status', true );
	}

	/**
	 * Delete deploy status for a specific batch.
	 *
	 * @param int    $batch_id
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
	 * @param string $type    Not supported at the moment.
	 * @param string $group
	 * @param int    $code    Not supported at the moment.
	 *
	 * @return array
	 */
	public function get_messages( $post_id, $type = null, $group = null, $code = 0 ) {

		$messages = array();
		$key      = $this->get_message_key( $group );

		// If a group has been set, only fetch messages for that group.
		if ( $group ) {
			return $this->postmeta_dao->get_post_meta( $post_id, $key );
		}

		$meta = $this->postmeta_dao->get_post_meta( $post_id );

		foreach ( $meta as $meta_key => $values ) {
			if ( strpos( $meta_key, $key ) === 0 ) {
				foreach ( $values as $message ) {
					array_push( $messages, unserialize( $message ) );
				}
			}
		}

		return $messages;
	}

	/**
	 * @see get_messages()
	 *
	 * @param  int    $post_id
	 * @param  string $type
	 * @param  int    $code
	 *
	 * @return array
	 */
	public function get_preflight_messages( $post_id, $type = null, $code = 0 ) {
		$messages = $this->get_messages( $post_id, $type, 'preflight', $code );
		return apply_filters( 'sme_get_preflight_messages', $messages );
	}

	/**
	 * @see get_messages()
	 *
	 * @param int    $post_id
	 * @param string $type
	 * @param int    $code
	 *
	 * @return array
	 */
	public function get_deploy_messages( $post_id, $type = null, $code = 0 ) {
		$messages = $this->get_messages( $post_id, $type, 'deploy', $code );
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

}