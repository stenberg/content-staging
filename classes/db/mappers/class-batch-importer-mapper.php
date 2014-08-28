<?php
namespace Me\Stenberg\Content\Staging\DB\Mappers;

use Me\Stenberg\Content\Staging\Models\Batch_Importer;

class Batch_Importer_Mapper extends Mapper {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Nothing here atm.
	}

	/**
	 * Take an array that was produced from an SQL query and map the
	 * array values to a Batch_Importer object.
	 *
	 * @param array $array
	 * @return Batch_Importer
	 */
	public function array_to_importer_object( $array ) {

		$importer = null;

		/*
		 * If array has been populated with values, map these values to a new
		 * Batch_Importer object.
		 */
		if ( ! empty( $array ) ) {

			$importer = new Batch_Importer();

			if ( isset( $array['ID'] ) ) {
				$importer->set_id( $array['ID'] );
			}

			if ( isset( $array['post_author'] ) ) {
				$importer->set_creator_id( $array['post_author'] );
			}

			if ( isset( $array['post_date'] ) ) {
				$importer->set_date( $array['post_date'] );
			}

			if ( isset( $array['post_date_gmt'] ) ) {
				$importer->set_date_gmt( $array['post_date_gmt'] );
			}

			if ( isset( $array['post_modified'] ) ) {
				$importer->set_modified( $array['post_modified'] );
			}

			if ( isset( $array['post_modified_gmt'] ) ) {
				$importer->set_modified_gmt( $array['post_modified_gmt'] );
			}

			if ( isset( $array['post_content'] ) ) {
				$importer->set_batch( unserialize( base64_decode( $array['post_content'] ) ) );
			}
		}

		return $importer;
	}

}