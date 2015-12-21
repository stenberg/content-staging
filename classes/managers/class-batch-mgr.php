<?php
namespace Me\Stenberg\Content\Staging\Managers;

use Exception;
use Me\Stenberg\Content\Staging\Apis\Common_API;
use Me\Stenberg\Content\Staging\DB\Batch_DAO;
use Me\Stenberg\Content\Staging\DB\Custom_DAO;
use Me\Stenberg\Content\Staging\DB\Post_DAO;
use Me\Stenberg\Content\Staging\DB\Post_Taxonomy_DAO;
use Me\Stenberg\Content\Staging\DB\Postmeta_DAO;
use Me\Stenberg\Content\Staging\DB\User_DAO;
use Me\Stenberg\Content\Staging\Factories\DAO_Factory;
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
	 * @var Batch_DAO
	 */
	private $batch_dao;

	/**
	 * @var Custom_DAO
	 */
	private $custom_dao;

	/**
	 * @var Post_DAO
	 */
	private $post_dao;

	/**
	 * @var Post_Taxonomy_DAO
	 */
	private $post_taxonomy_dao;

	/**
	 * @var Postmeta_DAO
	 */
	private $postmeta_dao;

	/**
	 * @var User_DAO
	 */
	private $user_dao;

	/**
	 * @var Common_API
	 */
	private $api;

	/**
	 * Constructor.
	 *
	 * @param Common_API  $api
	 * @param DAO_Factory $dao_factory
	 */
	public function __construct( Common_API $api, DAO_Factory $dao_factory ) {
		$this->api               = $api;
		$this->batch_dao         = $dao_factory->create( 'Batch' );
		$this->custom_dao        = $dao_factory->create( 'Custom' );
		$this->post_dao          = $dao_factory->create( 'Post' );
		$this->post_taxonomy_dao = $dao_factory->create( 'Post_Taxonomy' );
		$this->postmeta_dao      = $dao_factory->create( 'Postmeta' );
		$this->user_dao          = $dao_factory->create( 'User' );
	}

	/**
	 * Get batch from database and populates it with all its data, e.g. posts
	 * users, metadata etc. included in the batch.
	 *
	 * If $id is not provided an empty Batch object will be returned.
	 *
	 * @param int $id
	 *
	 * @return Batch
	 */
	public function get( $id = null ) {

		// This is a new batch, no need to populate the batch with any content.
		if ( $id === null ) {
			$batch = new Batch();
			$batch->set_status( 'publish' );
			return $batch;
		}

		return $this->batch_dao->find( $id );
	}

	/**
	 * Prepare batch with relevant content.
	 *
	 * @param Batch $batch
	 */
	public function prepare( Batch $batch ) {

		// Batch not yet created, nothing to prepare.
		if ( ! $batch->get_id() ) {
			return;
		}

		$post_ids = array();
		$batch->set_post_rel_keys( apply_filters( 'sme_post_relationship_keys', array() ) );

		// Clean batch from any old content.
		$batch->set_attachments( array() );
		$batch->set_users( array() );
		$batch->set_posts( array() );

		// Get IDs of posts user has selected to include in this batch.
		$meta = $this->batch_dao->get_post_meta( $batch->get_id(), 'sme_selected_post' );

		// Ensure that we got an array back when looking for posts IDs in DB.
		if ( is_array( $meta ) ) {
			$post_ids = $meta;
		}

		$this->add_table_prefix( $batch );
		$this->add_posts( $batch, $post_ids );
		$this->add_users( $batch );

		// Add the admin URL of content stage to the batch.
		$batch->add_custom_data( 'sme_content_stage_admin_url', admin_url() );
	}

	/**
	 * Provide IDs of posts you want to add to the current batch.
	 *
	 * @param Batch $batch
	 * @param array $post_ids
	 */
	private function add_posts( Batch $batch, $post_ids ) {

		$post_ids = apply_filters( 'sme_prepare_post_ids', $post_ids );
		$post_ids = array_unique( $post_ids );
		$posts    = $this->post_dao->find_by_ids( $post_ids );

		foreach ( $posts as $post ) {
			$this->add_post( $batch, $post );
		}
	}

	/**
	 * Provide a Post object you want to add to the current batch.
	 *
	 * @param Batch $batch
	 * @param Post  $post
	 */
	private function add_post( Batch $batch, Post $post ) {

		// Make sure the post is not already in the batch.
		foreach ( $batch->get_posts() as $post_in_batch ) {
			if ( $post->get_id() === $post_in_batch->get_id() ) {
				return;
			}
		}

		if ( $post->get_type() === 'attachment' ) {
			$this->add_attachment( $batch, $post->get_id() );
		}

		// Catch issue with term ID not being set properly.
		try {
			$this->post_taxonomy_dao->get_post_taxonomy_relationships( $post );
		} catch( Exception $e ) {
			$this->api->add_preflight_message( $batch->get_id(), $e->getMessage(), 'warning' );
		}

		$post->set_meta( $this->postmeta_dao->get_postmetas_by_post_id( $post->get_id() ) );

		/*
		 * Make it possible for third-party developers to modify post before it
		 * is added to batch.
		 */
		do_action( 'sme_prepare_post', $post, $batch );
		$batch->add_post( $post );

		$post_meta    = $post->get_meta();
		$record_count = count( $post_meta );

		for ( $i = 0; $i < $record_count; $i++ ) {
			$post_meta[$i] = $this->add_related_posts( $batch, $post_meta[$i] );
		}

		$post->set_meta( $post_meta );
	}

	/**
	 * @param Batch $batch
	 */
	private function add_users( Batch $batch ) {
		$user_ids = array();

		foreach ( $batch->get_posts() as $post ) {
			$user_ids[] = $post->get_author();
		}

		$batch->set_users( $this->user_dao->find_by_ids( $user_ids ) );
	}

	/**
	 * Add an attachment to batch.
	 *
	 * @param Batch $batch
	 * @param int   $attachment_id
	 */
	private function add_attachment( Batch $batch, $attachment_id ) {
		$attachment      = array();
		$upload_dir      = wp_upload_dir();
		$attachment_meta = wp_get_attachment_metadata( $attachment_id );

		if ( ! isset( $attachment_meta['file'] ) ) {
			return;
		}

		$attachment_info = pathinfo( $attachment_meta['file'] );

		if ( ! isset( $attachment_info['dirname'] ) ) {
			return;
		}

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

		if ( isset( $attachment_meta['sizes'] ) ) {
			foreach ( $attachment_meta['sizes'] as $item ) {
				$attachment['items'][] = $item['file'];
			}
		}

		$batch->add_attachment( $attachment );
	}

	/**
	 * Add post to the batch that is referenced through the post meta of
	 * another post.
	 *
	 * Scope of this method has been extended to also include changing the ID
	 * of the referenced post into using the GUID instead.
	 *
	 * @param Batch $batch
	 * @param array $postmeta
	 *
	 * @return array
	 */
	private function add_related_posts( Batch $batch, $postmeta ) {

		// Check if this post meta key is in the array of post relationship keys.
		if ( ! in_array( $postmeta['meta_key'], $batch->get_post_rel_keys() ) ) {
			return $postmeta;
		}

		// Find post the current post holds a reference to.
		$post = $this->post_dao->find( $postmeta['meta_value'] );

		if ( isset( $post ) && $post->get_id() !== null ) {
			$this->add_post( $batch, $post );

			/*
			 * Change meta value to post GUID instead of post ID so we can later find
			 * the reference on production.
			 */
			$postmeta['meta_value'] = $post->get_guid();
		}

		return $postmeta;
	}

	/**
	 * Add database table base prefix to batch.
	 *
	 * @param Batch $batch
	 */
	private function add_table_prefix( Batch $batch ) {
		$batch->add_custom_data( 'sme_table_base_prefix', $this->custom_dao->get_table_base_prefix() );
	}

}