<?php
namespace Me\Stenberg\Content\Staging\Importers;

use Exception;
use Me\Stenberg\Content\Staging\Apis\Common_API;
use Me\Stenberg\Content\Staging\DB\Batch_DAO;
use Me\Stenberg\Content\Staging\DB\Custom_DAO;
use Me\Stenberg\Content\Staging\DB\Post_DAO;
use Me\Stenberg\Content\Staging\DB\Post_Taxonomy_DAO;
use Me\Stenberg\Content\Staging\DB\Postmeta_DAO;
use Me\Stenberg\Content\Staging\DB\Taxonomy_DAO;
use Me\Stenberg\Content\Staging\DB\Term_DAO;
use Me\Stenberg\Content\Staging\DB\User_DAO;
use Me\Stenberg\Content\Staging\Helper_Factory;
use Me\Stenberg\Content\Staging\Models\Batch;
use Me\Stenberg\Content\Staging\Models\Post;
use Me\Stenberg\Content\Staging\Models\Post_Env_Diff;
use Me\Stenberg\Content\Staging\Models\Relationships\Post_Taxonomy;
use Me\Stenberg\Content\Staging\Models\Taxonomy;
use Me\Stenberg\Content\Staging\Models\Term;
use Me\Stenberg\Content\Staging\Models\User;

abstract class Batch_Importer {

	/**
	 * Array storing the relationship between a post from the staging
	 * environment and the same post on the production environment.
	 *
	 * @var array
	 */
	public $post_diffs;

	/**
	 * @var Batch
	 */
	protected $batch;

	/**
	 * @var Common_API
	 */
	protected $api;

	/**
	 * @var Batch_DAO
	 */
	protected $batch_dao;

	/**
	 * @var Custom_DAO
	 */
	protected $custom_dao;

	/**
	 * @var Post_DAO
	 */
	protected $post_dao;

	/**
	 * @var Post_Taxonomy_DAO
	 */
	protected $post_taxonomy_dao;

	/**
	 * @var Postmeta_DAO
	 */
	protected $postmeta_dao;

	/**
	 * @var Taxonomy_DAO
	 */
	protected $taxonomy_dao;

	/**
	 * @var Term_DAO
	 */
	protected $term_dao;

	/**
	 * @var User_DAO
	 */
	protected $user_dao;

	/**
	 * Constructor.
	 *
	 * @param Batch $batch
	 */
	protected function __construct( Batch $batch ) {

		/**
		 * @var Common_API $sme_content_staging_api
		 */
		global $sme_content_staging_api;

		$this->batch             = $batch;
		$this->api               = $sme_content_staging_api;
		$this->batch_dao         = Helper_Factory::get_instance()->get_dao( 'Batch' );
		$this->custom_dao        = Helper_Factory::get_instance()->get_dao( 'Custom' );
		$this->post_dao          = Helper_Factory::get_instance()->get_dao( 'Post' );
		$this->post_taxonomy_dao = Helper_Factory::get_instance()->get_dao( 'Post_Taxonomy' );
		$this->postmeta_dao      = Helper_Factory::get_instance()->get_dao( 'Postmeta' );
		$this->taxonomy_dao      = Helper_Factory::get_instance()->get_dao( 'Taxonomy' );
		$this->term_dao          = Helper_Factory::get_instance()->get_dao( 'Term' );
		$this->user_dao          = Helper_Factory::get_instance()->get_dao( 'User' );

		// Get diffs from database.
		$this->post_diffs = $this->post_dao->get_post_diffs( $batch );
	}

	/**
	 * Start importer.
	 */
	abstract function run();

	/**
	 * Get import status.
	 */
	abstract function status();

	/**
	 * Import users.
	 *
	 * @param array $users
	 */
	public function import_users( $users ) {
		foreach ( $users as $user ) {
			$this->import_user( $user );
		}
	}

