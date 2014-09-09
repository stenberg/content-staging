<?php
namespace Me\Stenberg\Content\Staging\Managers;

use Me\Stenberg\Content\Staging\DB\Batch_DAO;
use Me\Stenberg\Content\Staging\DB\Post_DAO;
use Me\Stenberg\Content\Staging\DB\Postmeta_DAO;
use Me\Stenberg\Content\Staging\DB\Term_DAO;
use Me\Stenberg\Content\Staging\DB\User_DAO;
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
	private $postmeta_dao;
	private $term_dao;
	private $user_dao;

	public function __construct( Batch_DAO $batch_dao, Post_DAO $post_dao, Postmeta_DAO $postmeta_dao,
								 Term_DAO $term_dao, User_DAO $user_dao ) {
		$this->batch_dao    = $batch_dao;
		$this->post_dao     = $post_dao;
		$this->postmeta_dao = $postmeta_dao;
		$this->term_dao     = $term_dao;
		$this->user_dao     = $user_dao;
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
			return new Batch();
		}

		// Get default batch data from database (ID, modification date, etc.).
		$this->batch = $this->batch_dao->get_batch_by_id( $id );

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
		$posts = $this->post_dao->get_posts_by_ids( $post_ids );

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

		$postmeta = $this->postmeta_dao->get_postmetas_by_post_id( $post->get_id() );

		if ( $post->get_post_type() === 'attachment' ) {
			$this->add_attachment( $post->get_id() );
		}

		/*
		 * To be able to find the parent post on the production server we must
		 * include GUID of parent post in the batch.
		 */
		$post->set_post_parent_guid( $this->post_dao->get_guid_by_id( $post->get_post_parent() ) );

		// Populate Post object with Post_Taxonomy relationship objects.
		$this->term_dao->get_post_taxonomy_relationships( $post );

		// Add post to batch.
		$this->batch->add_post( $post );

		foreach ( $postmeta as $item ) {
			$post->add_meta( $item );
			$this->add_related_posts( $item );
		}
	}

	private function add_users() {
		$user_ids = array();

		foreach ( $this->batch->get_posts() as $post ) {
			$user_ids[] = $post->get_post_author();
		}

		$this->batch->set_users( $this->user_dao->get_users_by_ids( $user_ids, false ) );
	}

	/**
	 * Add an attachment to batch.
	 *
	 * @param int $attachment_id
	 */
	private function add_attachment( $attachment_id ) {

		$attachment = array();

		$meta = wp_get_attachment_metadata( $attachment_id );
		$attachment['path']    = pathinfo( $meta['file'], PATHINFO_DIRNAME );
		$attachment['sizes'][] = wp_get_attachment_url( $attachment_id );

		foreach ( $meta['sizes'] as $size => $meta ) {
			$info = wp_get_attachment_image_src( $attachment_id, $size );
			$attachment['sizes'][] = $info[0];
		}

		$this->batch->add_attachment( $attachment );
	}

	private function add_related_posts( $postmeta ) {

		/*
		 * Get postmeta keys who's records contains relations between posts.
		 * @todo apply_filters will be called every time a post is being added to
		 * the batch to maximize extensibility of this plugin. For increased
		 * performance apply_filters could be called e.g. when post IDs are
		 * registered with the batch. That way we would only use apply_filters
		 * once.
		 */
		$meta_keys = apply_filters( 'sme_post_relationship_keys', array() );

		foreach ( $meta_keys as $key ) {
			if ( $postmeta['meta_key'] === $key ) {

				// Get post thumbnail.
				$post = $this->post_dao->get_post_by_id( $postmeta['meta_value'] );

				if ( isset( $post ) && $post->get_id() !== null ) {
					$this->add_post( $post );
				}
			}
		}
	}

}