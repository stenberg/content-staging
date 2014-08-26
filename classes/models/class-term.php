<?php
namespace Me\Stenberg\Content\Staging\Models;

class Term {

	private $id;
	private $name;
	private $slug;
	private $group;

	public function __construct( $id = null ) {
		$this->set_id( $id );
	}

	/**
	 * @param int $id
	 */
	public function set_id( $id ) {
		$this->id = $id;
	}

	/**
	 * @return int
	 */
	public function get_id() {
		return $this->id;
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
	 * @param string $slug
	 */
	public function set_slug( $slug ) {
		$this->slug = $slug;
	}

	/**
	 * @return string
	 */
	public function get_slug() {
		return $this->slug;
	}

	/**
	 * @param int $group
	 */
	public function set_group( $group ) {
		$this->group = $group;
	}

	/**
	 * @return int
	 */
	public function get_group() {
		return $this->group;
	}

	/**
	 * @return array
	 */
	public function to_array() {
		return array(
			'id'    => $this->get_id(),
			'name'  => $this->get_name(),
			'slug'  => $this->get_slug(),
			'group' => $this->get_group(),
		);
	}

}