	/**
	 * Import user.
	 *
	 * @param User $user
	 *
	 * @todo Here we are assuming that there cannot be two users with the
	 * same user_login. This might be wrong. Investigate!
	 * Consider using WP function get_user_by.
	 *
	 * @see http://codex.wordpress.org/Function_Reference/get_user_by
	 */
	public function import_user( User $user ) {

		// Database table base prefix for production and content stage.
		$prod_prefix  = $this->custom_dao->get_table_base_prefix();
		$stage_prefix = $this->batch->get_custom_data( 'sme_table_base_prefix' );

		// Change database table base prefix from content staging prefix to
		// production prefix.
		$meta = array_map(
			function( $record ) use ( $stage_prefix, $prod_prefix ) {
				if ( isset( $record['meta_key'] ) && strpos( $record['meta_key'], $stage_prefix) === 0 ) {
					$record['meta_key'] = substr_replace( $record['meta_key'], $prod_prefix, 0, strlen( $stage_prefix ) );
				}
				return $record;
			},
			$user->get_meta()
		);

		// Update user with new meta.
		$user->set_meta( $meta );

		// See if user exists in database.
		$existing = $this->user_dao->get_user_by_user_login( $user->get_login() );

		// Create if user does not exist, update otherwise.
		if ( empty( $existing ) ) {
			$this->user_dao->insert( $user );
		} else {
			$user->set_id( $existing->get_id() );
			$this->user_dao->update_user( $user );
		}
	}

	/**
	 * Import posts.
	 *
	 * @param array $posts
	 */
	public function import_posts( array $posts ) {
		foreach ( $posts as $post ) {
			$this->import_post( $post );
		}
	}

	/**
	 * Import post.
	 *
	 * @param Post $post
	 */
	public function import_post( Post $post ) {

		// Notify listeners that post is about to be imported.
		do_action( 'sme_post_import', $post, $this->batch );

		$publish_status_changed = false;

		/*
		 * Create object that can keep track of differences between stage and
		 * production post.
		 */
		$post_diff = new Post_Env_Diff( $post->get_id() );
		$post_diff->set_stage_status( $post->get_post_status() );

		// Check if post has any parent.
		if ( $post->get_parent() !== null ) {
			$post_diff->set_parent_guid( $post->get_parent()->get_guid() );
		}

		/*
		 * Check if post already exist on production, if it does then update the
		 * old version of the post rather then creating a new post.
		 */
		if ( ( $prod_revision = $this->post_dao->get_by_guid( $post->get_guid() ) ) !== null ) {

			/*
			 * This is an existing post.
			 */

			$post_diff->set_prod_id( $prod_revision->get_id() );
			$post->set_id( $prod_revision->get_id() );
			$this->post_dao->update_post( $post );
		} else {

			/*
			 * This is a new post.
			 */

			// Turn published posts into drafts for now.
			if ( $post->get_post_status() == 'publish' ) {
				$post->set_post_status( 'draft' );
				$publish_status_changed = true;
			}

			// Insert post.
			$this->post_dao->insert( $post );

			// Reset publish status.
			if ( $publish_status_changed ) {
				$post->set_post_status( 'publish' );
			}

			// Store new production ID in diff.
			$post_diff->set_prod_id( $post->get_id() );
		}

		// Add post diff to array of post diffs.
		$this->add_post_diff( $post_diff );

		// Clear old post/taxonomy relationships.
		foreach ( $post->get_post_taxonomy_relationships() as $post_taxonomy ) {
			$this->post_taxonomy_dao->clear_post_taxonomy_relationships( $post_taxonomy );
		}

		// Import post/taxonomy relationships.
		foreach ( $post->get_post_taxonomy_relationships() as $post_taxonomy ) {
			// Import taxonomy.
			$this->import_taxonomy( $post_taxonomy->get_taxonomy() );
			$this->post_taxonomy_dao->insert( $post_taxonomy );
		}

		// Notify listeners that post has been imported.
		do_action( 'sme_post_imported', $post, $this->batch );
	}

	/**
	 * Import all post meta for a post.
	 *
	 * @param array $posts
	 */
	public function import_posts_meta( array $posts ) {
		foreach ( $posts as $post ) {
			$this->import_post_meta( $post );
		}
	}

