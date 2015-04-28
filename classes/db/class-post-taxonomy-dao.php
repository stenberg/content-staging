<?php
namespace Me\Stenberg\Content\Staging\DB;

use Me\Stenberg\Content\Staging\Helper_Factory;
use Me\Stenberg\Content\Staging\Models\Model;
use Me\Stenberg\Content\Staging\Models\Post;
use Me\Stenberg\Content\Staging\Models\Relationships\Post_Taxonomy;
use Me\Stenberg\Content\Staging\Models\Taxonomy;

class Post_Taxonomy_DAO extends DAO {

	private $post_dao;
	private $taxonomy_dao;

	public function __construct( $wpdb ) {
		parent::__construct( $wpdb );
		$this->post_dao     = Helper_Factory::get_instance()->get_dao( 'Post' );
		$this->taxonomy_dao = Helper_Factory::get_instance()->get_dao( 'Taxonomy' );
	}

	/**
	 * Populate a Post object with Post_Taxonomy relationships.
	 *
	 * @param Post $post
	 */
	public function get_post_taxonomy_relationships( Post $post ) {
		$query = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->get_table() . ' WHERE object_id = %d',
			$post->get_id()
		);

		foreach ( $this->wpdb->get_results( $query, ARRAY_A ) as $relationship ) {
			$taxonomy = $this->taxonomy_dao->find( $relationship['term_taxonomy_id'] );

			if ( $taxonomy instanceof Taxonomy ) {
				$post->add_post_taxonomy( new Post_Taxonomy( $post, $taxonomy ) );
			}
		}
	}

	/**
	 * Clear the Post_Taxonomy relationships informations.
	 *
	 * @param Post_Taxonomy $post_taxonomy
	 */
	public function clear_post_taxonomy_relationships( Post_Taxonomy $post_taxonomy ) {
		$this->wpdb->delete( $this->wpdb->term_relationships, array( 'object_id' => $post_taxonomy->get_post()->get_id() ) );
	}

	/**
	 * @return string
	 */
	protected function get_table() {
		return $this->wpdb->term_relationships;
	}

	/**
	 * @return string
	 */
	protected function target_class() {
		return '\Me\Stenberg\Content\Staging\Models\Post_Taxonomy';
	}

	/**
	 * @param array $raw
	 * @return string
	 */
	protected function unique_key( array $raw ) {
		return $raw['object_id'] . '-' . $raw['term_taxonomy_id'];
	}

	/**
	 * @return string
	 */
	protected function select_stmt() {
		return '';
	}

	/**
	 * @param array $ids
	 * @return string
	 */
	protected function select_by_ids_stmt( array $ids ) {
		return '';
	}

	/**
	 * @param Model $obj
	 */
	protected function do_insert( Model $obj ) {
		$data   = $this->create_array( $obj );
		$format = $this->format();
		$this->wpdb->insert( $this->get_table(), $data, $format );
	}

	/**
	 * @param array $raw
	 * @return Post_Taxonomy
	 */
	protected function do_create_object( array $raw ) {
		$post     = $this->post_dao->find( $raw['object_id'] );
		$taxonomy = $this->taxonomy_dao->find( $raw['term_taxonomy_id'] );
		$obj      = new Post_Taxonomy( $post, $taxonomy );
		$obj->set_post( $post );
		$obj->set_taxonomy( $taxonomy );
		$obj->set_term_order( $raw['term_order'] );
		return $obj;
	}

	/**
	 * @param Model $obj
	 * @return array
	 */
	protected function do_create_array( Model $obj ) {
		return array(
			'object_id'        => $obj->get_post()->get_id(),
			'term_taxonomy_id' => $obj->get_taxonomy()->get_id(),
			'term_order'       => $obj->get_term_order(),
		);
	}

	/**
	 * Format of each of the values in the result set.
	 *
	 * Important! Must mimic the array returned by the
	 * 'do_create_array' method.
	 *
	 * @return array
	 */
	protected function format() {
		return array(
			'%d', // object_id
			'%d', // term_taxonomy_id
			'%d', // term_order
		);
	}

}