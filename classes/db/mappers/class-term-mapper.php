<?php
namespace Me\Stenberg\Content\Staging\DB\Mappers;

use Me\Stenberg\Content\Staging\Models\Term;

class Term_Mapper extends Mapper {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Nothing here atm.
	}

	/**
	 * Take an array that was produced from an SQL query and map the
	 * array values to a Term object.
	 *
	 * @param array $array
	 * @return Term
	 */
	public function array_to_term_object( $array ) {

		$term = null;

		if ( ! empty( $array ) ) {

			$term = new Term();

			if ( isset( $array['term_id'] ) ) {
				$term->set_id( $array['term_id'] );
			}

			if ( isset( $array['name'] ) ) {
				$term->set_name( $array['name'] );
			}

			if ( isset( $array['slug'] ) ) {
				$term->set_slug( $array['slug'] );
			}

			if ( isset( $array['term_group'] ) ) {
				$term->set_group( $array['term_group'] );
			}
		}

		return $term;
	}

}