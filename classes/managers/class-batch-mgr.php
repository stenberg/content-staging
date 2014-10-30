<?php
namespace Me\Stenberg\Content\Staging\Managers;

use Me\Stenberg\Content\Staging\DB\Batch_DAO;
use Me\Stenberg\Content\Staging\DB\Post_DAO;
use Me\Stenberg\Content\Staging\DB\Post_Taxonomy_DAO;
use Me\Stenberg\Content\Staging\DB\Postmeta_DAO;
use Me\Stenberg\Content\Staging\DB\Term_DAO;
use Me\Stenberg\Content\Staging\DB\User_DAO;
use Me\Stenberg\Content\Staging\Helper_Factory;
use Me\Stenberg\Content\Staging\Models\Batch;
use Me\Stenberg\Content\Staging\Models\Post;

/**
 * Class Batch_Mgr
 *
 * This is where all logic for building the batch we want to send from
 * content stage to production goes.
 *
 * @package Me\Stenberg\Content\Staging\Managers
 */
class Batch_Mgr {

	/**
	 * Batch object managed by this class.
	 *
	 * @var Batch
	 */
	private $batch;

	private $batch_dao;
	private $post_dao;
	private $post_taxonomy_dao;
	private $postmeta_dao;
	private $user_dao;

	public function __construct() {
		$this->batch_dao         = Helper_Factory::get_instance()->get_dao( 'Batch' );
		$this->post_dao          = Helper_Factory::get_instance()->get_dao( 'Post' );
		$this->post_taxonomy_dao = Helper_Factory::get_instance()->get_dao( 'Post_Taxonomy' );
		$this->postmeta_dao      = Helper_Factory::get_instance()->get_dao( 'Postmeta' );
		$this->user_dao          = Helper_Factory::get_instance()->get_dao( 'User' );
	}

	/**
	 * Get batch from database and populates it with all its data, e.g. posts
	 * users, metadata etc. included in the batch.
	 *
	 * If $id is not provided an empty Batch object will be returned.
	 *
	 * If $lazy is set to 'true' only default batch data such as batch ID,
	 * title, modification date, etc. will be fetched.
	 *
	 * When $lazy is set to 'false' the batch will be populated with all data
	 * in this batch, e.g. posts, terms, users, etc.
	 *
	 * @param int $id
	 * @param bool $lazy
	 * @return Batch
	 */
	public function get_batch( $id = null, $lazy = false ) {

		// This is a new batch, no need to populate the batch with any content.
		if ( $id === null ) {
			$batch = new Batch();
			$batch->set_status( 'publish' );
			return $batch;
		}

		$this->batch = $this->batch_dao->find( $id );
		$this->batch->set_post_rel_keys( apply_filters( 'sme_post_relationship_keys', array() ) );

		// Populate batch with data (posts, terms, etc.).
		if ( ! $lazy ) {

			// Get IDs of posts user has selected to include in this batch.
			$post_ids = $this->batch_dao->get_post_meta( $this->batch->get_id(), 'sme_selected_post_ids', true );

			$this->add_posts( $post_ids );
			$this->add_users();

		}

		return $this->batch;
	}

	/**
	 * Provide IDs of posts you want to add to the current batch.
	 *
	 * @param array $post_ids
	 */
	private function add_posts( $post_ids ) {
		$post_ids = apply_filters( 'sme_prepare_post_ids', $post_ids );
		$posts    = $this->post_dao->find_by_ids( $post_ids );

		foreach( $posts as $post ) {
			$this->add_post( $post );
		}
	}

	/**
	 * Provide a Post object you want to add to the current batch.
	 *
	 * @param Post $post
	 */
	private function add_post( Post $post ) {

		if ( $post->get_type() === 'attachment' ) {
			$this->add_attachment( $post->get_id() );
		}

		$this->post_taxonomy_dao->get_post_taxonomy_relationships( $post );
		$post->set_meta( $this->postmeta_dao->get_postmetas_by_post_id( $post->get_id() ) );

		/*
		 * Make it possible for third-party developers to modify post before it
		 * is added to batch.
		 */
		do_action( 'sme_prepare_post', $post, $this->batch );
		$this->batch->add_post( $post );

		foreach ( $post->get_meta() as $item ) {
			$this->add_related_posts( $item );
		}
	}

	private function add_users() {
		$user_ids = array();

		foreach ( $this->batch->get_posts() as $post ) {
			$user_ids[] = $post->get_author();
		}

		$this->batch->set_users( $this->user_dao->find_by_ids( $user_ids ) );
	}

	/**
	 * Add an attachment to batch.
	 *
	 * @param int $attachment_id
	 */
	private function add_attachment( $attachment_id ) {
		$attachment            = array();
		$upload_dir            = wp_upload_dir();
		$attachment_meta       = wp_get_attachment_metadata( $attachment_id );
		$attachment_info       = pathinfo( $attachment_meta['file'] );
		$attachment['subdir']  = '/' .$attachment_info['dirname'];
		$attachment['basedir'] = $upload_dir['basedir'];
		$attachment['baseurl'] = $upload_dir['baseurl'];

		/*
		 * Replace subdir of today (e.g. /2014/09) with subdir of this
		 * attachment (e.g. /2013/07).
		 */
		$attachment['path']    = str_replace( $upload_dir['subdir'], $attachment['subdir'], $upload_dir['path'] );
		$attachment['url']     = str_replace( $upload_dir['subdir'], $attachment['subdir'], $upload_dir['url'] );
		$attachment['items'][] = $attachment_info['basename'];

		foreach ( $attachment_meta['sizes'] as $item ) {
			$attachment['items'][] = $item['file'];
		}

		$this->batch->add_attachment( $attachment );
	}

	private function add_related_posts( $postmeta ) {
		foreach ( $this->batch->get_post_rel_keys() as $key ) {
			if ( $postmeta['meta_key'] === $key ) {
				// Get post thumbnail.
				$post = $this->post_dao->find( $postmeta['meta_value'] );

				if ( isset( $post ) && $post->get_id() !== null ) {
					$this->add_post( $post );
				}
			}
		}
	}

}