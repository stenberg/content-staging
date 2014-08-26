<?php
namespace Me\Stenberg\Content\Staging\DB;

use Me\Stenberg\Content\Staging\DB\Mappers\Taxonomy_Mapper;
use Me\Stenberg\Content\Staging\DB\Mappers\Term_Mapper;
use Me\Stenberg\Content\Staging\Models\Taxonomy;
use Me\Stenberg\Content\Staging\Models\Term;

class Term_DAO extends DAO {

	private $term_mapper;
	private $taxonomy_mapper;

	public function __construct( $wpdb, Taxonomy_Mapper $taxonomy_mapper, Term_Mapper $term_mapper ) {
		parent::__constuct( $wpdb );

		$this->taxonomy_mapper = $taxonomy_mapper;
		$this->term_mapper     = $term_mapper;
	}

	public function get_term_relationship( $object_id, $term_taxonomy_id ) {
		$query = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->wpdb->term_relationships . ' WHERE object_id = %d AND term_taxonomy_id = %d',
			$object_id,
			$term_taxonomy_id
		);

		return $this->wpdb->get_row( $query, ARRAY_A );
	}

	/**
	 * Get terms related to any of the provided posts.
	 *
	 * @param array $post_ids
	 * @return array
	 */
	public function get_term_relationships_by_post_ids( $post_ids ) {

		$placeholders = $this->in_clause_placeholders( $post_ids, '%d' );

		if ( ! $placeholders ) {
			return array();
		}

		$query = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->wpdb->term_relationships . ' WHERE object_id IN (' . $placeholders . ')',
			$post_ids
		);

		return $this->wpdb->get_results( $query, ARRAY_A );
	}

	/**
	 * Get terms related to a post.
	 *
	 * @param int $post_id
	 * @return array
	 */
	public function get_term_relationships_by_post_id( $post_id ) {
		$query = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->wpdb->term_relationships . ' WHERE object_id = %d',
			$post_id
		);

		return $this->wpdb->get_results( $query );
	}

	/**
	 * Get term taxonomies by term taxonomy IDs.
	 *
	 * @param array $term_taxonomy_ids
	 * @return array
	 */
	public function get_term_taxonomies_by_ids( $term_taxonomy_ids ) {

		$taxonomies = array();

		$placeholders = $this->in_clause_placeholders( $term_taxonomy_ids, '%d' );

		if ( ! $placeholders ) {
			return array();
		}

		$query = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->wpdb->term_taxonomy . ' WHERE term_taxonomy_id IN (' . $placeholders . ')',
			$term_taxonomy_ids
		);

		foreach ( $this->wpdb->get_results( $query, ARRAY_A ) as $taxonomy ) {
			$taxonomies[] = $this->taxonomy_mapper->array_to_taxonomy_object( $taxonomy );
		}

		return $taxonomies;
	}

	/**
	 * Get taxonomy by term-taxonomy ID.
	 *
	 * @param int $term_taxonomy_id
	 * @return object
	 */
	public function get_term_taxonomy_by_id( $term_taxonomy_id ) {
		$query = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->wpdb->term_taxonomy . ' WHERE term_taxonomy_id = %d',
			$term_taxonomy_id
		);

		return $this->taxonomy_mapper->array_to_taxonomy_object( $this->wpdb->get_row( $query, ARRAY_A ) );
	}

	/**
	 * Get taxonomy record by term ID and texonomy.
	 *
	 * @param int $term_id
	 * @param string $taxonomy
	 * @return object
	 */
	public function get_term_taxonomy_by_term_id_taxonomy( $term_id, $taxonomy ) {
		$query = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->wpdb->term_taxonomy . ' WHERE term_id = %d AND taxonomy = %s',
			$term_id,
			$taxonomy
		);

		return $this->taxonomy_mapper->array_to_taxonomy_object( $this->wpdb->get_row( $query, ARRAY_A ) );
	}

	/**
	 * Get terms by term IDs.
	 *
	 * @param array $term_ids
	 * @return array
	 */
	public function get_terms_by_ids( $term_ids ) {

		$terms = array();

		$placeholders = $this->in_clause_placeholders( $term_ids, '%d' );

		if ( ! $placeholders ) {
			return array();
		}

		$query = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->wpdb->terms . ' WHERE term_id IN (' . $placeholders . ')',
			$term_ids
		);

		foreach ( $this->wpdb->get_results( $query, ARRAY_A ) as $term ) {
			$terms[] = $this->term_mapper->array_to_term_object( $term );
		}

		return $terms;
	}

	/**
	 * Get term by term ID.
	 *
	 * @param int $term_id
	 * @return Term
	 */
	public function get_term_by_id( $term_id ) {
		$query = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->wpdb->terms . ' WHERE term_id = %d',
			$term_id
		);

		return $this->term_mapper->array_to_term_object( $this->wpdb->get_row( $query, ARRAY_A ) );
	}

	/**
	 * Get term by slug.
	 *
	 * @param string $slug
	 * @return Term
	 */
	public function get_term_by_slug( $slug ) {
		$query = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->wpdb->terms . ' WHERE slug = %s',
			$slug
		);

		return $this->term_mapper->array_to_term_object( $this->wpdb->get_row( $query, ARRAY_A ) );
	}

	/**
	 * Insert term relationship.
	 *
	 * @param array $relationship
	 */
	public function insert_term_relationship( $relationship ) {
		$data = $this->filter_term_relationship_data( $relationship );
		$this->insert( 'term_relationships', $data['values'], $data['format'] );
	}

	/**
	 * @param array $relationship
	 */
	public function update_term_relationship_by_object_taxonomy( $relationship ) {
		$data = $this->filter_term_relationship_data( $relationship );
		$this->update(
			'term_relationships',
			$data['values'],
			array(
				'object_id'        => $relationship['object_id'],
				'term_taxonomy_id' => $relationship['term_taxonomy_id'],
			),
			$data['format'],
			array( '%d', '%d' )
		);
	}

	/**
	 * @param Taxonomy $taxonomy
	 * @return int
	 */
	public function insert_term_taxonomy( Taxonomy $taxonomy ) {
		$data = $this->filter_taxonomy_data( $taxonomy );
		return $this->insert( 'term_taxonomy', $data['values'], $data['format'] );
	}

	/**
	 * @param Taxonomy $taxonomy
	 */
	public function update_term_taxonomy_by_id( Taxonomy $taxonomy ) {
		$data = $this->filter_taxonomy_data( $taxonomy );
		$this->update(
			'term_taxonomy',
			$data['values'],
			array( 'term_taxonomy_id' => $taxonomy->get_id() ),
			$data['format'],
			array( '%d' )
		);
	}

	/**
	 * @param Term $term
	 * @return int
	 */
	public function insert_term( Term $term ) {
		$data = $this->filter_term_data( $term );
		return $this->insert( 'terms', $data['values'], $data['format'] );
	}

	/**
	 * @param int $id
	 * @param Term $term
	 */
	public function update_term_by_id( $id, Term $term ) {
		$data = $this->filter_term_data( $term );
		$this->update( 'terms', $data['values'], array( 'term_id' => $id ), $data['format'], array( '%d' ) );
	}

	private function filter_term_relationship_data( $relationship ) {

		$values = array();
		$format = array();

		if ( isset( $relationship['object_id'] ) ) {
			$values['object_id'] = $relationship['object_id'];
			$format[]            = '%d';
		}

		if ( isset( $relationship['term_taxonomy_id'] ) ) {
			$values['term_taxonomy_id'] = $relationship['term_taxonomy_id'];
			$format[]                   = '%d';
		}

		if ( isset( $relationship['term_order'] ) ) {
			$values['term_order'] = $relationship['term_order'];
			$format[]             = '%d';
		}

		return array(
			'values' => $values,
			'format' => $format,
		);
	}

	/**
	 * @param Taxonomy $taxonomy
	 * @return array
	 */
	private function filter_taxonomy_data( Taxonomy $taxonomy ) {

		$values = array();
		$format = array();

		if ( $taxonomy->get_term_id() ) {
			$values['term_id'] = $taxonomy->get_term_id();
			$format[]          = '%d';
		}

		if ( $taxonomy->get_taxonomy() ) {
			$values['taxonomy'] = $taxonomy->get_taxonomy();
			$format[]           = '%s';
		}

		if ( $taxonomy->get_description() ) {
			$values['description'] = $taxonomy->get_description();
			$format[]              = '%s';
		}

		if ( $taxonomy->get_parent() ) {
			$values['parent'] = $taxonomy->get_parent();
			$format[]         = '%d';
		}

		if ( $taxonomy->get_count() ) {
			$values['count'] = $taxonomy->get_count();
			$format[]        = '%d';
		}

		return array(
			'values' => $values,
			'format' => $format,
		);
	}

	/**
	 * @param Term $term
	 * @return array
	 */
	private function filter_term_data( Term $term ) {

		$values = array();
		$format = array();

		if ( $term->get_name() ) {
			$values['name'] = $term->get_name();
			$format[]       = '%s';
		}

		if ( $term->get_slug() ) {
			$values['slug'] = $term->get_slug();
			$format[]       = '%s';
		}

		if ( $term->get_group() ) {
			$values['term_group'] = $term->get_group();
			$format[]             = '%d';
		}

		return array(
			'values' => $values,
			'format' => $format,
		);
	}
}