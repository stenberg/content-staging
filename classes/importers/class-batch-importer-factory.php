<?php
namespace Me\Stenberg\Content\Staging\Importers;

class Batch_Importer_Factory {

	public function __construct() {
		// Nothing here atm.
	}

	/**
	 * Determine what importer to use and return it.
	 */
	public function get_importer() {
		return new Batch_Background_Importer();
	}
}