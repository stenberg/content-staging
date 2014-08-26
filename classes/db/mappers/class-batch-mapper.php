<?php
namespace Me\Stenberg\Content\Staging\DB\Mappers;

use Me\Stenberg\Content\Staging\Models\Batch;

class Batch_Mapper extends Mapper {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Nothing here atm.
	}

	/**
	 * Take an array that was produced from an SQL query and map the
	 * array values to a Batch object.
	 *
	 * @param array $array
	 * @return Batch
	 */
	public function array_to_batch_object( $array ) {

		$batch = null;

		/*
		 * If array has been populated with values, map these values to a new
		 * Batch object.
		 */
		if ( ! empty( $array ) ) {

			$batch = new Batch();

			if ( isset( $array['ID'] ) ) {
				$batch->set_id( $array['ID'] );
			}

			if ( isset( $array['guid'] ) ) {
				$batch->set_guid( $array['guid'] );
			}

			if ( isset( $array['post_title'] ) ) {
				$batch->set_title( $array['post_title'] );
			}

			if ( isset( $array['post_content'] ) ) {
				$batch->set_content( $array['post_content'] );
			}

			if ( isset( $array['post_author'] ) ) {
				$batch->set_creator_id( $array['post_author'] );
			}

			if ( isset( $array['post_date'] ) ) {
				$batch->set_date( $array['post_date'] );
			}

			if ( isset( $array['post_date_gmt'] ) ) {
				$batch->set_date_gmt( $array['post_date_gmt'] );
			}

			if ( isset( $array['post_modified'] ) ) {
				$batch->set_modified( $array['post_modified'] );
			}

			if ( isset( $array['post_modified_gmt'] ) ) {
				$batch->set_modified_gmt( $array['post_modified_gmt'] );
			}
		}

		return $batch;
	}

}