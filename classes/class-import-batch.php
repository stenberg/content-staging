<?php
namespace Me\Stenberg\Content\Staging;

use Me\Stenberg\Content\Staging\DB\Batch_Importer_DAO;
use Me\Stenberg\Content\Staging\DB\Post_DAO;
use Me\Stenberg\Content\Staging\DB\Postmeta_DAO;
use Me\Stenberg\Content\Staging\DB\Term_DAO;
use Me\Stenberg\Content\Staging\DB\User_DAO;
use Me\Stenberg\Content\Staging\Models\Batch_Importer;
use Me\Stenberg\Content\Staging\Models\Post;
use Me\Stenberg\Content\Staging\Models\Relationships\Post_Taxonomy;
use Me\Stenberg\Content\Staging\Models\Taxonomy;
use Me\Stenberg\Content\Staging\Models\Term;

/**
 * Class Import_Batch
 *
 * @package Me\Stenberg\Content\Staging
 *
 * @todo Consider moving 'import_*' methods in this class to the
 * Batch_Importer model. Might want an import per import type though,
 * e.g. a Post_Importer etc.
 */
class Import_Batch {

	private $batch_importer_dao;
	private $post_dao;
	private $postmeta_dao;
	private $term_dao;
	private $user_dao;

	/**
	 * Array of postmeta keys that contain relationships between posts.
	 *
	 * @var array
	 */
	private $postmeta_keys;

	/**
	 * Array storing the relation between a post and its parent post.
	 *
	 * Key = Post ID.
	 * Value = Post GUID.
	 *
	 * @var array
	 */
	private $parent_post_relations;

	/**
	 * Array to keep track on the relation between a users ID on
	 * content stage and its ID on production:
	 *
	 * Key = Content stage user ID.
	 * Value = Production user ID.
	 *
	 * @var array
	 */
	private $user_relations;

	/**
	 * Array to keep track on the relation between a posts ID on
	 * content stage and its ID on production:
	 *
	 * Key = Content stage post ID.
	 * Value = Production post ID.
	 *
	 * @var array
	 */
	private $post_relations;

	/**
	 * Array where we store all posts that should be published when data has
	 * been synced from content stage to production.
	 *
	 * @var array
	 */
	private $posts_to_publish;

	/**
	 * Construct object, dependencies are injected.
	 *
	 * @param Batch_Importer_DAO $batch_importer_dao
	 * @param Post_DAO $post_dao
	 * @param Postmeta_DAO $postmeta_dao
	 * @param Term_DAO $term_dao
	 * @param User_DAO $user_dao
	 */
	public function __construct( Batch_Importer_DAO $batch_importer_dao, Post_DAO $post_dao,
								 Postmeta_DAO $postmeta_dao, Term_DAO $term_dao, User_DAO $user_dao ) {
		$this->batch_importer_dao    = $batch_importer_dao;
		$this->post_dao              = $post_dao;
		$this->postmeta_dao          = $postmeta_dao;
		$this->term_dao              = $term_dao;
		$this->user_dao              = $user_dao;
		$this->postmeta_keys         = array();
		$this->parent_post_relations = array();
		$this->user_relations        = array();
		$this->post_relations        = array();
		$this->posts_to_publish      = array();
	}

	/**
	 * Runs on production server when a batch of data has been received.
	 */
	public function init() {

		// Make sure an importer ID has been provided.
		if ( ! isset( $_GET['sme_batch_importer_id'] ) || ! $_GET['sme_batch_importer_id'] ) {
			return;
		}

		// Make sure a batch importer key has been provided.
		if ( ! isset( $_GET['sme_import_batch_key'] ) || ! $_GET['sme_import_batch_key'] ) {
			return;
		}

		$importer_id = intval( $_GET['sme_batch_importer_id'] );
		$import_key  = $_GET['sme_import_batch_key'];

		// Get batch importer from database.
		$importer = $this->batch_importer_dao->get_importer_by_id( $importer_id );

		// No importer found, error.
		if ( ! $importer ) {
			error_log( sprintf( 'Batch importer with ID %d failed to start.', $importer_id ) );
			wp_die( __( 'Something went wrong', 'sme-content-staging' ) );
		}

		// Validate key.
		if ( $import_key !== $importer->get_key() ) {
			error_log( 'Unauthorized batch import attempt terminated.' );
			wp_die( __( 'Something went wrong', 'sme-content-staging' ) );
		}

		// Import running.
		$importer->set_status( 1 );
		$importer->generate_key();
		$this->batch_importer_dao->update_importer( $importer );

		// Get the batch.
		$batch = $importer->get_batch();

		// Get postmeta keys who's records contains relations between posts.
		$this->postmeta_keys = apply_filters( 'sme_post_relationship_keys', array() );

		// Import attachments.
		$this->import_attachments( $importer );

		// Create/update users.
		$this->import_users( $batch->get_users() );

		// Create/update posts.
		foreach ( $batch->get_posts() as $post ) {
			$this->import_post( $post );
		}

		// Import postmeta.
		foreach ( $batch->get_posts() as $post ) {
			$this->import_postmeta( $post->get_meta() );
		}

		// Update relationship between posts and their parents.
		$this->update_parent_post_relations();

		// Import custom data.
		$this->import_custom_data( $importer );


		// Publish posts.
		$this->publish_posts();

		// Import finished, set success message and update import status.
		$importer->add_message( 'Batch has been successfully imported!', 'success' );
		$importer->set_status( 3 );
		$this->batch_importer_dao->update_importer( $importer );

		/*
		 * Delete importer. Importer is not actually deleted, just set to draft
		 * mode. This is important since we need to access e.g. meta data telling
		 * us the status of the import even when import has finished.
		 */
		$this->batch_importer_dao->delete_importer( $importer );
	}

