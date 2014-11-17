<?php
namespace Me\Stenberg\Content\Staging\Importers;

use Me\Stenberg\Content\Staging\Models\Batch_Import_Job;

class Batch_AJAX_Importer extends Batch_Importer {

	/**
	 * Constructor.
	 *
	 * @param Batch_Import_Job $job
	 */
	public function __construct( Batch_Import_Job $job ) {
		parent::__construct( $job );
	}

	/**
	 * Trigger importer.
	 */
	public function run() {

		// Import is running.
		$this->job->set_status( 1 );

		// Get first thing to import.
		$first = $this->get_next();

		// Store the first thing to import in database.
		update_post_meta( $this->job->get_id(), 'sme_import_next', $first );

		// Update job.
		$this->import_job_dao->update_job( $this->job );
	}

	/**
	 * Import next item.
	 */
	public function status() {

		// Make sure AJAX import has not already finished.
		if ( $this->job->get_status() > 1 ) {
			return;
		}

		// Get diffs from database.
		$this->post_env_diff = $this->post_dao->get_post_env_diffs( $this->job );

		// Get next thing to import from database.
		$next = get_post_meta( $this->job->get_id(), 'sme_import_next', true );

		// Import.
		call_user_func( array( $this, $next['method'] ), $next['params'] );

		// Store diffs between stage and production post in database.
		$diffs = array();
		foreach ( $this->post_env_diff as $diff ) {
			$diffs[] = $diff->to_array();
		}
		update_post_meta( $this->job->get_id(), 'sme_post_env_diff', $diffs );

		// Get next thing to import and store it in database.
		$next = $this->get_next( $next );
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
	private function get_next( $current = array() ) {

		// Check if this is the first thing we are going to import.
		if ( empty( $current ) ) {
			$current = array(
				'method' => 'import_attachment',
				'params' => array(),
				'index'  => -1,
			);
		}

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

		// Finish and clean up.
		if ( $current['method'] == 'publish_posts' ) {
			return array(
				'method' => 'tear_down',
				'params' => array(),
				'index'  => -1,
			);
		}

		return array();
	}

}