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

		if ( ! $type ) {
			$type = $this->get_import_type();
		}

		if ( $type == 'background' ) {
			return new Batch_Background_Importer(
				$job, $this->job_dao, $this->post_dao, $this->postmeta_dao, $this->term_dao, $this->user_dao
			);
		}

		// Default to using the AJAX importer.
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
			$job->add_message( __( 'Something went wrong', 'sme-content-staging' ), 'error' );
			$job->set_status( 2 );
			$this->job_dao->update_job( $job );
			wp_die( __( 'Something went wrong', 'sme-content-staging' ) );
		}

		// Background importer is running. Make the old import key useless.
		$job->generate_key();
		$this->job_dao->update_job( $job );

		$importer = new Batch_Background_Importer(
			$job, $this->job_dao, $this->post_dao, $this->postmeta_dao, $this->term_dao, $this->user_dao
		);

		$importer->import();
	}

	/**
	 * Get importer type.
	 *
	 * @return string
	 */
	private function get_import_type() {

		// Default importer type.
		$type = 'ajax';

		// Use AJAX importer on Windows environments.
		if ( substr( php_uname(), 0, 7 ) == 'Windows' ) {
			return 'ajax';
		}

		// Path to PHP executable.
		$path = $this->get_executable_path();

		// Test if the executable can be used.
		if ( $this->is_executable( $path ) === true ) {
			return 'background';
		}

		return $type;
	}

	/**
	 * Get path to PHP executable.
	 *
	 * @return string
	 */
	private function get_executable_path() {
		$paths = explode( PATH_SEPARATOR, getenv( 'PATH' ) );
		foreach ( $paths as $path ) {
			// XAMPP (Windows).
			if ( strstr( $path, 'php.exe' ) && isset( $_SERVER['WINDIR'] ) && file_exists( $path ) && is_file( $path ) ) {
				return $path;
			} else {
				$executable = $path . DIRECTORY_SEPARATOR . 'php' . ( isset( $_SERVER['WINDIR'] ) ? '.exe' : '');
				if ( file_exists( $executable ) && is_file( $executable ) ) {
					return $executable;
				}
			}
		}

		// Unix.
		if ( $path = shell_exec( 'which php' ) ) {
			return trim( $path );
		}

		// No executable found.
		return '';
	}

	/**
	 * Tells whether the PHP path is executable.
	 *
	 * @param string $path
	 * @return bool
	 */
	private function is_executable( $path ) {
		$is_executable = @is_executable( $path );

		if ( ! $is_executable ) {
			return false;
		}

		// Use PHP executable path and try to execute a simple command.
		$cmd = $path . ' -r \'echo "Success";\'';

		if ( shell_exec( $cmd ) !== 'Success' ) {
			return false;
		}

		return true;
	}

}