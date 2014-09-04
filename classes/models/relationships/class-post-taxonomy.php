<?php
namespace Me\Stenberg\Content\Staging\Models\Relationships;

use Me\Stenberg\Content\Staging\Models\Post;
use Me\Stenberg\Content\Staging\Models\Taxonomy;

class Post_Taxonomy {

	private $post;
	private $taxonomy;
	private $term_order;

	/**
	 * Constructor.
	 *
	 * Important that a Post and Taxonomy is set when constructing object!
	 * Otherwise e.g. the 'set_taxonomy' method would fail in setting the
	 * Taxonomy on the Post object.
	 *
	 * @param Post $post
	 * @param Taxonomy $taxonomy
	 */
	public function __construct( Post $post, Taxonomy $taxonomy ) {
		$this->post       = $post;
		$this->taxonomy   = $taxonomy;
		$this->term_order = 0;
	}

	/**
	 * @return Post
	 */
	public function get_post() {
		return $this->post;
	}

	/**
	 * @return Taxonomy
	 */
	public function get_taxonomy() {
		return $this->taxonomy;
	}

	/**
	 * @param int $term_order
	 */
	public function set_term_order( $term_order ) {
		$this->term_order = (int) $term_order;
	}

	/**
	 * @return int
	 */
	public function get_term_order() {
		return $this->term_order;
	}

}