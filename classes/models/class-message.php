<?php
namespace Me\Stenberg\Content\Staging\Models;

use Exception;

class Message {

	private $level;
	private $message;

	public function __construct( $message, $level ) {
		$this->set_level( $level );
		$this->message = $message;
	}

	public function set_level( $level ) {

		// Supported levels.
		$levels = array( 'success', 'info', 'warning', 'error' );

		if ( ! in_array( $level, $levels ) ) {
			throw new Exception( 'Unsupported message level: ' . $level );
		}

		$this->level = $level;
	}

	public function get_level() {
		return $this->level;
	}

	public function set_message( $message ) {
		$this->message = $message;
	}

	public function get_message() {
		return $this->message;
	}

	/**
	 * Return an array representation of this object.
	 *
	 * @return array
	 */
	public function to_array() {
		return array(
			'level'   => $this->get_level(),
			'message' => $this->get_message(),
		);
	}

}