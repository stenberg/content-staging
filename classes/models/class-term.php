<?php
namespace Me\Stenberg\Content\Staging\Models;

class Term extends Model {

	private $name;
	private $slug;
	private $group;
	private $taxonomy;

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
	 * @param Taxonomy $taxonomy
	 */
	public function set_taxonomy( Taxonomy $taxonomy ) {
		$this->taxonomy = $taxonomy;
	}

	/**
	 * @return Taxonomy
	 */
	public function get_taxonomy() {
		return $this->taxonomy;
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