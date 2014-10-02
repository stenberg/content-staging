<?php
namespace Me\Stenberg\Content\Staging\Models;

use Exception;

class Batch_Import_Job extends Model {

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
	 * Status of the import, can be any of:
	 * 0 (not started)
	 * 1 (importing)
	 * 2 (failed)
	 * 3 (completed)
	 *
	 * @var int
	 */
	private $status;

	/**
	 * Messages generated during batch import.
	 *
	 * @var
	 */
	private $messages;

	/**
	 * Import key.
	 *
	 * @var string
	 */
	private $key;

	/**
	 * Constructor.
	 *
	 * @param int $id
	 */
	public function __construct( $id = null ) {
		parent::__construct( (int) $id );
		$this->status   = 0;
		$this->messages = array();
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

	/**
	 * Set status of the import, can be any of:
	 * 0 (not started)
	 * 1 (running)
	 * 2 (failed)
	 * 3 (completed)
	 *
	 * @param int $status
	 */
	public function set_status( $status = 0 ) {
		$this->status = (int) $status;
	}

	/**
	 * Get status of import.
	 *
	 * @return int
	 */
	public function get_status() {
		return $this->status;
	}

	/**
	 * Set messages.
	 *
	 * @param array
	 */
	public function set_messages( array $messages ) {
		$this->messages = $messages;
	}

	/**
	 * Add a message.
	 *
	 * @param string $message
	 * @param string $level
	 * @throws Exception
	 */
	public function add_message( $message, $level = 'info' ) {

		// Supported levels.
		$levels = array( 'success', 'info', 'warning', 'error' );

		if ( ! in_array( $level, $levels ) ) {
			throw new Exception( 'Unsupported message level: ' . $level );
		}

		$this->messages[] = array(
			'message' => $message,
			'level'   => $level,
		);
	}

	/**
	 * Return all messages.
	 *
	 * @return array
	 */
	public function get_messages() {
		return $this->messages;
	}

	/**
	 * Set key.
	 *
	 * @param string $key
	 */
	public function set_key( $key ) {
		$this->key = $key;
	}

	/**
	 * Get key.
	 *
	 * @return string
	 */
	public function get_key() {
		return $this->key;
	}

	/**
	 * Generate a key.
	 *
	 * @throws Exception
	 */
	public function generate_key() {
		if ( ! $this->get_id() || ! $this->modified_gmt ) {
			throw new Exception( 'Failed generating batch importer key.' );
		}
		$this->key = md5( $this->get_id() . '-' . $this->modified_gmt . '-' . rand( 0, 100000 ) );
	}

}