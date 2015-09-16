<?php
namespace Me\Stenberg\Content\Staging\Importers;

use Me\Stenberg\Content\Staging\Background_Process;
use Me\Stenberg\Content\Staging\Models\Batch;

class Batch_Background_Importer extends Batch_Importer {

	/**
	 * Constructor.
	 *
	 * @param Batch $batch
	 */
	public function __construct( Batch $batch ) {
		parent::__construct( $batch );
	}

	/**
	 * Start importer background process on production environment.
	 */
	public function run() {

		// Get current deploy status (if any).
		$deploy_status = $this->api->get_deploy_status( $this->batch->get_id() );

		// Make sure background import for this job is not already running.
		if ( $deploy_status > 0 ) {
			return;
		}

		// Inicate that background import is about to start.
		$this->api->set_deploy_status( $this->batch->get_id(), 1 );

		// Default site path.
		$site_path = '/';

		// Site path in multi-site setup.
		if ( is_multisite() ) {
			$site      = get_blog_details();
			$site_path = $site->path;
		}

		// Trigger import script.
		$import_script      = dirname( dirname( dirname( __FILE__ ) ) ) . '/scripts/import-batch.php';
		$background_process = new Background_Process(
			'php ' . $import_script . ' ' . ABSPATH . ' ' . get_site_url() . ' ' . $this->batch->get_id() . ' ' . $site_path . ' ' . $this->api->generate_import_key( $this->batch )
		);

		if ( file_exists( $import_script ) ) {
			$background_process->run();
		}

		if ( ! $background_process->get_pid() ) {

			// Failed to start background import.
			$this->api->add_deploy_message( $this->batch->get_id(), 'Batch import failed to start.', 'info' );
			$this->api->set_deploy_status( $this->batch->get_id(), 2 );
		}
	}

	/**
	 * Retrieve import status.
	 */
	public function status() {
		// Nothing here atm.
	}

	/**
	 * Import all data in a batch on production environment.
	 */
	public function import() {

		// Import attachments.
		$this->import_attachments();

		// Create/update users.
		$this->import_users( $this->batch->get_users() );

		// Create/update posts.
		foreach ( $this->batch->get_posts() as $post ) {
			$this->import_post( $post );
		}

		// Import postmeta.
		foreach ( $this->batch->get_posts() as $post ) {
			$this->import_post_meta( $post );
		}

		// Update relationship between posts and their parents.
		$this->update_parent_post_relations( $this->batch->get_posts() );

		// Import options.
		$this->import_options( $this->batch->get_options() );

		// Import custom data.
		$this->import_custom_data();

		// Publish posts.
		$this->publish_posts();

		// Perform clean-up operations.
		$this->tear_down();

		/*
		 * Delete batch. Batch is not actually deleted, just set to draft
		 * mode. This is important since we need to access e.g. meta data telling
		 * us the status of the import even after import has finished.
		 */
		$this->batch_dao->delete_batch( $this->batch );
	}

}