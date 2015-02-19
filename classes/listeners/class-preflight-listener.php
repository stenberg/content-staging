<?php
namespace Me\Stenberg\Content\Staging\Listeners;

use Exception;
use Me\Stenberg\Content\Staging\Apis\Common_API;
use Me\Stenberg\Content\Staging\DB\Post_DAO;
use Me\Stenberg\Content\Staging\Helper_Factory;
use Me\Stenberg\Content\Staging\Models\Batch;
use Me\Stenberg\Content\Staging\Models\Post;

class Preflight_Listener {

	/**
	 * @var Post_DAO
	 */
	private $post_dao;

	/**
	 * @var Common_API
	 */
	private $api;

	/**
	 * Constructor.
	 */
	public function __construct() {

		$this->post_dao = Helper_Factory::get_instance()->get_dao( 'Post' );
		$this->api      = Helper_Factory::get_instance()->get_api( 'Common' );

		// Register listeners.
		add_action( 'sme_verify_posts', array( $this, 'verify_post' ), 10, 2 );
	}

	/**
	 * Verify that a post is ready for deploy.
	 *
	 * @param Post  $post
	 * @param Batch $batch
	 */
	public function verify_post( Post $post, Batch $batch ) {
		/*
		 * If more then one post is found when searching posts with a specific
		 * GUID, then add an error message. Two or more posts should never share
		 * the same GUID.
		 */
		try {

			/*
			 * Check if a revision of this post already exist on production or if it
			 * is a brand new post.
			 */
			$production_revision = $this->post_dao->get_by_guid( $post->get_guid() );

			// Check if parent post exist on production or in batch.
			if ( ! $this->parent_post_exists( $post, $batch->get_posts() ) ) {

				// Admin URL of content stage.
				$admin_url = $batch->get_custom_data( 'sme_content_stage_admin_url' );

				$message = sprintf(
					'Post <a href="%s" target="_blank">%s</a> has a parent post that does not exist on production and is not part of this batch. Include post <a href="%s" target="_blank">%s</a> in this batch to resolve this issue.',
					$admin_url . 'post.php?post=' . $post->get_id() . '&action=edit',
					$post->get_title(),
					$admin_url . 'post.php?post=' . $post->get_parent()->get_id() . '&action=edit',
					$post->get_parent()->get_title()
				);

				$this->api->add_preflight_message( $batch->get_id(), $message, 'error' );
			}
		} catch ( Exception $e ) {
			$this->api->add_preflight_message( $batch->get_id(), $e->getMessage(), 'error' );
		}
	}

	/**
	 * Make sure parent post exist (if post has any) either in production
	 * database or in batch.
	 *
	 * @param Post  $post
	 * @param array $posts
	 * @return bool True if parent post exist (or post does not have a parent), false
	 *              otherwise.
	 */
	private function parent_post_exists( Post $post, $posts ) {

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

}