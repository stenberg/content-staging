<?php
namespace Me\Stenberg\Content\Staging\Listeners;

use Me\Stenberg\Content\Staging\Apis\Common_API;
use Me\Stenberg\Content\Staging\DB\Custom_DAO;
use Me\Stenberg\Content\Staging\Helper_Factory;
use Me\Stenberg\Content\Staging\Models\Batch;
use Me\Stenberg\Content\Staging\Models\Message;
use Me\Stenberg\Content\Staging\View\Template;

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
	 * @var Template
	 */
	private $template;

	/**
	 * @var Common_API
	 */
	private $api;

	/**
	 * Constructor.
	 */
	public function __construct( Template $template, Common_API $api ) {

		// ID of this extension.
		$this->extension = 'sme_deleted_posts';

		// Template.
		$this->template = $template;

		// Common API.
		$this->api = $api;

		// Register listeners.
		if ( current_user_can( 'delete_posts' ) ) {
			add_action( 'delete_post', array( $this, 'add_to_delete_log' ) );
		}

		add_action( 'sme_view_edit_batch_pre_buttons', array( $this, 'render' ) );

		// Hook in to Content Staging preparation of batch.
		add_action( 'sme_save_batch', array( $this, 'prepare' ) );

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

		$title  = ( isset( $post->post_title ) ) ? $post->post_title : '(no title)';

		if ( isset( $post->post_type ) ) {
			$title .= ' (' . $post->post_type . ')';
		}

		$data = array(
			'id'    => $post->ID,
			'guid'  => $post->guid,
			'title' => $title,
		);

		add_option( $this->get_option_name( $post_id, 'delete' ), $data, '', 'no' );
	}

	/**
	 * Render table of deleted posts.
	 */
	public function render( Batch $batch ) {
		/**
		 * @var Custom_DAO $custom_dao
		 */
		$custom_dao = Helper_Factory::get_instance()->get_dao( 'Custom' );

		// Get deleted posts.
		$deleted_posts = $custom_dao->get_deleted_posts();

		// No deleted posts exists.
		if ( empty( $deleted_posts ) ) {
			return;
		}

		// Deleted posts previously scheduled to be synced to production.
		$selected_posts = $batch->get_custom_data( $this->extension );

		if ( ! $selected_posts ) {
			$selected_posts = array();
		}

		$selected_posts = array_map( function( $post ) {
			return $post['id'];
		}, $selected_posts );

		$deleted_posts = array_map( function( $post ) use ( $selected_posts ) {
			$post['checked'] = ( in_array( $post['id'], $selected_posts ) ) ? 'checked="checked"' : '';
			return $post;
		}, $deleted_posts );

		$data = array(
			'deleted_posts' => $deleted_posts,
		);

		$this->template->render( 'delete-post', $data );
	}

	/**
	 * Add GUIDs of deleted posts to batch.
	 *
	 * @param Batch $batch
	 */
	public function prepare( Batch $batch ) {

		if ( ! isset( $_POST['delete_posts'] ) || empty( $_POST['delete_posts'] ) ) {
			$batch->add_custom_data( $this->extension, array() );
			return;
		}

		// Posts selected for deletion.
		$selected = array_map( 'intval', $_POST['delete_posts'] );

		/**
		 * @var Custom_DAO $custom_dao
		 */
		$custom_dao = Helper_Factory::get_instance()->get_dao( 'Custom' );

		$deleted_posts = $custom_dao->get_deleted_posts();

		$deleted_posts = array_filter( $deleted_posts, function( $post ) use ( $selected ) {
			return ( isset( $post['id'] ) && in_array( $post['id'], $selected ) );
		} );

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

		foreach ( $posts as $post ) {

			if ( ! isset( $post['guid'] ) ) {
				continue;
			}

			$post_id = $this->api->get_post_id_by_guid( $post['guid'] );
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

		$this->api->add_deploy_message( $batch->get_id(), $message, 'info', 104 );
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

			$this->handle_deleted_posts( $message );
		}

		return $response;
	}

	/**
	 * Look for message indicating that a post has been deleted on production
	 * and remove these posts from log of deleted posts.
	 *
	 * @param Message $message
	 */
	private function handle_deleted_posts( Message $message ) {

		$regex = '#<span class="hidden" data-blog-id="(.*?)">(.*?)</span>#';
		preg_match( $regex, $message->get_message(), $groups );

		// Make sure groups has been set.
		if ( empty( $groups[1] ) || empty( $groups[2] ) ) {
			return;
		}

		$blog_id  = intval( $groups[1] );
		$post_ids = array_map( 'intval', explode( ',', $groups[2] ) );

		// Make sure blog ID and post IDs has been set.
		if ( ! $blog_id || empty( $post_ids ) ) {
			return;
		}

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