<?php
namespace Me\Stenberg\Content\Staging\Listeners;

use Me\Stenberg\Content\Staging\Apis\Common_API;
use Me\Stenberg\Content\Staging\DB\Custom_DAO;
use Me\Stenberg\Content\Staging\Helper_Factory;
use Me\Stenberg\Content\Staging\Models\Batch;

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
			add_action( 'delete_post', array( $this, 'delete_post' ) );
		}

		// Hook in to Content Staging preparation of batch.
		add_action( 'sme_prepared', array( $this, 'prepare' ) );

		// Hook in to Content Staging import of extension data.
		add_action( 'sme_import_' . $this->extension, array( $this, 'import' ) );
	}

	/**
	 * Remove post from list of trashed posts.
	 *
	 * @param int $post_id
	 */
	public function untrash_post( $post_id ) {
		delete_option( $this->get_option_name( $post_id, 'trash' ) );
	}

	/**
	 * Add post to list of trashed posts.
	 *
	 * @param int $post_id
	 */
	public function trash_post( $post_id ) {
		$this->delete( $post_id, 'trash' );
	}

	/**
	 * Add post to list of deleted posts.
	 *
	 * @param int $post_id
	 */
	public function delete_post( $post_id ) {
		$this->untrash_post( $post_id );
		$this->delete( $post_id, 'delete' );
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
	 * @param array $guids
	 */
	public function import( $guids ) {

		/**
		 * @var Common_API $common_api
		 */
		$common_api = Helper_Factory::get_instance()->get_api( 'Common' );

		foreach ( $guids as $guid ) {
			$post_id = $common_api->get_post_id_by_guid( $guid );
			wp_delete_post( $post_id, true );
		}
	}

	/**
	 * Add post to list of deleted / trashed posts.
	 *
	 * @param int $post_id
	 * @param string $key
	 */
	private function delete( $post_id, $key ) {
		$post = get_post( $post_id );

		if ( ! isset( $post->guid ) || ! $post->guid ) {
			return;
		}

		add_option( $this->get_option_name( $post_id, $key ), $post->guid, '', 'no' );
	}

	/**
	 * Option name holding deleted (or trashed) post.
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