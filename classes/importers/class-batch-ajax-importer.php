<?php
namespace Me\Stenberg\Content\Staging\Importers;

use Me\Stenberg\Content\Staging\Models\Batch;

class Batch_AJAX_Importer extends Batch_Importer {

	/**
	 * Constructor.
	 *
	 * @param Batch $batch
	 */
	public function __construct( Batch $batch ) {
		parent::__construct( $batch );
	}

	/**
	 * Trigger importer.
	 */
	public function run() {

		// Import is running.
		$this->api->set_deploy_status( $this->batch->get_id(), 1 );

		// Get first thing to import.
		$first = $this->get_next();

		// Store the first thing to import in database.
		update_post_meta( $this->batch->get_id(), '_sme_import_next', $first );
	}

	/**
	 * Import next item.
	 */
	public function status() {

		// Make sure AJAX import has not already finished.
		if ( $this->api->get_deploy_status( $this->batch->get_id() ) == 3 ) {
			return;
		}

		// Get next thing to import from database.
		$next = get_post_meta( $this->batch->get_id(), '_sme_import_next', true );

		// Import.
		call_user_func( array( $this, $next['method'] ), $next['params'] );

		// Get next thing to import and store it in database.
		$next = $this->get_next( $next );
		update_post_meta( $this->batch->get_id(), '_sme_import_next', $next );

		if ( $this->api->get_deploy_status( $this->batch->get_id() ) == 3 ) {
			/*
			 * Delete batch. Batch is not actually deleted, just set to draft
			 * mode. This is important since we need to access e.g. meta data telling
			 * us the status of the import even after import has finished.
			 */
			$this->batch_dao->delete_batch( $this->batch );
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
			$attachments = $this->batch->get_attachments();
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
			$users = $this->batch->get_users();
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
			$posts = $this->batch->get_posts();
			if ( isset( $posts[$current['index'] + 1] ) ) {
				return array(
					'method' => $current['method'],
					'params' => $posts[$current['index'] + 1],
					'index'  => $current['index'] + 1,
				);
			} else {
				// Method is post, but no posts is left to import.
				return array(
					'method' => 'import_posts_meta',
					'params' => $posts,
					'index'  => -1,
				);
			}
		}

		// Post meta.
		if ( $current['method'] == 'import_posts_meta' ) {
			return array(
				'method' => 'update_parent_post_relations',
				'params' => $this->batch->get_posts(),
				'index'  => -1,
			);
		}

		// Parent post relationship.
		if ( $current['method'] == 'update_parent_post_relations' ) {
			return array(
				'method' => 'import_options',
				'params' => $this->batch->get_options(),
				'index'  => -1,
			);
		}

		// Options.
		if ( $current['method'] == 'import_options' ) {
			return array(
				'method' => 'import_custom_data',
				'params' => array(),
				'index'  => -1,
			);
		}

		// Custom data.
		if ( $current['method'] == 'import_custom_data' ) {
			// Method is custom data, but no custom data is left to import.
			return array(
				'method' => 'publish_posts',
				'params' => array(),
				'index'  => -1,
			);
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