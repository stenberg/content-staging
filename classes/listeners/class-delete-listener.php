<?php
namespace Me\Stenberg\Content\Staging\Listeners;

use Me\Stenberg\Content\Staging\Apis\Common_API;
use Me\Stenberg\Content\Staging\DB\Custom_DAO;
use Me\Stenberg\Content\Staging\Helper_Factory;
use Me\Stenberg\Content\Staging\Models\Batch;
use Me\Stenberg\Content\Staging\Models\Message;

/**
 * Class Delete_Listener
 *
 * Log trashed and deleted posts so these changes can be synced to
 * production.
 *
 * @package Me\Stenberg\Content\Staging\Listeners
 */
class Delete_Listener {

	/**
	 * ID of extension.
	 *
	 * @var string
	 */
	private $extension;

	/**
	 * Constructor.
	 */
	public function __construct() {

		// ID of this extension.
		$this->extension = 'sme_deleted_posts';

		// Register listeners.
		if ( current_user_can( 'delete_posts' ) ) {
			add_action( 'delete_post', array( $this, 'add_to_delete_log' ) );
		}

		// Hook in to Content Staging preparation of batch.
		add_action( 'sme_prepared', array( $this, 'prepare' ) );

		// Hook in to Content Staging import of extension data.
		add_action( 'sme_import_' . $this->extension, array( $this, 'import' ), 10, 2 );

		// Hook into Content Staging receive deploy message from production.
		add_action( 'sme_deploy_status', array( $this, 'deploy_status' ) );
	}

	/**
	 * Add post to list of deleted posts.
	 *
	 * @param int $post_id
	 */
	public function add_to_delete_log( $post_id ) {

		$post = get_post( $post_id );

		if ( ! isset( $post->ID ) || ! $post->ID ) {
			return;
		}

		if ( ! isset( $post->guid ) || ! $post->guid ) {
			return;
		}

		$data = array(
			'id'   => $post->ID,
			'guid' => $post->guid,
		);

		add_option( $this->get_option_name( $post_id, 'delete' ), $data, '', 'no' );
	}

	/**
	 * Add GUIDs of deleted posts to batch.
	 *
	 * @param Batch $batch
	 */
	public function prepare( Batch $batch ) {

		/**
		 * @var Custom_DAO $custom_dao
		 */
		$custom_dao = Helper_Factory::get_instance()->get_dao( 'Custom' );

		$deleted_posts = $custom_dao->get_deleted_posts();
		$batch->add_custom_data( $this->extension, $deleted_posts );
	}

	/**
	 * Remove posts that has been deleted on content stage.
	 *
	 * Runs on production server after batch has been sent from content stage
	 * and received by the production server.
	 *
	 * @param array $posts
	 * @param Batch $batch
	 */
	public function import( $posts, Batch $batch ) {

		// No posts provided.
		if ( empty( $posts ) ) {
			return;
		}

		// String of deleted post IDs.
		$stage_post_ids = array();

		/**
		 * @var Common_API $common_api
		 */
		$common_api = Helper_Factory::get_instance()->get_api( 'Common' );

		foreach ( $posts as $post ) {

			if ( ! isset( $post['guid'] ) ) {
				continue;
			}

			$post_id = $common_api->get_post_id_by_guid( $post['guid'] );
			wp_delete_post( $post_id, true );

			if ( isset( $post['id'] ) ) {
				array_push( $stage_post_ids, $post['id'] );
			}
		}

		// String of deleted content staging IDs.
		$str = implode( ',', $stage_post_ids );

		$common_api->add_deploy_message(
			$batch->get_id(),
			'Posts deleted on Content Stage has been removed from Production. <span class="hidden">' . $str . '</span>',
			'info',
			104
		);
	}

	public function deploy_status( $response ) {

		// Post IDs to remove from log of deleted posts.
		$post_ids = array();

		if ( ! isset( $response['messages'] ) ) {
			return $response;
		}

		foreach ( $response['messages'] as $message ) {

			if ( ! $message instanceof Message ) {
				continue;
			}

			if ( $message->get_code() !== 104 ) {
				continue;
			}

			$post_ids_str = $this->extract_text_between_tags( $message->get_message(), '<span class="hidden">', '</span>' );
			$new_post_ids = explode( ',', $post_ids_str );
			$new_post_ids = array_map( 'intval', $new_post_ids );

			if ( ! empty( $new_post_ids ) ) {
				$post_ids = array_merge( $post_ids, $new_post_ids );
			}
		}

		if ( ! empty( $post_ids ) ) {

			/**
			 * @var Custom_DAO $custom_dao
			 */
			$custom_dao = Helper_Factory::get_instance()->get_dao( 'Custom' );

			// Cleanup WP options.
			$custom_dao->remove_from_deleted_posts_log( $post_ids );
		}

		return $response;
	}

	/**
	 * Option name holding deleted post.
	 *
	 * @param int $post_id
	 * @param string $key
	 *
	 * @return string
	 */
	private function get_option_name( $post_id, $key ) {
		return sprintf( '_sme_%s_post_%d', $key, $post_id );
	}

	/**
	 * Extract text between two tags from a string.
	 */
	private function extract_text_between_tags( $str, $start_tag, $end_tag ) {

		$substr    = '';
		$start_pos = strpos( $str, $start_tag ) + strlen( $start_tag );

		if ( false !== $start_pos ) {
			$end_pos = strpos( $str, $end_tag, $start_pos );
			if ( false !== $end_pos ) {
				$substr = substr( $str, $start_pos, $end_pos - $start_pos );
			}
		}

		return $substr;
	}

}