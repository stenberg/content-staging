<?php
namespace Me\Stenberg\Content\Staging\Models;

class Message extends Model {

	/**
	 * @var int
	 */
	private $post_id;

	/**
	 * @var string
	 */
	private $message;

	/**
	 * @var int
	 */
	private $code;

	/**
	 * @var string
	 */
	private $level;

	/**
	 * Constructor.
	 *
	 * @param int $id
	 */
	public function __construct( $id = null ) {
		parent::__construct( (int) $id );
	}

	/**
	 * @param int $post_id
	 */
	public function set_post_id( $post_id ) {
		$this->post_id = $post_id;
	}

	/**
	 * @return int
	 */
	public function get_post_id() {
		return $this->post_id;
	}

	/**
	 * @param string $code
	 */
	public function set_code( $code ) {
		$this->code = $code;
	}

	/**
	 * @return string
	 */
	public function get_code() {
		return $this->code;
	}

	/**
	 * @param string $level
	 */
	public function set_level( $level ) {
		$this->level = $level;
	}

	/**
	 * @return string
	 */
	public function get_level() {
		return $this->level;
	}

	/**
	 * @param string $message
	 */
	public function set_message( $message ) {
		$this->message = $message;
	}

	/**
	 * @return string
	 */
	public function get_message() {
		return $this->message;
	}

	/**
	 * @return array
	 */
	public function to_array() {
		return array(
			'id'      => $this->get_id(),
			'message' => $this->get_message(),
			'code'    => $this->get_code(),
			'level'   => $this->get_level(),
		);
	}

}