<?php
namespace Me\Stenberg\Content\Staging\Importers;

use Me\Stenberg\Content\Staging\Models\Batch_Import_Job;

abstract class Batch_Importer {

	/**
	 * @var string
	 */
	protected $type;

	/**
	 * @var Batch_Import_Job
	 */
	protected $job;

	/**
	 * Constructor.
	 *
	 * @param string $type
	 * @param Batch_Import_Job $job
	 */
	protected function __construct( $type, Batch_Import_Job $job ) {
		$this->type = $type;
		$this->job  = $job;
	}

	/**
	 * Trigger importer.
	 */
	abstract function run();

	/**
	 * @return string
	 */
	public function get_type() {
		return $this->type;
	}

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