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
		parent::__construct( 'sme-ajax-import', $job );
	}

	/**
	 * Trigger importer.
	 */
	public function run() {

		// Make sure AJAX import has not already finished.
		if ( $this->job->get_status() > 1 ) {
			return;
		}

		// Get next post to import.

		// Import post.
	}

}