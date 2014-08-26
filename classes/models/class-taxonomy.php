<?php
namespace Me\Stenberg\Content\Staging\Models;

class Taxonomy {

	private $id;
	private $term_id;
	private $taxonomy;
	private $description;
	private $parent;
	private $count;

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
	 * @param int $term_id
	 */
	public function set_term_id( $term_id ) {
		$this->term_id = $term_id;
	}

	/**
	 * @return int
	 */
	public function get_term_id() {
		return $this->term_id;
	}

	/**
	 * @param string $taxonomy
	 */
	public function set_taxonomy( $taxonomy ) {
		$this->taxonomy = $taxonomy;
	}

	/**
	 * @return string
	 */
	public function get_taxonomy() {
		return $this->taxonomy;
	}

	/**
	 * @param string $description
	 */
	public function set_description( $description ) {
		$this->description = $description;
	}

	/**
	 * @return string
	 */
	public function get_description() {
		return $this->description;
	}

	/**
	 * @param int $parent
	 */
	public function set_parent( $parent ) {
		$this->parent = $parent;
	}

	/**
	 * @return int
	 */
	public function get_parent() {
		return $this->parent;
	}

	/**
	 * @param int $count
	 */
	public function set_count( $count ) {
		$this->count = $count;
	}

	/**
	 * @return int
	 */
	public function get_count() {
		return $this->count;
	}

	/**
	 * @return array
	 */
	public function to_array() {
		return array(
			'id'          => $this->get_id(),
			'term_id'     => $this->get_term_id(),
			'taxonomy'    => $this->get_taxonomy(),
			'description' => $this->get_description(),
			'parent'      => $this->get_parent(),
			'count'       => $this->get_count(),
		);
	}

}