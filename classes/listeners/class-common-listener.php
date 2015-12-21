<?php
namespace Me\Stenberg\Content\Staging\Listeners;

use Exception;
use Me\Stenberg\Content\Staging\Apis\Common_API;
use Me\Stenberg\Content\Staging\DB\Post_DAO;
use Me\Stenberg\Content\Staging\Factories\DAO_Factory;
use Me\Stenberg\Content\Staging\Helper_Factory;
use Me\Stenberg\Content\Staging\Models\Batch;
use Me\Stenberg\Content\Staging\Models\Post;

class Common_Listener {

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
	public function __construct( Common_API $api, DAO_Factory $dao_factory ) {

		$this->post_dao = $dao_factory->create( 'Post' );
		$this->api      = $api;

		// Register listeners.
		add_action( 'sme_prepare', array( $this, 'prepare_preflight' ) );
		add_action( 'sme_store', array( $this, 'prepare_preflight' ) );
		add_action( 'sme_verify_posts', array( $this, 'verify_post' ), 10, 2 );
		add_action( 'sme_deploy', array( $this, 'prepare_deploy' ), 9 );
		add_action( 'sme_import', array( $this, 'prepare_deploy' ), 9 );
	}

	/**
	 * Prepare for pre-flight. Cleanup old pre-flight messages, pre-flight
	 * status etc.
	 *
	 * @param Batch $batch
	 */
	public function prepare_preflight( Batch $batch ) {
		$this->api->delete_preflight_status( $batch->get_id() );
		$this->api->delete_preflight_messages( $batch->get_id() );
	}

	/**
	 * Prepare for deploy. Cleanup old deploy messages, deploy status etc.
	 *
	 * @param Batch $batch
	 */
	public function prepare_deploy( Batch $batch ) {
		$this->api->delete_deploy_status( $batch->get_id() );
		$this->api->delete_deploy_messages( $batch->get_id() );
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
			$revision = $this->post_dao->get_by_guid( $post->get_guid() );
		} catch ( Exception $e ) {
			$this->api->set_preflight_status( $batch->get_id(), 2 );
			$this->api->add_preflight_message( $batch->get_id(), $e->getMessage(), 'error' );
			return;
		}

		// Check if parent post exist on production or in batch.
		if ( ! $this->parent_post_exists( $post, $batch->get_posts() ) ) {

			// Fail pre-flight.
			$this->api->set_preflight_status( $batch->get_id(), 2 );

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
			return;
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