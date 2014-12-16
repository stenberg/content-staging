<?php
namespace Me\Stenberg\Content\Staging\Apis;

use Me\Stenberg\Content\Staging\Helper_Factory;
use Me\Stenberg\Content\Staging\Managers\Batch_Mgr;
use Me\Stenberg\Content\Staging\Models\Batch;
use Me\Stenberg\Content\Staging\Models\Post;

class Common_API {

	private $client;
	private $post_dao;
	private $postmeta_dao;

	public function __construct() {
		$this->client       = Helper_Factory::get_instance()->get_client();
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

	/**
	 * Prepare a batch.
	 *
	 * Here we take all available information about a batch and use it to
	 * populate the batch with actual content. Example on available
	 * information would be what post IDs should be included in the batch.
	 *
	 * Runs on content stage.
	 *
	 * @param int $id
	 * @return Batch
	 */
	public function prepare_batch( $id ) {

		$mgr   = new Batch_Mgr();
		$batch = $mgr->get_batch( $id );

		// Let third-party developers filter batch data.
		$batch->set_posts( apply_filters( 'sme_prepare_posts', $batch->get_posts() ) );
		$batch->set_attachments( apply_filters( 'sme_prepare_attachments', $batch->get_attachments() ) );
		$batch->set_users( apply_filters( 'sme_prepare_users', $batch->get_users() ) );

		/*
		 * Make sure to get rid of any old messages generated during pre-flight
		 * of this batch.
		 */
		$this->delete_messages( $batch->get_id() );

		/*
		 * Let third party developers perform actions before pre-flight. This is
		 * most often where third-party developers would add custom data.
		 */
		do_action( 'sme_prepare', $batch );

		return $batch;
	}

	/**
	 * Deploy a batch from content stage to production.
	 *
	 * @param Batch $batch
	 * @return array
	 */
	public function deploy( Batch $batch ) {

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
			'batch'  => $batch,
		);

		$this->client->request( 'smeContentStaging.import', $request );
		return $this->client->get_response_data();
	}

	/* **********************************************************************
	 * Status API
	 * **********************************************************************/

	/**
	 * Set status for a batch.
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

	/* **********************************************************************
	 * Message API
	 * **********************************************************************/

	/**
	 * Add a message.
	 *
	 * Messages will be displayed to the user through the UI.
	 *
	 * @param string $message
	 * @param string $level
	 * @param int $post_id
	 * @param string $type
	 */
	public function add_message( $message, $level, $post_id, $type = '' ) {

		$key = $this->get_message_key( $type );

		$value = array(
			'level'   => $level,
			'message' => $message,
		);

		add_post_meta( $post_id, $key, $value );
	}

	/**
	 * Get messages for a specific post.
	 *
	 * @param $post_id
	 * @param string $type
	 * @return array
	 */
	public function get_messages( $post_id, $type = '' ) {

		$messages = array();
		$key      = $this->get_message_key( $type );

		if ( $type ) {
			return get_post_meta( $post_id, $key );
		}

		$meta = get_post_meta( $post_id );

		foreach ( $meta as $group => $values ) {
			if ( strpos( $group, $key ) === 0 ) {
				foreach ( $values as $message ) {
					array_push( $messages, unserialize( $message ) );
				}
			}
		}

		return $messages;
	}

	/**
	 * Delete messages for a specific post.
	 *
	 * @param $post_id
	 * @param string $type
	 * @return bool
	 */
	public function delete_messages( $post_id, $type = '' ) {

		$key = $this->get_message_key( $type );

		if ( $type ) {
			delete_post_meta( $post_id, $key );
			return true;
		}

		$meta = get_post_meta( $post_id );

		foreach ( $meta as $group => $values ) {
			if ( strpos( $group, $key ) === 0 ) {
				delete_post_meta( $post_id, $group );
			}
		}

		return true;
	}

	/**
	 * Get meta_key to use when searching for records in wp_postmeta.
	 *
	 * @param string $type
	 * @return string
	 */
	private function get_message_key( $type = '' ) {

		// Default meta_key in wp_postmeta table.
		$key = '_sme_message';

		// Append _$type to $key if a $type has been set.
		if ( $type ) {
			$key .= '_' . $type;
		}

		return $key;
	}

}