	/**
	 * Import users.
	 *
	 * @param array $users
	 */
	private function import_users( $users ) {

		foreach ( $users as $user ) {

			/*
			 * See if user exists in database.
			 *
			 * @todo Here we are assuming that there cannot be two users with the
			 * same user_login. This might be wrong. Investigate!
			 * Consider using WP function get_user_by.
			 *
			 * @see http://codex.wordpress.org/Function_Reference/get_user_by
			 */
			$existing = $this->user_dao->get_user_by_user_login( $user->get_user_login() );

			// Create if user does not exist, update otherwise.
			if ( empty( $existing ) ) {
				$stage_user_id = $user->get_id();
				$prod_user_id  = $this->user_dao->insert_user( $user );

				$user->set_id( $prod_user_id );
			} else {
				$stage_user_id = $user->get_id();
				$prod_user_id  = $existing->get_id();

				$user->set_id( $prod_user_id );
				$this->user_dao->update_user( $user, array( 'ID' => $user->get_id() ), array( '%d' ) );
				$this->user_dao->delete_usermeta( array( 'user_id' => $prod_user_id ), array( '%d' ) );
			}

			// Add to the user_relations property
			$this->user_relations[$stage_user_id] = $prod_user_id;
		}

		// Import usermeta.
		$this->import_usermeta( $users );
	}

	/**
	 * Import usermeta.
	 *
	 * Start by changing content staging user IDs to production IDs.
	 *
	 * The content staging user ID is used as a key in the user relations
	 * array and the production user ID is used as value.
	 *
	 * @param array $users
	 */
	private function import_usermeta( $users ) {
		foreach ( $users as $user ) {
			foreach ( $user->get_meta() as $meta ) {
				$meta['user_id'] = $user->get_id();
				$this->user_dao->insert_usermeta( $meta );
			}
		}
	}

	/**
	 * Import post.
	 *
	 * @param Post $post
	 */
	private function import_post( Post $post ) {

		// Post ID on content staging environment.
		$stage_post_id = $post->get_id();

		// Taxonomy ID on production environment.
		$this->post_dao->get_id_by_guid( $post );

		/*
		 * E.g. attachments wont have a post status of publish. We need this flag
		 * to keep track on what posts we should change post status for after all
		 * content has been stored on production.
		 */
		$is_published = false;

		if ( $post->get_post_status() == 'publish' ) {
			$is_published = true;
		}

		if ( ! $post->get_id() ) {

			// Do not publish post immediately.
			if ( $is_published ) {
				$post->set_post_status( 'draft' );
			}

			// This post does not exist on production, create it.
			$this->post_dao->insert_post( $post );

			/*
			 * Store ID of post so we can publish it when data has been completely
			 * synced.
			 */
			if ( $is_published ) {
				$this->posts_to_publish[] = $post->get_id();
			}
		} else {
			// This post exists on production, update it.
			$this->post_dao->update_post( $post );
			$this->postmeta_dao->delete_postmeta( array( 'post_id' => $post->get_id() ), array( '%d' ) );
		}

		$this->post_relations[$stage_post_id] = $post->get_id();

		// Store relation between a post and its parent post.
		if ( $post->get_post_parent_guid() ) {
			$this->parent_post_relations[$post->get_id()] = $post->get_post_parent_guid();
		}

		// Store relation between post ID on content stage and ID on production.
		$this->post_relations[$stage_post_id] = $post->get_id();

		// Import post/taxonomy relationships.
		foreach ( $post->get_post_taxonomy_relationships() as $post_taxonomy ) {
			$this->import_post_taxonomy_relationship( $post_taxonomy );
		}
	}

	/**
	 * Import postmeta.
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
	 * @param array $postmeta
	 */
	private function import_postmeta( array $postmeta ) {

		foreach ( $postmeta as $meta ) {
			if ( in_array( $meta['meta_key'], $this->postmeta_keys ) ) {

				/*
				 * The meta value must be an integer pointing at the ID of the post
				 * that the post whose postmeta we are currently importing has a
				 * relationship to.
				 */
				if ( isset( $this->post_relations[$meta['meta_value']] ) ) {
					$meta['meta_value'] = $this->post_relations[$meta['meta_value']];
				} else {
					error_log( 'Trying to update dependency between posts. Relationship is defined in postmeta (post_id: ' . $this->post_relations[$meta['post_id']] . ', meta_key: ' . $meta['meta_key'] . ', meta_value: ' . $meta['meta_value'] . ') where post_id is the post ID that has a relationship to the post defined in meta_value. If meta_value does not contain a valid post ID relationship between posts cannot be maintained.' );
				}
			}

			$meta['post_id'] = $this->post_relations[$meta['post_id']];
			$this->postmeta_dao->insert_postmeta( $meta );
		}
	}

