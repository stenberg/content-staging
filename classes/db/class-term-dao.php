<?php
namespace Me\Stenberg\Content\Staging\DB;

use Me\Stenberg\Content\Staging\DB\Mappers\Taxonomy_Mapper;
use Me\Stenberg\Content\Staging\DB\Mappers\Term_Mapper;
use Me\Stenberg\Content\Staging\Models\Post;
use Me\Stenberg\Content\Staging\Models\Relationships\Post_Taxonomy;
use Me\Stenberg\Content\Staging\Models\Taxonomy;
use Me\Stenberg\Content\Staging\Models\Term;

class Term_DAO extends DAO {

	private $term_mapper;

	public function __construct( $wpdb, Term_Mapper $term_mapper ) {
		parent::__constuct( $wpdb );

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
	 * Check if a relationship between a post and a taxonomy has been
	 * persisted to database.
	 *
	 * @param Post_Taxonomy $post_taxonomy
	 * @return bool
	 */
	public function has_post_taxonomy_relationship( Post_Taxonomy $post_taxonomy ) {
		$query = $this->wpdb->prepare(
			'SELECT COUNT(*) FROM ' . $this->wpdb->term_relationships . ' WHERE object_id = %d AND term_taxonomy_id = %d',
			$post_taxonomy->get_post()->get_id(),
			$post_taxonomy->get_taxonomy()->get_id()
		);

		if ( $this->wpdb->get_var( $query ) > 0 ) {
			return true;
		}

		return false;
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

		return $this->wpdb->get_results( $query, ARRAY_A );
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
			$taxonomies[] = $this->create_taxonomy_object( $taxonomy );
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

		return $this->create_taxonomy_object( $this->wpdb->get_row( $query, ARRAY_A ) );
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

		return $this->create_taxonomy_object( $this->wpdb->get_row( $query, ARRAY_A ) );
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
			'SELECT term_taxonomy_id FROM ' . $this->wpdb->term_taxonomy . ' WHERE term_id = %d AND taxonomy = %s',
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
	 * Find term with the same slug as the provided term. If a match is
	 * found, update provided term with the term ID we got from database.
	 *
	 * Useful for comparing a term sent from content staging to production.
	 *
	 * @param Term $term
	 */
	public function get_term_id_by_slug( Term $term ) {

		$query = $this->wpdb->prepare(
			'SELECT term_id FROM ' . $this->wpdb->terms . ' WHERE slug = %s',
			$term->get_slug()
		);

		$row = $this->wpdb->get_row( $query, ARRAY_A );

		if ( isset( $row['term_id'] ) ) {
			$term->set_id( $row['term_id'] );
		} else {
			$term->set_id( null );
		}
	}

	/**
	 * Insert post/taxonomy relationship.
	 *
	 * @param Post_Taxonomy $post_taxonomy
	 */
	public function insert_post_taxonomy_relationship( Post_Taxonomy $post_taxonomy ) {
		$data = $this->filter_term_relationship_data( $post_taxonomy );
		$this->insert( 'term_relationships', $data['values'], $data['format'] );
	}

	/**
	 * Update post/taxonomy relationship.
	 *
	 * @param Post_Taxonomy $post_taxonomy
	 */
	public function update_post_taxonomy_relationship( Post_Taxonomy $post_taxonomy ) {
		$this->update(
			'term_relationships',
			array( 'term_order' => $post_taxonomy->get_term_order() ),
			array(
				'object_id'        => $post_taxonomy->get_post()->get_id(),
				'term_taxonomy_id' => $post_taxonomy->get_taxonomy()->get_id(),
			),
			array( '%d' ),
			array( '%d', '%d' )
		);
	}

	/**
	 * @param Taxonomy $taxonomy
	 */
	public function insert_taxonomy( Taxonomy $taxonomy ) {
		$data = $this->filter_taxonomy_data( $taxonomy );
		$taxonomy->set_id( $this->insert( 'term_taxonomy', $data['values'], $data['format'] ) );
	}

	/**
	 * @param Taxonomy $taxonomy
	 */
	public function update_taxonomy( Taxonomy $taxonomy ) {
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
	 */
	public function insert_term( Term $term ) {
		$data = $this->filter_term_data( $term );
		$term->set_id( $this->insert( 'terms', $data['values'], $data['format'] ) );
	}

	/**
	 * @param Term $term
	 */
	public function update_term( Term $term ) {
		$data = $this->filter_term_data( $term );
		$this->update( 'terms', $data['values'], array( 'term_id' => $term->get_id() ), $data['format'], array( '%d' ) );
	}

	/**
	 * @param Post_Taxonomy $post_taxonomy
	 * @return array
	 */
	private function filter_term_relationship_data( Post_Taxonomy $post_taxonomy ) {

		$values = array();
		$format = array();

		$values['object_id'] = $post_taxonomy->get_post()->get_id();
		$format[]            = '%d';

		$values['term_taxonomy_id'] = $post_taxonomy->get_taxonomy()->get_id();
		$format[]                   = '%d';

		$values['term_order'] = $post_taxonomy->get_term_order();
		$format[]             = '%d';

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

		if ( $taxonomy->get_term() ) {
			$values['term_id'] = $taxonomy->get_term()->get_id();
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
			$values['parent'] = $taxonomy->get_parent()->get_term()->get_id();
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

	/**
	 * Populate a Post object with Post_Taxonomy relationships.
	 *
	 * @param Post $post
	 */
	public function get_post_taxonomy_relationships( Post $post ) {
		$relationships = $this->get_term_relationships_by_post_id( $post->get_id() );

		foreach ( $relationships as $relationship ) {
			$taxonomy = $this->get_term_taxonomy_by_id( $relationship['term_taxonomy_id'] );

			if ( $taxonomy instanceof Taxonomy ) {
				$post->add_post_taxonomy( new Post_Taxonomy( $post, $taxonomy ) );
			}
		}
	}

	/**
	 * Take an array that was produced from an SQL query and map the
	 * array values to a Taxonomy object.
	 *
	 * @param array $array
	 * @return Taxonomy
	 */
	public function create_taxonomy_object( $array ) {

		$taxonomy = null;

		if ( ! empty( $array ) ) {

			$taxonomy = new Taxonomy();

			if ( isset( $array['term_taxonomy_id'] ) ) {
				$taxonomy->set_id( $array['term_taxonomy_id'] );
			}

			if ( isset( $array['term_id'] ) ) {
				$term = $this->get_term_by_id( $array['term_id'] );
				if ( $term instanceof Term ) {
					$taxonomy->set_term( $term );
				}
			}

			if ( isset( $array['taxonomy'] ) ) {
				$taxonomy->set_taxonomy( $array['taxonomy'] );
			}

			if ( isset( $array['description'] ) ) {
				$taxonomy->set_description( $array['description'] );
			}

			if ( isset( $array['parent'] ) ) {
				$parent_taxonomy = $this->get_term_taxonomy_by_term_id_taxonomy(
					$array['parent'], $array['taxonomy']
				);
				if ( $parent_taxonomy instanceof Taxonomy ) {
					$taxonomy->set_parent( $parent_taxonomy );
				}
			}

			if ( isset( $array['count'] ) ) {
				$taxonomy->set_count( $array['count'] );
			}
		}

		return $taxonomy;
	}

}