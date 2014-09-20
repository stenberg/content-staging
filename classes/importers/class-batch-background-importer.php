<?php
namespace Me\Stenberg\Content\Staging\Importers;

use Me\Stenberg\Content\Staging\Background_Process;
use Me\Stenberg\Content\Staging\Models\Batch_Import_Job;

class Batch_Background_Importer extends Batch_Importer {

	/**
	 * Constructor.
	 *
	 * @param Batch_Import_Job $job
	 */
	public function __construct( Batch_Import_Job $job ) {
		parent::__construct( 'sme-background-import', $job );
	}

	/**
	 * Start importer.
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

		// @todo store background process ID: $background_process->get_pid();
	}
}