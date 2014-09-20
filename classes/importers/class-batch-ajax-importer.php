<?php
namespace Me\Stenberg\Content\Staging\Importers;

class Batch_AJAX_Importer extends Batch_Importer {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->type = 'sme-ajax-import';
	}

}