<?php
namespace Me\Stenberg\Content\Staging\Models;

use Exception;

class Batch_Importer {

	/**
	 * ID of this importer.
	 *
	 * @var int
	 */
	private $id;

	/**
	 * ID of user who created this importer.
	 * @var
	 */
	private $creator_id;

	/**
	 * Date when this importer was created. Timezone according to settings
	 * of the user who created the importer.
	 *
	 * @var string
	 */
	private $date;

	/**
	 * Date when this importer was created in Greenwich Mean Time (GMT).
	 *
	 * @var string
	 */
	private $date_gmt;

	/**
	 * Date when this importer was last modified. Timezone according to
	 * settings of the user who modified the importer.
	 *
	 * @var string
	 */
	private $modified;

	/**
	 * Date when this importer was last modified in
	 * Greenwich Mean Time (GMT).
	 *
	 * @var string
	 */
	private $modified_gmt;

	/**
	 * Batch this importer is managing.
	 *
	 * @var Batch $batch
	 */
	private $batch;

	/**
	 * Constructor.
	 *
	 * @param int $id
	 */
	public function __construct( $id = null ) {
		$this->id = $id;
	}

	/**
	 * @param int $id
	 */
	public function set_id( $id ) {
		$this->id = (int) $id;
	}

	/**
	 * @return int
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * @param int $creator_id
	 */
	public function set_creator_id( $creator_id ) {
		$this->creator_id = (int) $creator_id;
	}

	/**
	 * @return int
	 */
	public function get_creator_id() {
		return $this->creator_id;
	}

	/**
	 * @param string $date
	 */
	public function set_date( $date ) {
		$this->date = $date;
	}

	/**
	 * @return string
	 */
	public function get_date() {
		return $this->date;
	}

	/**
	 * @param string $date_gmt
	 */
	public function set_date_gmt( $date_gmt ) {
		$this->date_gmt = $date_gmt;
	}

	/**
	 * @return string
	 */
	public function get_date_gmt() {
		return $this->date_gmt;
	}

	/**
	 * @param string $modified
	 */
	public function set_modified( $modified ) {
		$this->modified = $modified;
	}

	/**
	 * @return string
	 */
	public function get_modified() {
		return $this->modified;
	}

	/**
	 * @param string $modified_gmt
	 */
	public function set_modified_gmt( $modified_gmt ) {
		$this->modified_gmt = $modified_gmt;
	}

	/**
	 * @return string
	 */
	public function get_modified_gmt() {
		return $this->modified_gmt;
	}

	/**
	 * @param Batch $batch
	 */
	public function set_batch( Batch $batch ) {
		$this->batch = $batch;
	}

	/**
	 * @return Batch
	 */
	public function get_batch() {
		return $this->batch;
	}

}