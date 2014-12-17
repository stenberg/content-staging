<?php
namespace Me\Stenberg\Content\Staging\Importers;

use Me\Stenberg\Content\Staging\DB\Batch_Import_Job_DAO;
use Me\Stenberg\Content\Staging\DB\Post_DAO;
use Me\Stenberg\Content\Staging\DB\Post_Taxonomy_DAO;
use Me\Stenberg\Content\Staging\DB\Postmeta_DAO;
use Me\Stenberg\Content\Staging\DB\Taxonomy_DAO;
use Me\Stenberg\Content\Staging\DB\Term_DAO;
use Me\Stenberg\Content\Staging\DB\User_DAO;
use Me\Stenberg\Content\Staging\Helper_Factory;
use Me\Stenberg\Content\Staging\Models\Batch_Import_Job;
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
	 * @var Batch_Import_Job
	 */
	protected $job;

	/**
	 * @var Batch_Import_Job_DAO
	 */
	protected $import_job_dao;

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
	 * @param Batch_Import_Job $job
	 */
	protected function __construct( Batch_Import_Job $job ) {
		$this->job               = $job;
		$this->import_job_dao    = Helper_Factory::get_instance()->get_dao( 'Batch_Import_Job' );
		$this->post_dao          = Helper_Factory::get_instance()->get_dao( 'Post' );
		$this->post_taxonomy_dao = Helper_Factory::get_instance()->get_dao( 'Post_Taxonomy' );
		$this->postmeta_dao      = Helper_Factory::get_instance()->get_dao( 'Postmeta' );
		$this->taxonomy_dao      = Helper_Factory::get_instance()->get_dao( 'Taxonomy' );
		$this->term_dao          = Helper_Factory::get_instance()->get_dao( 'Term' );
		$this->user_dao          = Helper_Factory::get_instance()->get_dao( 'User' );

		// Get diffs from database.
		$this->post_diffs = $this->post_dao->get_post_diffs( $this->job );
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
	 * @param Batch_Import_Job $job
	 */
	public function set_job( Batch_Import_Job $job) {
		$this->job = $job;
	}

	/**
	 * @return Batch_Import_Job
	 */
	public function get_job() {
		return $this->job;
	}

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
		do_action( 'sme_post_import', $post );

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
			}

			// Insert post.
			$this->post_dao->insert( $post );

			// Store new production ID in diff.
			$post_diff->set_prod_id( $post->get_id() );
		}

		// Add post diff to array of post diffs.
		$this->add_post_diff( $post_diff );

		// Import post/taxonomy relationships.
		foreach ( $post->get_post_taxonomy_relationships() as $post_taxonomy ) {
			$this->import_post_taxonomy_relationship( $post_taxonomy );
		}

		// Notify listeners that post has been imported.
		do_action( 'sme_post_imported', $post, $this->job );
	}

	/**
	 * Import all post meta for a post.
	 *
	 * @param array $posts
	 */
	public function import_posts_meta( array $posts) {
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

		$stage_id = null;
		$prod_id  = null;
		$meta     = $post->get_meta();

		// Keys in postmeta table containing relationship to another post.
		$keys = $this->job->get_batch()->get_post_rel_keys();

		for ( $i = 0; $i < count( $meta ); $i++ ) {
			$stage_id = $meta[$i]['post_id'];
			$prod_id  = $this->post_diffs[$stage_id]->get_prod_id();

			if ( in_array( $meta[$i]['meta_key'], $keys ) ) {

				/*
				 * The meta value must be an integer pointing at the ID of the post
				 * that the post whose post meta we are currently importing has a
				 * relationship to.
				 */
				if ( isset( $this->post_diffs[$meta[$i]['meta_value']] ) ) {
					$meta[$i]['meta_value'] = $prod_id;
				} else {
					error_log(
						sprintf(
							'Trying to update dependency between posts. Relationship is defined in postmeta (post_id: %d, meta_key: %s, meta_value: %s) where post_id is the post ID that has a relationship to the post defined in meta_value. If meta_value does not contain a valid post ID relationship between posts cannot be maintained.',
							$prod_id,
							$meta[$i]['meta_key'],
							$meta[$i]['meta_value']
						)
					);
				}
			}

			$meta[$i]['post_id'] = $prod_id;
		}

		if ( $prod_id ) {
			$this->postmeta_dao->update_postmeta_by_post( $prod_id, $meta );
		}
	}

	/**
	 * Import attachments.
	 */
	public function import_attachments() {

		/*
		 * Make it possible for third-party developers to inject their custom
		 * attachment import functionality.
		 */
		do_action( 'sme_import_custom_attachment_importer', $this->job->get_batch()->get_attachments(), $this->job );

		/*
		 * Make it possible for third-party developers to alter the list of
		 * attachments to import.
		 */
		$this->job->get_batch()->set_attachments(
			apply_filters( 'sme_import_attachments', $this->job->get_batch()->get_attachments(), $this->job )
		);

		foreach ( $this->job->get_batch()->get_attachments() as $attachment ) {
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
			do_action( 'import_attachment_failure', $attachment, $filepath, $this->job );
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
	 * Import post/taxonomy relationship.
	 *
	 * @param Post_Taxonomy $post_taxonomy
	 */
	public function import_post_taxonomy_relationship( Post_Taxonomy $post_taxonomy ) {

		// Import taxonomy.
		$this->import_taxonomy( $post_taxonomy->get_taxonomy() );

		/*
		 * Check if a relationship between a post and a taxonomy exists on
		 * production.
		 */
		$has_relationship = $this->post_taxonomy_dao->has_post_taxonomy_relationship( $post_taxonomy );

		// Check if this is a new term-taxonomy.
		if ( ! $has_relationship ) {
			/*
			 * This post/taxonomy relationship does not exist on production,
			 * create it.
			 */
			$this->post_taxonomy_dao->insert( $post_taxonomy );
		} else {
			// This post/taxonomy relationship exists on production, update it.
			$this->post_taxonomy_dao->update_post_taxonomy( $post_taxonomy );
		}
	}

	/**
	 * Import taxonomy.
	 *
	 * @param Taxonomy $taxonomy
	 */
	public function import_taxonomy( Taxonomy $taxonomy ) {

		$this->import_term( $taxonomy->get_term() );

		// If a parent taxonomy exists, import it.
		if ( $taxonomy->get_parent() !== null ) {
			$this->import_taxonomy( $taxonomy->get_parent() );
		}

		// Taxonomy ID on production environment.
		$this->taxonomy_dao->get_taxonomy_id_by_taxonomy( $taxonomy );

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
		$this->term_dao->get_term_id_by_slug( $term );

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
		foreach ( $this->job->get_batch()->get_custom_data() as $addon => $data ) {
			do_action( 'sme_import_' . $addon, $data, $this->job );
		}
	}

	/**
	 * Update the relationship between posts and their parent posts.
	 */
	public function update_parent_post_relations() {
		foreach ( $this->post_diffs as $diff ) {
			$this->update_parent_post_relation( $diff );
		}
	}

	/**
	 * Update the relationship between a post and its parent post.
	 *
	 * @param Post_Env_Diff $diff
	 */
	public function update_parent_post_relation( Post_Env_Diff $diff ) {
		if ( ! $diff->get_parent_guid() ) {
			return;
		}

		$parent = $this->post_dao->get_by_guid( $diff->get_parent_guid() );
		$this->post_dao->update(
			array( 'post_parent' => $parent->get_id() ),
			array( 'ID' => $diff->get_prod_id() ),
			array( '%d' ),
			array( '%d' )
		);
	}

	/**
	 * Publish multiple posts sent to production.
	 */
	public function publish_posts() {
		foreach ( $this->post_diffs as $diff ) {
			$this->publish_post( $diff );
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
	 * @param Post_Env_Diff $diff
	 */
	public function publish_post( Post_Env_Diff $diff ) {
		/*
		 * Publish the new post if post status from staging environment is set to
		 * "publish".
		 */
		if ( $diff->get_stage_status() == 'publish' ) {
			$this->post_dao->update_post_status( $diff->get_prod_id(), 'publish' );
		}

		/*
		 * Turn the old version of the post into a revision (if an old version
		 * exists).
		 */
		if ( $diff->get_revision_id() ) {
			$this->post_dao->make_revision( $diff->get_revision_id(), $diff->get_prod_id() );
		}
	}

	public function tear_down() {

		do_action( 'sme_imported', $this->job );

		// Import finished, update import status.
		$this->job->set_status( 3 );
		$this->import_job_dao->update_job( $this->job );
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
			add_post_meta( $this->job->get_id(), 'sme_post_diff', $diff->to_array() );

			// Store diff in property.
			$this->post_diffs[$diff->get_stage_id()] = $diff;
		}
	}
}