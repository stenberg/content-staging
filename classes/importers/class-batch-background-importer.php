<?php
namespace Me\Stenberg\Content\Staging\Importers;

use Me\Stenberg\Content\Staging\Background_Process;
use Me\Stenberg\Content\Staging\DB\Batch_Import_Job_DAO;
use Me\Stenberg\Content\Staging\DB\Post_DAO;
use Me\Stenberg\Content\Staging\DB\Postmeta_DAO;
use Me\Stenberg\Content\Staging\DB\Term_DAO;
use Me\Stenberg\Content\Staging\DB\User_DAO;
use Me\Stenberg\Content\Staging\Models\Batch_Import_Job;

class Batch_Background_Importer extends Batch_Importer {

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
		parent::__construct( 'background', $job, $job_dao, $post_dao, $postmeta_dao, $term_dao, $user_dao );
	}

	/**
	 * Start importer background process on production environment.
	 */
	public function run() {

		// Make sure background import for this job is not already running.
		if ( $this->job->get_status() > 0 ) {
			return;
		}

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
			'php ' . $import_script . ' ' . ABSPATH . ' ' . get_site_url() . ' ' . $this->job->get_id() . ' ' . $site_path . ' ' . $this->job->get_key()
		);

		if ( file_exists( $import_script ) ) {
			$background_process->run();
		}

		if ( $background_process->get_pid() ) {
			// Background import started.
			$this->job->set_status( 1 );
		} else {
			// Failed to start background import.
			$this->job->add_message( 'Batch import failed to start.', 'info' );
			$this->job->set_status( 2 );
		}

		$this->import_job_dao->update_job( $this->job );
	}

	/**
	 * Import all data in a batch on production environment.
	 */
	public function import() {

		// Get the batch.
		$batch = $this->job->get_batch();

		// Get postmeta keys who's records contains relations between posts.
		$this->postmeta_keys = apply_filters( 'sme_post_relationship_keys', array() );

		// Import attachments.
		$this->import_attachments();

		// Create/update users.
		$this->import_users( $batch->get_users() );

		// Create/update posts.
		foreach ( $batch->get_posts() as $post ) {
			$this->import_post( $post );
		}

		// Import postmeta.
		foreach ( $batch->get_posts() as $post ) {
			$this->import_postmeta( $post );
		}

		// Update relationship between posts and their parents.
		$this->update_parent_post_relations();

		// Import custom data.
		$this->import_custom_data( $this->job );

		// Publish posts.
		$this->publish_posts();

		// Import finished, set success message and update import status.
		$this->job->add_message( 'Batch has been successfully imported!', 'success' );
		$this->job->set_status( 3 );
		$this->import_job_dao->update_job( $this->job );

		/*
		 * Delete importer. Importer is not actually deleted, just set to draft
		 * mode. This is important since we need to access e.g. meta data telling
		 * us the status of the import even when import has finished.
		 */
		$this->import_job_dao->delete_job( $this->job );
	}

}