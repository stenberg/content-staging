<?php
namespace Me\Stenberg\Content\Staging\Models;

class Postmeta extends Model {

	private $post;
	private $key;
	private $value;

	/**
	 * Constructor.
	 */
	public function __construct( $id = null ) {
		parent::__construct( (int) $id );
	}

	/**
	 * @param Post $post
	 */
	public function set_post( Post $post ) {
		$this->post = $post;
	}

	/**
	 * @return Post
	 */
	public function get_post() {
		return $this->post;
	}

	/**
	 * @param string $key
	 */
	public function set_key( $key ) {
		$this->key = $key;
	}

	/**
	 * @return string
	 */
	public function get_key() {
		return $this->key;
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

}
