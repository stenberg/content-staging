<?php
namespace Me\Stenberg\Content\Staging\Models;

class Option extends Model {

	/**
	 * @var string
	 */
	private $name;

	/**
	 * @var string
	 */
	private $value;

	/**
	 * @var string Can either be 'yes' or 'no'.
	 */
	private $autoload;

	/**
	 * Constructor.
	 */
	public function __construct( $id = null ) {
		parent::__construct( (int) $id );
	}

	/**
	 * @param string $name
	 */
	public function set_name( $name ) {
		$this->name = $name;
	}

	/**
	 * @return string
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * @param string $value
	 */
	public function set_value( $value ) {
		$this->value = $value;
	}

	/**
	 * @return string
	 */
	public function get_value() {
		return $this->value;
	}

	/**
	 * @param string $autoload
	 */
	public function set_autoload( $autoload ) {
		$this->autoload = $autoload == 'yes' ? $autoload : 'no';
	}

	/**
	 * @return string
	 */
	public function get_autoload() {
		return $this->autoload;
	}

}
