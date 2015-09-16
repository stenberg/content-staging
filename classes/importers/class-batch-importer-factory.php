<?php
namespace Me\Stenberg\Content\Staging\Importers;

use Me\Stenberg\Content\Staging\Apis\Common_API;
use Me\Stenberg\Content\Staging\DB\Batch_DAO;
use Me\Stenberg\Content\Staging\Factories\DAO_Factory;
use Me\Stenberg\Content\Staging\Helper_Factory;
use Me\Stenberg\Content\Staging\Models\Batch;

class Batch_Importer_Factory {

	/**
	 * @var Common_API
	 */
	private $api;

	/**
	 * @var DAO_Factory
	 */
	private $dao_factory;

	/**
	 * Constructor.
	 */
	public function __construct( Common_API $api, DAO_Factory $dao_factory ) {
		$this->api         = $api;
		$this->dao_factory = $dao_factory;
	}

	/**
	 * Determine what importer to use and return it.
	 *
	 * @param Batch $batch
	 *
	 * @return Batch_Importer
	 */
	public function get_importer( Batch $batch ) {

		// What importer class to use.
		$class = $this->get_importer_class();

		// Initialize and return the importer.
		return new $class( $batch );
	}

	/**
	 * Helper method to trigger a background import.
	 */
	public function run_background_import() {

		// Make sure a background import has been requested.
		if ( ! isset( $_GET['sme_background_import'] ) || ! $_GET['sme_background_import'] ) {
			return;
		}

		// Make sure a batch ID has been provided.
		if ( ! isset( $_GET['sme_batch_id'] ) || ! $_GET['sme_batch_id'] ) {
			return;
		}

		// Make sure a background import key has been provided.
		if ( ! isset( $_GET['sme_import_key'] ) || ! $_GET['sme_import_key'] ) {
			return;
		}

		$batch_id   = intval( $_GET['sme_batch_id'] );
		$import_key = $_GET['sme_import_key'];
		$batch_dao  = $this->dao_factory->create( 'Batch' );

		// Get batch from database.
		$batch = $batch_dao->find( $batch_id );

		// No batch to import found, error.
		if ( ! $batch ) {
			error_log( sprintf( 'Batch with ID %d could not be imported.', $batch_id ) );
			wp_die( __( 'Something went wrong', 'sme-content-staging' ) );
		}

		// Validate key.
		if ( $import_key !== $this->api->get_import_key( $batch->get_id() ) ) {

			error_log( 'Unauthorized batch import attempt terminated.' );

			$this->api->add_deploy_message( $batch->get_id(), __( 'Something went wrong', 'sme-content-staging' ), 'error' );
			$this->api->set_deploy_status( $batch->get_id(), 2 );

			wp_die( __( 'Something went wrong', 'sme-content-staging' ) );
		}

		// Background import is running. Make the old import key useless.
		$this->api->generate_import_key( $batch );

		// Create the importer.
		$importer = new Batch_Background_Importer( $batch );

		// Trigger import.
		$importer->import();
	}

	/**
	 * Get importer class.
	 *
	 * @return string
	 */
	private function get_importer_class() {

		/*
		 * Make it possible for third-party developer to decide what importer
		 * to use or to set their own importer.
		 */
		$class = apply_filters( 'sme_importer', null );

		if ( $class !== null ) {
			return $class;
		}

		// Set default importer class.
		$class = 'Me\Stenberg\Content\Staging\Importers\Batch_AJAX_Importer';

		// Use AJAX importer on Windows environments.
		if ( substr( php_uname(), 0, 7 ) == 'Windows' ) {
			return $class;
		}

		// Path to PHP executable.
		$path = $this->get_executable_path();

		// Test if the executable can be used.
		if ( $this->is_executable( $path ) === true ) {
			return 'Me\Stenberg\Content\Staging\Importers\Batch_Background_Importer';
		}

		// Use default importer class.
		return $class;
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