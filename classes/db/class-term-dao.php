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
	 * Get term by term ID.
	 *
	 * @param int $term_id
	 * @return Term
	 */
	public function get_term_by_id( $term_id ) {
		$query = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->table . ' WHERE term_id = %d',
			$term_id
		);

		$result = $this->wpdb->get_row( $query, ARRAY_A );

		if ( isset( $result['term_id'] ) ) {
			return $this->create_object( $result );
		}

		return null;
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
	public function insert_term( Term $term ) {
		$data   = $this->create_array( $term );
		$format = $this->format();
		$this->wpdb->insert( $this->table, $data, $format );
		$term->set_id( $this->wpdb->insert_id );
	}

	/**
	 * @param Term $term
	 */
	public function update_term( Term $term ) {
		$data         = $this->create_array( $term );
		$where        = array( 'term_id' => $term->get_id() );
		$format       = $this->format();
		$where_format = array( '%d' );
		$this->wpdb->update( $this->table, $data, $where, $format, $where_format );
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
	private function format() {
		return array(
			'%s', // name
			'%s', // slug
			'%d', // term_group
		);
	}

}