	/**
	 * Import postmeta for a specific post.
	 *
	 * Never call before all posts has been imported! In case you do
	 * relationships between post IDs on content stage and production has not
	 * been established and import of postmeta will fail!
	 *
	 * Start by changing content staging post IDs to production IDs.
	 *
	 * The content staging post ID is used as a key in the post relations
	 * array and the production post ID is used as value.
	 *
	 * @param Post $post
	 */
	public function import_post_meta( Post $post ) {

		$meta = $post->get_meta();

		// Keys in postmeta table containing relationship to another post.
		$keys = $this->batch->get_post_rel_keys();

		// Stage ID of current post.
		$stage_id = $post->get_id();

		// Get the production ID of the current post.
		$prod_id = $this->post_dao->get_id_by_guid( $post->get_guid() );

		// Post not found on production.
		if ( ! $prod_id ) {
			$message = sprintf( 'Post not found on production. Stage ID %d, GUID: %s', $stage_id, $prod_id );
			$this->api->add_deploy_message( $this->batch->get_id(), $message, 'error' );
			return;
		}

		for ( $i = 0; $i < count( $meta ); $i++ ) {

			// Update post ID to point at the post ID on production.
			$meta[$i]['post_id'] = $prod_id;

			// TODO Remove check for "master"
			if ( in_array( $meta[$i]['meta_key'], $keys ) && ! empty( $meta[$i]['meta_value'] ) && $meta[$i]['meta_value'] !== 'master' ) {

				// Post ID this meta value is referring to.
				$referenced_post_id = $this->post_dao->get_id_by_guid( $meta[$i]['meta_value'] );

				// Referenced post could not be found.
				if ( ! $referenced_post_id ) {

					$referenced_post_id = 0;

					$message  = 'Failed updating relationship between posts (blog ID %d). The relationship is defined in the postmeta table. ';
					$message .= '<ul>';
					$message .= '<li>Stage ID referencing post: %d</li>';
					$message .= '<li>Production ID referencing post: %d</li>';
					$message .= '<li>Key holding referenced post: %s</li>';
					$message .= '<li>GUID referenced post: %s</li>';
					$message .= '</ul>';

					$message = sprintf( $message, get_current_blog_id(), $stage_id, $prod_id, $meta[$i]['meta_key'], $meta[$i]['meta_value'] );

					$this->api->add_deploy_message( $this->batch->get_id(), $message, 'warning' );
				}

				// Update meta value to point at the post ID on production.
				$meta[$i]['meta_value'] = $referenced_post_id;
			}
		}

		$this->postmeta_dao->update_postmeta_by_post( $prod_id, $meta );
	}

	/**
	 * Import attachments.
	 */
	public function import_attachments() {

		/*
		 * Make it possible for third-party developers to inject their custom
		 * attachment import functionality.
		 */
		do_action( 'sme_import_custom_attachment_importer', $this->batch->get_attachments(), $this->batch );

		/*
		 * Make it possible for third-party developers to alter the list of
		 * attachments to import.
		 */
		$this->batch->set_attachments(
			apply_filters( 'sme_import_attachments', $this->batch->get_attachments(), $this->batch )
		);

		foreach ( $this->batch->get_attachments() as $attachment ) {
			$this->import_attachment( $attachment );
		}
	}

	/**
	 * Import a single attachment.
	 *
	 * @param array $attachment
	 * @return bool
	 */
	public function import_attachment( array $attachment ) {
		$upload_dir = wp_upload_dir();
		$filepath   = $upload_dir['basedir'] . $attachment['subdir'] . '/';

		if ( ! is_dir( $filepath ) && ! wp_mkdir_p( $filepath ) ) {
			/*
			 * Directory to place image in does not exist and we were not able to
			 * create it.
			 */
			do_action( 'import_attachment_failure', $attachment, $filepath, $this->batch );
			return false;
		}

		foreach ( $attachment['items'] as $item ) {

			// Get file if it exists.
			if ( $image = file_get_contents( $attachment['url'] . '/' . $item ) ) {
				file_put_contents( $filepath . $item, $image );
			}
		}

		return true;
	}

	/**
	 * Import taxonomy.
	 *
	 * @param Taxonomy $taxonomy
	 */
	public function import_taxonomy( Taxonomy $taxonomy ) {

		// Get the term.
		$term = $taxonomy->get_term();

		// Import term.
		if ( $term !== null ) {
			$this->import_term( $taxonomy->get_term() );
		}

		// If a parent taxonomy exists, import it.
		if ( $taxonomy->get_parent() !== null ) {
			$this->import_taxonomy( $taxonomy->get_parent() );
		}

		// Taxonomy ID on production environment.
		try {
			$this->taxonomy_dao->get_taxonomy_id_by_taxonomy( $taxonomy );
		} catch ( Exception $e ) {
			$this->api->add_deploy_message( $this->batch->get_id(), $e->getMessage(), 'warning' );
		}

		if ( ! $taxonomy->get_id() ) {
			// This taxonomy does not exist on production, create it.
			$this->taxonomy_dao->insert( $taxonomy );
		} else {
			// This taxonomy exists on production, update it.
			$this->taxonomy_dao->update_taxonomy( $taxonomy );
		}
	}

