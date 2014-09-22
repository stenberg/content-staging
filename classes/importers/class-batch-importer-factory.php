<?php
namespace Me\Stenberg\Content\Staging\Importers;

use Me\Stenberg\Content\Staging\DB\Batch_Import_Job_DAO;
use Me\Stenberg\Content\Staging\DB\Post_DAO;
use Me\Stenberg\Content\Staging\DB\Postmeta_DAO;
use Me\Stenberg\Content\Staging\DB\Term_DAO;
use Me\Stenberg\Content\Staging\DB\User_DAO;
use Me\Stenberg\Content\Staging\Models\Batch_Import_Job;

class Batch_Importer_Factory {

	private $job_dao;
	private $post_dao;
	private $postmeta_dao;
	private $term_dao;
	private $user_dao;

	/**
	 * Constructor.
	 *
	 * @param Batch_Import_Job_DAO $job_dao
	 * @param Post_DAO $post_dao
	 * @param Postmeta_DAO $postmeta_dao
	 * @param Term_DAO $term_dao
	 * @param User_DAO $user_dao
	 */
	public function __construct( Batch_Import_Job_DAO $job_dao, Post_DAO $post_dao, Postmeta_DAO $postmeta_dao,
								 Term_DAO $term_dao, User_DAO $user_dao ) {
		$this->job_dao      = $job_dao;
		$this->post_dao     = $post_dao;
		$this->postmeta_dao = $postmeta_dao;
		$this->term_dao     = $term_dao;
		$this->user_dao     = $user_dao;
	}

	/**
	 * Determine what importer to use and return it.
	 */
	public function get_importer( Batch_Import_Job $job, $type = null ) {

		if ( $type == 'ajax' ) {
			return new Batch_AJAX_Importer(
				$job, $this->job_dao, $this->post_dao, $this->postmeta_dao, $this->term_dao, $this->user_dao
			);
		}

		if ( $type == 'background' ) {
			return new Batch_Background_Importer(
				$job, $this->job_dao, $this->post_dao, $this->postmeta_dao, $this->term_dao, $this->user_dao
			);
		}

		return new Batch_AJAX_Importer(
			$job, $this->job_dao, $this->post_dao, $this->postmeta_dao, $this->term_dao, $this->user_dao
		);
	}

	/**
	 * Helper method to trigger a background import.
	 */
	public function run_background_import() {

		// Make sure a background import has been requested.
		if ( ! isset( $_GET['sme_background_import'] ) || ! $_GET['sme_background_import'] ) {
			return;
		}

		// Make sure a job ID has been provided.
		if ( ! isset( $_GET['sme_batch_import_job_id'] ) || ! $_GET['sme_batch_import_job_id'] ) {
			return;
		}

		// Make sure a job key has been provided.
		if ( ! isset( $_GET['sme_import_batch_key'] ) || ! $_GET['sme_import_batch_key'] ) {
			return;
		}

		$job_id     = intval( $_GET['sme_batch_import_job_id'] );
		$import_key = $_GET['sme_import_batch_key'];

		// Get batch importer from database.
		$job = $this->job_dao->get_job_by_id( $job_id );

		// No job found, error.
		if ( ! $job ) {
			error_log( sprintf( 'Batch job with ID %d failed to start.', $job_id ) );
			wp_die( __( 'Something went wrong', 'sme-content-staging' ) );
		}

		// Validate key.
		if ( $import_key !== $job->get_key() ) {
			error_log( 'Unauthorized batch import attempt terminated.' );
			wp_die( __( 'Something went wrong', 'sme-content-staging' ) );
		}

		// Import running.
		$job->generate_key();
		$this->job_dao->update_job( $job );

		$importer = new Batch_Background_Importer(
			$job, $this->job_dao, $this->post_dao, $this->postmeta_dao, $this->term_dao, $this->user_dao
		);

		$importer->import();
	}

}