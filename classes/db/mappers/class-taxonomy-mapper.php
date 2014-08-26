<?php
namespace Me\Stenberg\Content\Staging\DB\Mappers;

use Me\Stenberg\Content\Staging\Models\Taxonomy;

class Taxonomy_Mapper extends Mapper {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Nothing here atm.
	}

	/**
	 * Take an array that was produced from an SQL query and map the
	 * array values to a Taxonomy object.
	 *
	 * @param array $array
	 * @return Taxonomy
	 */
	public function array_to_taxonomy_object( $array ) {

		$taxonomy = null;

		if ( ! empty( $array ) ) {

			$taxonomy = new Taxonomy();

			if ( isset( $array['term_taxonomy_id'] ) ) {
				$taxonomy->set_id( $array['term_taxonomy_id'] );
			}

			if ( isset( $array['term_id'] ) ) {
				$taxonomy->set_term_id( $array['term_id'] );
			}

			if ( isset( $array['taxonomy'] ) ) {
				$taxonomy->set_taxonomy( $array['taxonomy'] );
			}

			if ( isset( $array['description'] ) ) {
				$taxonomy->set_description( $array['description'] );
			}

			if ( isset( $array['parent'] ) ) {
				$taxonomy->set_parent( $array['parent'] );
			}

			if ( isset( $array['count'] ) ) {
				$taxonomy->set_count( $array['count'] );
			}
		}

		return $taxonomy;
	}

}