<?php
namespace Me\Stenberg\Content\Staging\Models;

class Taxonomy {

	private $id;
	private $term;
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
	 * @param Term $term
	 */
	public function set_term( Term $term ) {
		$this->term = $term;
	}

	/**
	 * @return Term
	 */
	public function get_term() {
		return $this->term;
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
	 * @param Taxonomy $taxonomy
	 */
	public function set_parent( Taxonomy $taxonomy ) {
		$this->parent = $taxonomy;
	}

	/**
	 * @return Taxonomy
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

}