	/**
	 * Import attachments.
	 *
	 * @param Batch_Importer $importer
	 */
	private function import_attachments( Batch_Importer $importer ) {

		$attachments = $importer->get_batch()->get_attachments();

		/*
		 * Make it possible for third-party developers to inject their custom
		 * attachment import functionality.
		 */
		do_action( 'sme_import_attachments', $attachments, $importer );

		/*
		 * Make it possible for third-party developers to alter the list of
		 * attachments to import.
		 */
		$attachments = apply_filters( 'sme_import_attachments', $attachments, $importer );

		$upload_dir = wp_upload_dir();
		foreach ( $attachments as $attachment ) {
			$path = $attachment['path'];
			$filepath = $upload_dir['basedir'] . '/' . $path . '/';

			if ( ! is_dir( $filepath ) ) {
				mkdir( $filepath );
			}

			foreach ( $attachment['sizes'] as $size ) {
				$basename = pathinfo( $size, PATHINFO_BASENAME );

				// Get file if it exists.
				if ( $image = file_get_contents( $size ) ) {
					file_put_contents( $filepath . $basename, $image );
				}
			}
		}
	}

	/**
	 * Import post/taxonomy relationship.
	 *
	 * @param Post_Taxonomy $post_taxonomy
	 */
	private function import_post_taxonomy_relationship( Post_Taxonomy $post_taxonomy ) {

		// Import taxonomy.
		$this->import_taxonomy( $post_taxonomy->get_taxonomy() );

		/*
		 * Check if a relationship between a post and a taxonomy exists on
		 * production.
		 */
		$has_relationship = $this->term_dao->has_post_taxonomy_relationship( $post_taxonomy );

		// Check if this is a new term-taxonomy.
		if ( ! $has_relationship ) {
			/*
			 * This post/taxonomy relationship does not exist on production,
			 * create it.
			 */
			$this->term_dao->insert_post_taxonomy_relationship( $post_taxonomy );
		} else {
			// This post/taxonomy relationship exists on production, update it.
			$this->term_dao->update_post_taxonomy_relationship( $post_taxonomy );
		}
	}

	/**
	 * Import taxonomy.
	 *
	 * @param Taxonomy $taxonomy
	 */
	private function import_taxonomy( Taxonomy $taxonomy ) {

		$this->import_term( $taxonomy->get_term() );

		// If a parent taxonomy exists, import it.
		if ( $taxonomy->get_parent() instanceof Taxonomy ) {
			$this->import_taxonomy( $taxonomy->get_parent() );
		}

		// Taxonomy ID on production environment.
		$this->term_dao->get_taxonomy_id_by_taxonomy( $taxonomy );

		if ( ! $taxonomy->get_id() ) {
			// This taxonomy does not exist on production, create it.
			$this->term_dao->insert_taxonomy( $taxonomy );
		} else {
			// This taxonomy exists on production, update it.
			$this->term_dao->update_taxonomy( $taxonomy );
		}
	}

	/**
	 * Import term.
	 *
	 * @param Term $term
	 */
	private function import_term( Term $term ) {

		// Term ID on content staging environment.
		$stage_term_id = $term->get_id();

		// Term ID on production environment.
		$this->term_dao->get_term_id_by_slug( $term );

		if ( ! $term->get_id() ) {
			// This term does not exist on production, create it.
			$this->term_dao->insert_term( $term );
		} else {
			// This term exists on production, update it.
			$this->term_dao->update_term( $term );
		}
	}

	/**
	 * Import data added by a third-party.
	 *
	 * @param Batch_Importer $importer
	 */
	private function import_custom_data( Batch_Importer $importer ) {
		foreach ( $importer->get_batch()->get_custom_data() as $addon => $data ) {
			do_action( 'sme_import_' . $addon, $data, $importer );
		}
	}

	/**
	 * Update the relationship between a post and its parent post.
	 */
	private function update_parent_post_relations() {
		foreach ( $this->parent_post_relations as $post_id => $parent_guid ) {
			$parent = $this->post_dao->get_post_by_guid( $parent_guid );
			$this->post_dao->update(
				'posts',
				array( 'post_parent' => $parent->get_id() ),
				array( 'ID' => $post_id ),
				array( '%d' ),
				array( '%d' )
			);
		}
	}

	/**
	 * Publish all posts sent to production.
	 *
	 * New posts that are sent to production will have a post status of
	 * 'publish'. Since we don't want the post to go public until all data
	 * has been synced from content stage, post status has been changed to
	 * 'draft'. Post status is now chnaged back to 'publish'.
	 */
	private function publish_posts() {
		foreach ( $this->posts_to_publish as $post_id ) {
			$this->post_dao->update(
				'posts',
				array( 'post_status' => 'publish' ),
				array( 'ID' => $post_id ),
				array( '%s' ),
				array( '%d' )
			);
		}
	}

}