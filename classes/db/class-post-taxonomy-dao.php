<?php
namespace Me\Stenberg\Content\Staging\DB;

use Me\Stenberg\Content\Staging\Models\Model;
use Me\Stenberg\Content\Staging\Models\Post;
use Me\Stenberg\Content\Staging\Models\Relationships\Post_Taxonomy;
use Me\Stenberg\Content\Staging\Models\Taxonomy;

class Post_Taxonomy_DAO extends DAO {

	private $table;
	private $post_dao;
	private $taxonomy_dao;

	public function __construct( $wpdb, Post_DAO $post_dao, Taxonomy_DAO $taxonomy_dao ) {
		parent::__constuct( $wpdb );
		$this->table        = $wpdb->term_relationships;
		$this->post_dao     = $post_dao;
		$this->taxonomy_dao = $taxonomy_dao;
	}

	/**
	 * Check if a relationship between a post and a taxonomy has been
	 * persisted to database.
	 *
	 * @param Post_Taxonomy $post_taxonomy
	 * @return bool
	 */
	public function has_post_taxonomy_relationship( Post_Taxonomy $post_taxonomy ) {
		$query = $this->wpdb->prepare(
			'SELECT COUNT(*) FROM ' . $this->table . ' WHERE object_id = %d AND term_taxonomy_id = %d',
			$post_taxonomy->get_post()->get_id(),
			$post_taxonomy->get_taxonomy()->get_id()
		);

		if ( $this->wpdb->get_var( $query ) > 0 ) {
			return true;
		}

		return false;
	}

	/**
	 * Populate a Post object with Post_Taxonomy relationships.
	 *
	 * @param Post $post
	 */
	public function get_post_taxonomy_relationships( Post $post ) {
		$query = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->table . ' WHERE object_id = %d',
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
	 * @param Post_Taxonomy $post_taxonomy
	 */
	public function update_post_taxonomy( Post_Taxonomy $post_taxonomy ) {
		$data  = $this->create_array( $post_taxonomy );
		$where = array(
			'object_id'        => $post_taxonomy->get_post()->get_id(),
			'term_taxonomy_id' => $post_taxonomy->get_taxonomy()->get_id(),
		);
		$format       = $this->format();
		$where_format = array( '%d', '%d' );
		$this->update( $data, $where, $format, $where_format );
	}

	/**
	 * @return string
	 */
	protected function get_table() {
		return $this->table;
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
		$this->wpdb->insert( $this->table, $data, $format );
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