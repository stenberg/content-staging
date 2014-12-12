<?php
namespace Me\Stenberg\Content\Staging;

use Me\Stenberg\Content\Staging\DB\Post_DAO;
use Me\Stenberg\Content\Staging\DB\Postmeta_DAO;
use Me\Stenberg\Content\Staging\Models\Batch;
use Me\Stenberg\Content\Staging\XMLRPC\Client;

class API {

	private $client;
	private $post_dao;
	private $postmeta_dao;

	public function __construct() {
		$this->client       = Helper_Factory::get_instance()->get_client();
		$this->post_dao     = Helper_Factory::get_instance()->get_dao( 'Post' );
		$this->postmeta_dao = Helper_Factory::get_instance()->get_dao( 'Postmeta' );
	}

	public function get_post_by_guid( $guid ) {
		return $this->post_dao->get_by_guid( $guid );
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
}