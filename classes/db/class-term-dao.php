<?php
namespace Me\Stenberg\Content\Staging\DB;

use Me\Stenberg\Content\Staging\Models\Model;
use Me\Stenberg\Content\Staging\Models\Term;

class Term_DAO extends DAO {

	private $table;

	public function __construct( $wpdb ) {
		parent::__constuct( $wpdb );
		$this->table = $wpdb->terms;
	}

	/**
	 * Get term by slug.
	 *
	 * @param string $slug
	 * @return Term
	 */
	public function get_term_by_slug( $slug ) {
		$query = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->table . ' WHERE slug = %s',
			$slug
		);

		$result = $this->wpdb->get_row( $query, ARRAY_A );

		if ( isset( $result['term_id'] ) ) {
			return $this->create_object( $result );
		}

		return null;
	}

	/**
	 * Find term with the same slug as the provided term. If a match is
	 * found, update provided term with the term ID we got from database.
	 *
	 * Useful for comparing a term sent from content staging to production.
	 *
	 * @param Term $term
	 */
	public function get_term_id_by_slug( Term $term ) {
		$term_id = null;
		$query   = $this->wpdb->prepare(
			'SELECT term_id FROM ' . $this->table . ' WHERE slug = %s',
			$term->get_slug()
		);

		$result = $this->wpdb->get_row( $query, ARRAY_A );

		if ( isset( $result['term_id'] ) ) {
			$term_id = $result['term_id'];
		}

		$term->set_id( $term_id );
	}

	/**
	 * @param Term $term
	 */
	public function update_term( Term $term ) {
		$data         = $this->create_array( $term );
		$where        = array( 'term_id' => $term->get_id() );
		$format       = $this->format();
		$where_format = array( '%d' );
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
		return '\Me\Stenberg\Content\Staging\Models\Term';
	}

	/**
	 * @param array $raw
	 * @return string
	 */
	protected function unique_key( array $raw ) {
		return $raw['term_id'];
	}

	/**
	 * @return string
	 */
	protected function select_stmt() {
		return 'SELECT * FROM ' . $this->table . ' WHERE term_id = %d';
	}

	/**
	 * @param array $ids
	 * @return string
	 */
	protected function select_by_ids_stmt( array $ids ) {
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		return 'SELECT * FROM ' . $this->table . ' WHERE term_id in (' . $placeholders . ')';
	}

	/**
	 * @param Model $obj
	 */
	protected function do_insert( Model $obj ) {
		$data   = $this->create_array( $obj );
		$format = $this->format();
		$this->wpdb->insert( $this->table, $data, $format );
		$obj->set_id( $this->wpdb->insert_id );
	}

	/**
	 * @param array $raw
	 * @return Term
	 */
	protected function do_create_object( array $raw ) {
		$obj = new Term( $raw['term_id'] );
		$obj->set_name( $raw['name'] );
		$obj->set_slug( $raw['slug'] );
		$obj->set_group( $raw['term_group'] );
		return $obj;
	}

	/**
	 * @param Model $obj
	 * @return array
	 */
	protected function do_create_array( Model $obj ) {
		return array(
			'name'       => $obj->get_name(),
			'slug'       => $obj->get_slug(),
			'term_group' => $obj->get_group(),
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
			'%s', // name
			'%s', // slug
			'%d', // term_group
		);
	}

}