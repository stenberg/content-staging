<?php
namespace Me\Stenberg\Content\Staging\Importers;

use Me\Stenberg\Content\Staging\DB\Batch_Import_Job_DAO;
use Me\Stenberg\Content\Staging\DB\Post_DAO;
use Me\Stenberg\Content\Staging\DB\Postmeta_DAO;
use Me\Stenberg\Content\Staging\DB\Term_DAO;
use Me\Stenberg\Content\Staging\DB\User_DAO;
use Me\Stenberg\Content\Staging\Models\Batch;
use Me\Stenberg\Content\Staging\Models\Batch_Import_Job;

class Batch_AJAX_Importer extends Batch_Importer {

	/**
	 * Constructor.
	 *
	 * @param Batch_Import_Job $job
	 * @param Batch_Import_Job_DAO $job_dao
	 * @param Post_DAO $post_dao
	 * @param Postmeta_DAO $postmeta_dao
	 * @param Term_DAO $term_dao
	 * @param User_DAO $user_dao
	 */
	public function __construct( Batch_Import_Job $job, Batch_Import_Job_DAO $job_dao, Post_DAO $post_dao,
								 Postmeta_DAO $postmeta_dao, Term_DAO $term_dao, User_DAO $user_dao ) {
		parent::__construct( 'ajax', $job, $job_dao, $post_dao, $postmeta_dao, $term_dao, $user_dao );
	}

	/**
	 * Trigger importer.
	 */
	public function run() {

		// Make sure AJAX import has not already finished.
		if ( $this->job->get_status() > 1 ) {
			return;
		}

		$this->job->set_status( 1 );
		$batch = $this->job->get_batch();
		$this->postmeta_keys = apply_filters( 'sme_post_relationship_keys', array() );
		$next = array(
			'method' => 'import_attachment',
			'params' => array(),
			'index'  => -1,
		);

		if ( $val = get_post_meta( $this->job->get_id(), 'sme_parent_post_relations', true ) ) {
			$this->parent_post_relations = $val;
		}

		if ( $val = get_post_meta( $this->job->get_id(), 'sme_user_relations', true ) ) {
			$this->user_relations = $val;
		}

		if ( $val = get_post_meta( $this->job->get_id(), 'sme_post_relations', true ) ) {
			$this->post_relations = $val;
		}

		if ( $val = get_post_meta( $this->job->get_id(), 'sme_posts_to_publish', true ) ) {
			$this->posts_to_publish = $val;
		}

		if ( $val = get_post_meta( $this->job->get_id(), 'sme_import_next', true ) ) {
			$next = $val;
		} else {
			// This is the first thing we are going to import.
			$next = $this->get_next( $next );
		}

		// Import.
		call_user_func( array( $this, $next['method'] ), $next['params'] );

		// Get next thing to import.
		$next = $this->get_next( $next );

		update_post_meta( $this->job->get_id(), 'sme_parent_post_relations', $this->parent_post_relations );
		update_post_meta( $this->job->get_id(), 'sme_user_relations', $this->user_relations );
		update_post_meta( $this->job->get_id(), 'sme_post_relations', $this->post_relations );
		update_post_meta( $this->job->get_id(), 'sme_posts_to_publish', $this->posts_to_publish );
		update_post_meta( $this->job->get_id(), 'sme_import_next', $next );

		if ( empty( $next ) ) {
			$this->job->add_message( 'Batch has been successfully imported!', 'success' );
			$this->job->set_status( 3 );
		}

		$this->import_job_dao->update_job( $this->job );

		if ( $this->job->get_status() > 1 ) {
			/*
			 * Delete importer. Importer is not actually deleted, just set to draft
			 * mode. This is important since we need to access e.g. meta data telling
			 * us the status of the import even when import has finished.
			 */
			$this->import_job_dao->delete_job( $this->job );
		}
	}

	/**
	 * Get action to perform during next AJAX request.
	 *
	 * @param array $current
	 * @return array
	 */
	private function get_next( array $current ) {

		// Attachments.
		if ( $current['method'] == 'import_attachment' ) {
			$attachments = $this->job->get_batch()->get_attachments();
			if ( isset( $attachments[$current['index'] + 1] ) ) {
				return array(
					'method' => $current['method'],
					'params' => $attachments[$current['index'] + 1],
					'index'  => $current['index'] + 1,
				);
			} else {
				// Method is attachment, but no attachment is left to import.
				$current['method'] = 'import_user';
				$current['params'] = array();
				$current['index']  = -1;
			}
		}

		// Users.
		if ( $current['method'] == 'import_user' ) {
			$users = $this->job->get_batch()->get_users();
			if ( isset( $users[$current['index'] + 1] ) ) {
				return array(
					'method' => $current['method'],
					'params' => $users[$current['index'] + 1],
					'index'  => $current['index'] + 1,
				);
			} else {
				// Method is attachment, but no attachment is left to import.
				$current['method'] = 'import_post';
				$current['params'] = array();
				$current['index']  = -1;
			}
		}

		// Posts.
		if ( $current['method'] == 'import_post' ) {
			$posts = $this->job->get_batch()->get_posts();
			if ( isset( $posts[$current['index'] + 1] ) ) {
				return array(
					'method' => $current['method'],
					'params' => $posts[$current['index'] + 1],
					'index'  => $current['index'] + 1,
				);
			} else {
				// Method is post, but no posts is left to import.
				return array(
					'method' => 'import_all_postmeta',
					'params' => $posts,
					'index'  => -1,
				);
			}
		}

		// Post meta.
		if ( $current['method'] == 'import_all_postmeta' ) {
			$current['method'] = 'import_custom_data';
			$current['params'] = array();
			$current['index']  = -1;
		}

		// Parent post relationship.
		if ( $current['method'] == 'update_parent_post_relations' ) {
			$current['method'] = 'import_custom_data';
			$current['params'] = array();
			$current['index']  = -1;
		}

		// Custom data.
		if ( $current['method'] == 'import_custom_data' ) {
			$custom = $this->job->get_batch()->get_custom_data();
			if ( isset( $custom[$current['index'] + 1] ) ) {
				return array(
					'method' => $current['method'],
					'params' => $custom[$current['index'] + 1],
					'index'  => $current['index'] + 1,
				);
			} else {
				// Method is custom data, but no custom data is left to import.
				return array(
					'method' => 'publish_posts',
					'params' => array(),
					'index'  => -1,
				);
			}
		}

		return array();
	}

}