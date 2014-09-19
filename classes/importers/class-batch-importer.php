<?php
namespace Me\Stenberg\Content\Staging\Importers;

use Me\Stenberg\Content\Staging\Models\Batch_Import_Job;

abstract class Batch_Importer {

	/**
	 * @var Batch_Import_Job
	 */
	protected $job;

	/**
	 * @param Batch_Import_Job $job
	 */
	public function set_job( Batch_Import_Job $job) {
		$this->job = $job;
	}

	/**
	 * @return Batch_Import_Job
	 */
	public function get_job() {
		return $this->job;
	}
}