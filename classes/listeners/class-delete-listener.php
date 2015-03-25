<?php
namespace Me\Stenberg\Content\Staging\Listeners;

use DOMDocument;
use DOMElement;
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

		// Current blog ID.
		$blog_id = get_current_blog_id();

		$message = sprintf(
			'Posts deleted on Content Stage has been removed from Production. <span class="hidden" data-blog-id="%d">%s</span>',
			$blog_id, $str
		);

		$common_api->add_deploy_message( $batch->get_id(), $message, 'info', 104 );
	}

	public function deploy_status( $response ) {

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

			$blog_id  = null;
			$post_ids = array();

			$dom = new DOMDocument;
			$dom->loadHTML( $message->get_message() );
			$elements = $dom->getElementsByTagName( 'span' );

			/**
			 * @var DOMElement $element
			 */
			foreach ( $elements as $element ) {

				if ( ! ( $attr = $element->getAttribute( 'data-blog-id' ) ) ) {
					continue;
				}

				$blog_id  = intval( $attr );
				$post_ids = array_map( 'intval', explode( ',', $element->nodeValue ) );
			}

			if ( $blog_id && ! empty( $post_ids ) ) {

				/**
				 * @var Custom_DAO $custom_dao
				 */
				$custom_dao = Helper_Factory::get_instance()->get_dao( 'Custom' );

				$current_blog_id = get_current_blog_id();

				// Switch blog if we are not on the correct one.
				if ( $current_blog_id !== $blog_id ) {
					switch_to_blog( $blog_id );
				}

				// Cleanup WP options.
				$custom_dao->remove_from_deleted_posts_log( $post_ids );

				// Restore blog if we switched before.
				if ( $current_blog_id !== $blog_id ) {
					restore_current_blog();
				}
			}
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

}