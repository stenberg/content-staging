<?php
namespace Me\Stenberg\Content\Staging\Models\Relationships;

use Me\Stenberg\Content\Staging\Models\Model;
use Me\Stenberg\Content\Staging\Models\Post;
use Me\Stenberg\Content\Staging\Models\Taxonomy;

class Post_Taxonomy extends Model {

	private $post;
	private $taxonomy;
	private $term_order;

	/**
	 * @param Post $post
	 * @param Taxonomy $taxonomy
	 */
	public function __construct( Post $post, Taxonomy $taxonomy ) {
		parent::__construct();
		$this->set_id( $post->get_id() . '-' . $taxonomy->get_id() );
		$this->post     = $post;
		$this->taxonomy = $taxonomy;
	}

	/**
	 * @param Post $post
	 */
	public function set_post( Post $post ) {
		$this->set_id( $post->get_id() . '-' . $this->taxonomy->get_id() );
		$this->post = $post;
	}

	/**
	 * @return Post
	 */
	public function get_post() {
		return $this->post;
	}

	/**
	 * @param Taxonomy $taxonomy
	 */
	public function set_taxonomy( Taxonomy $taxonomy ) {
		$this->set_id( $this->post->get_id() . '-' . $taxonomy->get_id() );
		$this->taxonomy = $taxonomy;
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
     return ( $this->term_order !== null ) ? $this->term_order : 0;
   }

}
