<?php
namespace Me\Stenberg\Content\Staging\DB;

use Me\Stenberg\Content\Staging\Helper_Factory;
use Me\Stenberg\Content\Staging\Models\Model;
use Me\Stenberg\Content\Staging\Models\Taxonomy;

class Taxonomy_DAO extends DAO {

	private $table;
	private $term_dao;

	public function __construct( $wpdb ) {
		parent::__constuct( $wpdb );
		$this->table    = $wpdb->term_taxonomy;
		$this->term_dao = Helper_Factory::get_instance()->get_dao( 'Term' );
	}

	/**
	 * Get taxonomy record by term ID and taxonomy.
	 *
	 * @param int $term_id
	 * @param string $taxonomy
	 * @return object
	 */
	public function get_taxonomy_by_term_id_taxonomy( $term_id, $taxonomy ) {
		$query = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->table . ' WHERE term_id = %d AND taxonomy = %s',
			$term_id,
			$taxonomy
		);

		$result = $this->wpdb->get_row( $query, ARRAY_A );

		if ( isset( $result['term_taxonomy_id'] ) ) {
			return $this->create_object( $result );
		}

		return null;
	}

	/**
	 * Find taxonomy with the same term ID and taxonomy (type) as for the
	 * provided taxonomy. If a match is found, update provided taxonomy with
	 * the taxonomy ID we got from database.
	 *
	 * Useful for comparing a post sent from content staging to production.
	 *
	 * @param Taxonomy $taxonomy
	 */
	public function get_taxonomy_id_by_taxonomy( Taxonomy $taxonomy ) {

		$query = $this->wpdb->prepare(
			'SELECT term_taxonomy_id FROM ' . $this->table . ' WHERE term_id = %d AND taxonomy = %s',
			$taxonomy->get_term()->get_id(),
			$taxonomy->get_taxonomy()
		);

		$row = $this->wpdb->get_row( $query, ARRAY_A );

		if ( isset( $row['term_taxonomy_id'] ) ) {
			$taxonomy->set_id( $row['term_taxonomy_id'] );
		} else {
			$taxonomy->set_id( null );
		}
	}

	/**
	 * @param Taxonomy $taxonomy
	 */
	public function update_taxonomy( Taxonomy $taxonomy ) {
		$data         = $this->create_array( $taxonomy );
		$where        = array( 'term_taxonomy_id' => $taxonomy->get_id() );
		$format       = $this->format();
		$where_format = array( '%d' );
		$this->update( $data, $where, $format, $where_format );
		$this->update_term_hierarchy( $taxonomy );
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
		return '\Me\Stenberg\Content\Staging\Models\Taxonomy';
	}

	/**
	 * @param array $raw
	 * @return string
	 */
	protected function unique_key( array $raw ) {
		return $raw['term_taxonomy_id'];
	}

	/**
	 * @return string
	 */
	protected function select_stmt() {
		return 'SELECT * FROM ' . $this->table . ' WHERE term_taxonomy_id = %d';
	}

	/**
	 * @param array $ids
	 * @return string
	 */
	protected function select_by_ids_stmt( array $ids ) {
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		return 'SELECT * FROM ' . $this->table . ' WHERE term_taxonomy_id in (' . $placeholders . ')';
	}

	/**
	 * @param Model $obj
	 */
	protected function do_insert( Model $obj ) {
		$data   = $this->create_array( $obj );
		$format = $this->format();
		$this->wpdb->insert( $this->table, $data, $format );
		$obj->set_id( $this->wpdb->insert_id );
		$this->update_term_hierarchy( $obj );
	}

	/**
	 * @param array $raw
	 * @return Taxonomy
	 */
	protected function do_create_object( array $raw ) {
		$obj  = new Taxonomy( $raw['term_taxonomy_id'] );
		$term = $this->term_dao->find( $raw['term_id'] );
		$term->set_taxonomy( $obj );
		$parent = $this->get_taxonomy_by_term_id_taxonomy( $raw['parent'], $raw['taxonomy'] );
		$obj->set_term( $term );
		$obj->set_taxonomy( $raw['taxonomy'] );
		$obj->set_description( $raw['description'] );
		if ( $parent !== null ) {
			$obj->set_parent( $parent );
		}
		$obj->set_count( $raw['count'] );
		return $obj;
	}

	/**
	 * @param Model $obj
	 * @return array
	 */
	protected function do_create_array( Model $obj ) {
		$parent = 0;

		if ( $obj->get_parent() !== null ) {
			if ( $obj->get_parent()->get_term() !== null ) {
				$parent = $obj->get_parent()->get_term()->get_id();
			}
		}

		return array(
			'term_id'     => $obj->get_term()->get_id(),
			'taxonomy'    => $obj->get_taxonomy(),
			'description' => $obj->get_description(),
			'parent'      => $parent,
			'count'       => $obj->get_count(),
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
			'%d', // term_id
			'%s', // taxonomy
			'%s', // description
			'%d', // parent
			'%d', // count
		);
	}

	/**
	 * @param Taxonomy $taxonomy
	 */
	private function update_term_hierarchy( Taxonomy $taxonomy ) {

		$do_insert = false;
		$do_update = false;

		// Get term hierarchy for this taxonomy.
		$hierarchy = $this->get_term_hierarchy( $taxonomy );

		// No term hierarchy exists. Should be created.
		if ( $hierarchy === null ) {
			$hierarchy = array();
			$do_insert = true;
		}

		// ID of parent term.
		$parent_id = null;
		if ( $taxonomy->get_parent() ) {
			$parent_id = $taxonomy->get_parent()->get_term()->get_id();
		}

		if ( array_key_exists( $parent_id, $hierarchy ) &&
			! in_array( $taxonomy->get_term()->get_id(), $hierarchy[$parent_id] ) ) {
			/*
			 * The parent term exist in the hierarchy, but the term has not been
			 * added as a child yet. Add the term as a child.
			 */
			array_push( $hierarchy[$parent_id], (int) $taxonomy->get_term()->get_id() );
			$do_update = true;
		}

		if ( $parent_id && ! array_key_exists( $parent_id, $hierarchy ) ) {
			/*
			 * The parent term does not exist in the hierarchy. Add the parent term
			 * to the hierarchy as an array index and the term as a child.
			 */
			$hierarchy[$parent_id] = array();
			array_push( $hierarchy[$parent_id], (int) $taxonomy->get_term()->get_id() );
			$do_update = true;
		}

		if ( ! $parent_id ) {
			foreach( $hierarchy as $term => $children ) {
				if ( ( $index = array_search( $taxonomy->get_term()->get_id(), $children) ) !== false ) {
					/*
					 * The term used to have a parent term, but that is no longer the case.
					 * Remove the child term from the hierarchy.
					 */
					unset( $hierarchy[$term][$index] );

					if ( empty( $hierarchy[$term] ) ) {
						// No term children exist anymore, remove this parent term from hierarchy.
						unset( $hierarchy[$term] );
					}

					$do_update = true;
					break;
				}
			}
		}

		if ( $do_insert ) {
			$this->wpdb->insert(
				$this->wpdb->options,
				array(
					'option_name'  => $taxonomy->get_taxonomy() . '_children',
					'option_value' => serialize( $hierarchy ),
					'autoload'     => 'yes',
				),
				array( '%s', '%s', '%s' )
			);
		}

		if ( ! $do_insert && $do_update ) {
			$this->wpdb->update(
				$this->wpdb->options,
				array(
					'option_value' => serialize( $hierarchy ),
				),
				array(
					'option_name' => $taxonomy->get_taxonomy() . '_children',
				),
				array( '%s' ),
				array( '%s' )
			);
		}
	}

	/**
	 * @param Taxonomy $taxonomy
	 * @return array
	 */
	private function get_term_hierarchy( Taxonomy $taxonomy ) {
		$query = $this->wpdb->prepare(
			'SELECT option_value FROM ' . $this->wpdb->options . ' WHERE option_name = %s',
			$taxonomy->get_taxonomy() . '_children'
		);

		$result = $this->wpdb->get_row( $query, ARRAY_A );

		if ( isset( $result['option_value'] ) ) {
			if ( $array = unserialize( $result['option_value'] ) ) {
				return $array;
			} else {
				return array();
			}
		}

		return null;
	}

}