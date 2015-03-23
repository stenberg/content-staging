<?php
namespace Me\Stenberg\Content\Staging\Listeners;

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
	 * Constructor.
	 */
	public function __construct() {

		// Register listeners.
		if ( current_user_can( 'delete_posts' ) ) {
			add_action( 'untrashed_post', array( $this, 'untrash_post' ) );
			add_action( 'wp_trash_post', array( $this, 'trash_post' ) );
			add_action( 'delete_post', array( $this, 'delete_post' ) );
		}
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