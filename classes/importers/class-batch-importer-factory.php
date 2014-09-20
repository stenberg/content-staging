<?php
namespace Me\Stenberg\Content\Staging\Importers;

use Me\Stenberg\Content\Staging\Models\Batch_Import_Job;

class Batch_Importer_Factory {

	public function __construct() {
		// Nothing here atm.
	}

	/**
	 * Determine what importer to use and return it.
	 */
	public function get_importer( Batch_Import_Job $job ) {
		return new Batch_Background_Importer( $job );
	}
}