	/**
	 * Import term.
	 *
	 * @param Term $term
	 */
	public function import_term( Term $term ) {

		// Term ID on production environment.
		$term_id = $this->term_dao->get_term_id_by_slug( $term->get_slug() );
		$term->set_id( $term_id );

		if ( ! $term->get_id() ) {
			// This term does not exist on production, create it.
			$this->term_dao->insert( $term );
		} else {
			// This term exists on production, update it.
			$this->term_dao->update_term( $term );
		}
	}

	/**
	 * Import data added by a third-party.
	 */
	public function import_custom_data() {
		foreach ( $this->batch->get_custom_data() as $addon => $data ) {
			do_action( 'sme_import_' . $addon, $data, $this->batch );
		}
	}

	/**
	 * Update the relationship between posts and their parent posts.
	 */
	public function update_parent_post_relations() {
		foreach ( $this->batch->get_posts() as $post ) {
			$this->update_parent_post_relation( $post );
		}
	}

	/**
	 * Update the relationship between a post and its parent post.
	 *
	 * @param Post $post
	 */
	public function update_parent_post_relation( Post $post ) {

		$parent = $post->get_parent();

		if ( ! $parent ) {
			return;
		}

		// Get production IDs.
		$prod_id        = $this->post_dao->get_id_by_guid( $post->get_guid() );
		$parent_prod_id = $this->post_dao->get_id_by_guid( $parent->get_guid() );

		if ( ! $prod_id ) {
			$msg = sprintf( 'No post with GUID %s found on production.', $post->get_guid() );
			$this->api->add_deploy_message( $this->batch->get_id(), $msg, 'error' );
			return;
		}

		if ( ! $parent_prod_id ) {
			$msg = sprintf( 'No post with GUID %s found on production.', $parent->get_guid() );
			$this->api->add_deploy_message( $this->batch->get_id(), $msg, 'error' );
			return;
		}

		$this->post_dao->update(
			array( 'post_parent' => $parent_prod_id ),
			array( 'ID' => $prod_id ),
			array( '%d' ),
			array( '%d' )
		);
	}

	/**
	 * Publish multiple posts sent to production.
	 */
	public function publish_posts() {
		foreach ( $this->batch->get_posts() as $post ) {
			$this->publish_post( $post );
		}
	}

	/**
	 * Publish a post sent to production.
	 *
	 * New posts that are sent to production will have a post status of
	 * 'publish'. Since we don't want the post to go public until all data
	 * has been synced from content stage, post status has been changed to
	 * 'draft'. Post status is now changed back to 'publish'.
	 *
	 * @param Post $post
	 */
	public function publish_post( Post $post ) {

		$prod_id = $this->post_dao->get_id_by_guid( $post->get_guid() );

		if ( ! $prod_id ) {
			$msg = sprintf( 'No post with GUID %s found on production.', $post->get_guid() );
			$this->api->add_deploy_message( $this->batch->get_id(), $msg, 'error' );
			return;
		}

		/*
		 * Trigger an action before changing the post status to give other plug-ins
		 * a chance to act before the post goes public (e.g. cache warm-up).
		 */
		do_action( 'sme_pre_publish_post', $prod_id, get_current_blog_id() );

		/*
		 * Publish the new post if post status from staging environment is set to
		 * "publish".
		 */
		if ( $post->get_post_status() == 'publish' ) {
			$this->post_dao->update_post_status( $prod_id, 'publish' );
		}
	}

	public function tear_down() {

		// Import finished, update import status.
		$this->api->set_deploy_status( $this->batch->get_id(), 3 );

		do_action( 'sme_imported', $this->batch );
	}

	/**
	 * Add diff between stage post and production post.
	 *
	 * @param Post_Env_Diff $diff
	 */
	private function add_post_diff( Post_Env_Diff $diff ) {

		// Store diff if it does not already exist.
		if ( ! isset( $this->post_diffs[$diff->get_stage_id()] ) ) {

			// Store diff in database.
			add_post_meta( $this->batch->get_id(), 'sme_post_diff', $diff->to_array() );

			// Store diff in property.
			$this->post_diffs[$diff->get_stage_id()] = $diff;
		}
	}
}