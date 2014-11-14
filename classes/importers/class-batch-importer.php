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
	 * @var string
	 */
	protected $type;

	/**
	 * @var Batch_Import_Job
	 */
	protected $job;

	/**
	 * Array storing the relationship between a post from the staging
	 * environment and the same post on the production environment.
	 *
	 * @var array
	 */
	protected $post_env_diff;

	/**
	 * @var Batch_Import_Job_DAO
	 */
	protected $import_job_dao;

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
	 * @var Taxonomy_DAO
	 */
	private $taxonomy_dao;

	/**
	 * @var Term_DAO
	 */
	private $term_dao;

	/**
	 * @var User_DAO
	 */
	private $user_dao;

	/**
	 * Constructor.
	 *
	 * @param string $type
	 * @param Batch_Import_Job $job
	 */
	protected function __construct( $type, Batch_Import_Job $job ) {
		$this->type                  = $type;
		$this->job                   = $job;
		$this->import_job_dao        = Helper_Factory::get_instance()->get_dao( 'Batch_Import_Job' );
		$this->post_dao              = Helper_Factory::get_instance()->get_dao( 'Post' );
		$this->post_taxonomy_dao     = Helper_Factory::get_instance()->get_dao( 'Post_Taxonomy' );
		$this->postmeta_dao          = Helper_Factory::get_instance()->get_dao( 'Postmeta' );
		$this->taxonomy_dao          = Helper_Factory::get_instance()->get_dao( 'Taxonomy' );
		$this->term_dao              = Helper_Factory::get_instance()->get_dao( 'Term' );
		$this->user_dao              = Helper_Factory::get_instance()->get_dao( 'User' );
		$this->post_env_diff         = array();
	}

	/**
	 * Trigger importer.
	 */
	abstract function run();

	/**
	 * @return string
	 */
	public function get_type() {
		return $this->type;
	}

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
	protected function import_users( $users ) {
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
	protected function import_user( User $user ) {
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
	 * Import post.
	 *
	 * @param Post $post
	 */
	protected function import_post( Post $post ) {

		/*
		 * Create object that can keep track of differences between stage and
		 * production post.
		 */
		$post_diff = new Post_Env_Diff( $post );
		$post_diff->set_stage_id( $post->get_id() );
		$post_diff->set_stage_status( $post->get_post_status() );

		/*
		 * Check if post already exist on production, if it does then add the old
		 * production post ID to the diff and update the GUID to indicate that
		 * this post will now be a revision.
		 */
		if ( ( $prod_revision = $this->post_dao->get_by_guid( $post->get_guid() ) ) !== null ) {
			$post_diff->set_revision( $prod_revision );
			$this->post_dao->update_guid( $prod_revision->get_id(), $prod_revision->get_guid() . '-rev' );
		}

		// Insert post.
		$this->post_dao->insert( $post );

		/*
		 * If a old revision of this post exist the
		 */

		// Add post diff to array of post diffs.
		$this->post_env_diff[$post_diff->get_stage_id()] = $post_diff;

		// Import post/taxonomy relationships.
		foreach ( $post->get_post_taxonomy_relationships() as $post_taxonomy ) {
			$this->import_post_taxonomy_relationship( $post_taxonomy );
		}

		$this->job->add_message(
			sprintf(
				'Post <strong>%s</strong> has been successfully imported.',
				$post->get_title()
			),
			'success'
		);

		$this->import_job_dao->update_job( $this->job );
	}

	/**
	 * Import all post meta for a post.
	 *
	 * @param array $posts
	 */
	protected function import_all_postmeta( array $posts) {
		foreach ( $posts as $post ) {
			$this->import_postmeta( $post );
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
	protected function import_postmeta( Post $post ) {

		$meta = $post->get_meta();

		for ( $i = 0; $i < count( $meta ); $i++ ) {
			if ( in_array( $meta[$i]['meta_key'], $this->job->get_batch()->get_post_rel_keys() ) ) {

				/*
				 * The meta value must be an integer pointing at the ID of the post
				 * that the post whose post meta we are currently importing has a
				 * relationship to.
				 */
				if ( isset( $this->post_env_diff[$meta[$i]['meta_value']] ) ) {
					$meta[$i]['meta_value'] = $this->post_env_diff[$meta[$i]['meta_value']]->get_post()->get_id();
				} else {
					error_log(
						sprintf(
							'Trying to update dependency between posts. Relationship is defined in postmeta (post_id: %d, meta_key: %s, meta_value: %s) where post_id is the post ID that has a relationship to the post defined in meta_value. If meta_value does not contain a valid post ID relationship between posts cannot be maintained.',
							$this->post_env_diff[$meta[$i]['post_id']]->get_post()->get_id(),
							$meta[$i]['meta_key'],
							$meta[$i]['meta_value']
						)
					);
				}
			}

			$meta[$i]['post_id'] = $this->post_env_diff[$meta[$i]['post_id']]->get_post()->get_id();
		}

		$this->postmeta_dao->insert_post_meta( $meta );
	}

	/**
	 * Import attachments.
	 */
	protected function import_attachments() {

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
	protected function import_attachment( array $attachment ) {
		$upload_dir = wp_upload_dir();
		$filepath   = $upload_dir['basedir'] . $attachment['subdir'] . '/';

		if ( ! is_dir( $filepath ) && ! wp_mkdir_p( $filepath ) ) {
			/*
			 * Directory to place image in does not exist and we were not able to
			 * create it. Create and set an error message.
			 */

			$failed_attachment = '';

			if ( isset( $attachment['items'][0] ) ) {
				$failed_attachment = sprintf(
					' Attachment %s and generated sizes could not be deployed to production. This is most likely a file permission error, make sure your web server can write to the image upload directory.',
					$attachment['items'][0]
				);
			}

			// Add error message.
			$this->job->add_message(
				sprintf(
					'Failed creating directory %s.%s',
					$filepath,
					$failed_attachment
				),
				'warning'
			);

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
	protected function import_post_taxonomy_relationship( Post_Taxonomy $post_taxonomy ) {
		// Import taxonomy.
		$this->import_taxonomy( $post_taxonomy->get_taxonomy() );

		// Import relationship between post and taxonomy.
		$this->post_taxonomy_dao->insert( $post_taxonomy );
	}

	/**
	 * Import taxonomy.
	 *
	 * @param Taxonomy $taxonomy
	 */
	protected function import_taxonomy( Taxonomy $taxonomy ) {

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
	protected function import_term( Term $term ) {
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
	 *
	 * @param Batch_Import_Job $importer
	 */
	protected function import_custom_data( Batch_Import_Job $importer ) {
		foreach ( $importer->get_batch()->get_custom_data() as $addon => $data ) {
			do_action( 'sme_import_' . $addon, $data, $importer );
		}
	}

	/**
	 * Update the relationship between posts and their parent posts.
	 */
	protected function update_parent_post_relations() {
		foreach ( $this->post_env_diff as $diff ) {
			$this->update_parent_post_relation( $diff->get_post() );
		}
	}

	/**
	 * Update the relationship between a post and its parent post.
	 *
	 * @param Post $post
	 */
	protected function update_parent_post_relation( Post $post ) {
		if ( $post->get_parent() === null ) {
			return;
		}

		$parent = $this->post_dao->get_by_guid( $post->get_parent()->get_guid() );
		$this->post_dao->update(
			array( 'post_parent' => $parent->get_id() ),
			array( 'ID' => $post->get_id() ),
			array( '%d' ),
			array( '%d' )
		);
	}

	/**
	 * Publish all posts sent to production.
	 *
	 * New posts that are sent to production will have a post status of
	 * 'publish'. Since we don't want the post to go public until all data
	 * has been synced from content stage, post status has been changed to
	 * 'draft'. Post status is now changed back to 'publish'.
	 */
	protected function publish_posts() {
		foreach ( $this->post_env_diff as $diff ) {

			/*
			 * Publish the new post if post status from staging environment is set to
			 * "publish".
			 */
			if ( $diff->get_stage_status() == 'publish' ) {
				$this->post_dao->update_post_status( $diff->get_post(), 'publish' );
			}

			/*
			 * Turn the old version of the post into a revision (if an old version
			 * exists).
			 */
			if ( $diff->get_revision() !== null ) {
				$this->post_dao->make_revision( $diff->get_revision(), $diff->get_post() );
			}
		}
	}

	protected function tear_down() {
		$links  = array();
		$output = '';

		foreach ( $this->job->get_batch()->get_posts() as $post ) {
			$links[] = array(
				'link' => get_permalink( $post->get_id() ),
				'post' => $post,
			);
		}

		$links = apply_filters( 'sme_imported_post_links', $links );

		foreach ( $links as $link ) {
			$output .= '<li><a href="' . $link['link'] . '" target="_blank">' . $link['post']->get_title() . '</a></li>';
		}

		if ( $output !== '' ) {
			$output = '<ul>' . $output . '</ul>';
			$this->job->add_message( '<h3>Posts deployed to the live site:</h3>' . $output );
		}

		do_action( 'sme_imported', $this->job );
	}
}