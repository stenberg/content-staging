<?php
namespace Me\Stenberg\Content\Staging;

use \Exception;
use Me\Stenberg\Content\Staging\DB\Batch_DAO;
use Me\Stenberg\Content\Staging\DB\Batch_Importer_DAO;
use Me\Stenberg\Content\Staging\DB\Post_DAO;
use Me\Stenberg\Content\Staging\DB\Postmeta_DAO;
use Me\Stenberg\Content\Staging\DB\Term_DAO;
use Me\Stenberg\Content\Staging\DB\User_DAO;
use Me\Stenberg\Content\Staging\Models\Batch;
use Me\Stenberg\Content\Staging\Models\Taxonomy;

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
	 * Array to keep track on the relation between a terms-taxonomy ID on
	 * content stage and its ID on production:
	 *
	 * Key = Content stage term-taxonomy ID.
	 * Value = Production term-taxonomy ID.
	 *
	 * @var array
	 */
	private $term_taxonomy_relations;

	/**
	 * Array to keep track on the relation between a terms ID on
	 * content stage and its ID on production:
	 *
	 * Key = Content stage term ID.
	 * Value = Production term ID.
	 *
	 * @var array
	 */
	private $term_relations;

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
		$this->batch_importer_dao = $batch_importer_dao;
		$this->post_dao           = $post_dao;
		$this->postmeta_dao       = $postmeta_dao;
		$this->term_dao           = $term_dao;
		$this->user_dao           = $user_dao;

		$this->parent_post_relations   = array();
		$this->user_relations          = array();
		$this->post_relations          = array();
		$this->posts_to_publish        = array();
		$this->term_relations          = array();
		$this->term_taxonomy_relations = array();
	}

	/**
	 * Runs on production server when a batch of data has been received.
	 *
	 * @param int $batch_importer_id
	 */
	public function init( $batch_importer_id ) {

		error_log( 'Importing batch using importer with ID ' . $batch_importer_id . '...' );

		// Get batch importer from database.
		$importer = $this->batch_importer_dao->get_importer_by_id( $batch_importer_id );

		// No importer found, error
		if ( ! $importer ) {
			$importer->add_message(
				sprintf( 'Batch importer with ID %d failed to start.', $batch_importer_id ),
				'error'
			);
			$importer->set_status( 2 );
			$this->batch_importer_dao->update_importer( $importer );
			return;
		}

		// Import running.
		$importer->set_status( 1 );
		$this->batch_importer_dao->update_importer( $importer );

		// Get the batch.
		$batch = $importer->get_batch();

		// Import attachments.
		$this->import_attachments( $batch->get_attachments() );

		// Create/update users.
		$this->import_users( $batch->get_users() );

		// Import terms/taxonomies.
		$this->import_term_data( $batch->get_terms() );

		// Create/update posts.
		$this->import_posts( $batch->get_posts() );
		$this->update_parent_post_relations();

		// Import custom data.
		$this->import_custom_data( $batch->get_custom_data(), $batch );

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
	 * Import posts.
	 *
	 * @param array $posts
	 */
	private function import_posts( $posts ) {

		// Filter posts before they are created/updated.
		$posts = apply_filters( 'sme_sent_posts', $posts );

		foreach ( $posts as $post ) {

			/*
			 * E.g. attachments wont have a post status of publish. We need this flag
			 * to keep track on what posts we should change post status for after all
			 * content has been stored on production.
			 */
			$is_published = false;

			if ( $post->get_post_status() === 'publish' ) {
				$is_published = true;
			}

			// Store content stage post ID for later use.
			$stage_post_id = $post->get_id();

			// Get production revision of post if it exists.
			$prod_post = $this->post_dao->get_post_by_guid( $post->get_guid() );

			// Does this post already exist on production or is it new?
			if ( empty( $prod_post ) ) {

				// Do not publish post immediately.
				if ( $is_published ) {
					$post->set_post_status( 'draft' );
				}

				$prod_post_id = $this->post_dao->insert_post( $post );
				$post->set_id( $prod_post_id );

				// Store ID of post so we can publish it when data has been completely synced.
				if ( $is_published ) {
					$this->posts_to_publish[] = $prod_post_id;
				}
			} else {
				$prod_post_id = $prod_post->get_id();
				$post->set_id( $prod_post_id );

				$this->post_dao->update_post( $post );
				$this->postmeta_dao->delete_postmeta( array( 'post_id' => $prod_post_id ), array( '%d' ) );
			}

			// Store relation between a post and its parent post.
			if ( $post->get_post_parent_guid() ) {
				$this->parent_post_relations[$prod_post_id] = $post->get_post_parent_guid();
			}

			// Store relation between post ID on content stage and ID on production.
			$this->post_relations[$stage_post_id] = $prod_post_id;
		}

		// Import postmeta.
		$this->import_postmeta( $posts );

		// Import relationships between post and taxonomies.
		$this->import_term_relationships( $posts );
	}

	/**
	 * Import postmeta.
	 *
	 * Never call before all posts has been imported! In case we call
	 * import_postmeta() before import_posts() relationships between post IDs
	 * on content stage and production has not been established and import of
	 * postmeta will fail!
	 *
	 * Start by changing content staging post IDs to production IDs.
	 *
	 * The content staging post ID is used as a key in the post relations
	 * array and the production post ID is used as value.
	 *
	 * @param array $posts
	 */
	private function import_postmeta( $posts ) {

		// Get postmeta keys who's records contains relations between posts.
		$meta_keys = apply_filters( 'sme_postmeta_post_relation_keys', array() );

		foreach ( $posts as $post ) {

			foreach ( $post->get_meta() as $item ) {

				if ( in_array( $item['meta_key'], $meta_keys ) ) {

					/*
					 * The meta value must be an integer pointing at the ID of the post
					 * that the post whose postmeta we are currently importing has a
					 * relationship to.
					 */
					if ( isset( $this->post_relations[$item['meta_value']] ) ) {
						$item['meta_value'] = $this->post_relations[$item['meta_value']];
					} else {
						error_log( 'Trying to update dependency between posts. Relationship is defined in postmeta (post_id: ' . $this->post_relations[$item['post_id']] . ', meta_key: ' . $item['meta_key'] . ', meta_value: ' . $item['meta_value'] . ') where post_id is the post ID that has a relationship to the post defined in meta_value. If meta_value does not contain a valid post ID relationship between posts cannot be maintained.' );
					}
				}

				$item['post_id'] = $this->post_relations[$item['post_id']];
				$this->postmeta_dao->insert_postmeta( $item );
			}
		}
	}

	private function import_attachments( $attachments ) {
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
	 * Import terms/taxonomies and relations to posts.
	 *
	 * @param array $data
	 */
	private function import_term_data( $data ) {

		if ( isset( $data['terms'] ) ) {
			$this->import_terms( $data['terms'] );
		}

		if ( isset( $data['term_taxonomies'] ) ) {
			foreach ( $data['term_taxonomies'] as $term_taxonomy ) {
				try {
					$this->import_term_taxonomy( $term_taxonomy );
				} catch( Exception $e ) {
					error_log( $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine() );
				}
			}
		}
	}

	private function import_terms( $terms ) {
		foreach ( $terms as $term ) {
			$exisiting = $this->term_dao->get_term_by_slug( $term->get_slug() );

			// Check if this is a new term.
			if ( empty( $exisiting ) ) {
				$stage_term_id = $term->get_id();
				$prod_term_id  = $this->term_dao->insert_term( $term );
			} else {
				$stage_term_id = $term->get_id();
				$prod_term_id  = $exisiting->get_id();

				$term->set_id( $exisiting->get_id() );
				$this->term_dao->update_term_by_id( $term->get_id(), $term );
			}

			$this->term_relations[$stage_term_id] = $prod_term_id;
		}
	}

	/**
	 * @param Taxonomy $term_taxonomy
	 * @throws Exception
	 */
	private function import_term_taxonomy( Taxonomy $term_taxonomy ) {

		/*
		 * Make sure it is possible to map the content stage term ID to a
		 * production term ID.
		 */
		if ( ! isset( $this->term_relations[$term_taxonomy->get_term_()] ) ) {
			throw new Exception( 'Error: Could not map content stage term ID (' . $term_taxonomy->get_term_() . ') to a production term ID' );
		}

		// Check if term/taxonomy has a parent term.
		if ( $term_taxonomy->get_parent() > 0 ) {

			// Get production term ID.
			$term_taxonomy->set_parent_term( $this->term_relations[$term_taxonomy->get_parent()] );
		}

		// Get production term ID.
		$prod_term_id = $this->term_relations[$term_taxonomy->get_term_()];

		// Change from content stage term ID to production term ID.
		$term_taxonomy->set_term_( $prod_term_id );

		// Get content stage term/taxonomy ID.
		$stage_term_taxonomy_id = $term_taxonomy->get_id();

		// Get production version of this term/taxonomy.
		$exisiting = $this->term_dao->get_term_taxonomy_by_term_id_taxonomy(
			$term_taxonomy->get_term_(),
			$term_taxonomy->get_taxonomy()
		);

		// Check if this is a new term/taxonomy.
		if ( empty( $exisiting ) ) {

			// Create term/taxonomy on production and return the term/taxonomy ID.
			$prod_term_taxonomy_id  = $this->term_dao->insert_term_taxonomy( $term_taxonomy );

		} else {

			// Change from content stage term/taxonomy ID to production term/taxonomy ID.
			$prod_term_taxonomy_id = $exisiting->get_id();

			$term_taxonomy->set_id( $exisiting->get_id() );
			$this->term_dao->update_term_taxonomy_by_id( $term_taxonomy );
		}

		$this->term_taxonomy_relations[$stage_term_taxonomy_id] = $prod_term_taxonomy_id;
	}

	/**
	 * Import relationships between posts and taxonomies.
	 *
	 * Never call this method before terms and posts has been imported!
	 * Calling this method earlier will cause relationships not being stored
	 * or even worse getting stored with wrong data.
	 *
	 * @param array $posts
	 */
	private function import_term_relationships( $posts ) {
		foreach ( $posts as $post ) {
			foreach ( $post->get_taxonomy_relationships() as $term_relationship ) {

				if ( ! isset( $this->term_taxonomy_relations[$term_relationship['term_taxonomy_id']] ) ) {
					/*
					 * @todo Consider throwing an exception. Its hard to recover from this,
					 * batch should be rolled back.
					 */
					error_log( 'Relationship between post and taxonomy could not be maintained on production in ' . __FILE__ . ' on line ' . __LINE__ );
					continue;
				}

				// Get the production version of the taxonomy ID.
				$prod_term_taxonomy_id = $this->term_taxonomy_relations[$term_relationship['term_taxonomy_id']];

				// Get existing relationship between post and taxonomy (if it already exists).
				$exisiting = $this->term_dao->get_term_relationship( $post->get_id(), $prod_term_taxonomy_id );

				// Check if this is a new term-taxonomy.
				if ( empty( $exisiting ) ) {
					$term_relationship['object_id']        = $post->get_id();
					$term_relationship['term_taxonomy_id'] = $prod_term_taxonomy_id;

					$this->term_dao->insert_term_relationship( $term_relationship );
				} else {
					$term_relationship['object_id']        = $exisiting['object_id'];
					$term_relationship['term_taxonomy_id'] = $exisiting['term_taxonomy_id'];

					$this->term_dao->update_term_relationship_by_object_taxonomy( $term_relationship );
				}
			}
		}
	}

	/**
	 * Import data added by a third-party.
	 *
	 * @param array $custom_data Custom data added by third-party.
	 * @param Batch $batch
	 */
	private function import_custom_data( $custom_data, Batch $batch ) {
		do_action( 'sme_custom_data_sent', $custom_data, $batch );
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