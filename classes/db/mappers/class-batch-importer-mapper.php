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
	 * @param array $post
	 * @param array $meta
	 * @return Batch_Importer
	 */
	public function array_to_importer_object( $post, $meta ) {

		$importer = null;

		/*
		 * If array has been populated with values, map these values to a new
		 * Batch_Importer object.
		 */
		if ( ! empty( $post ) ) {

			$importer = new Batch_Importer();

			if ( isset( $post['ID'] ) ) {
				$importer->set_id( $post['ID'] );
			}

			if ( isset( $post['post_author'] ) ) {
				$importer->set_creator_id( $post['post_author'] );
			}

			if ( isset( $post['post_date'] ) ) {
				$importer->set_date( $post['post_date'] );
			}

			if ( isset( $post['post_date_gmt'] ) ) {
				$importer->set_date_gmt( $post['post_date_gmt'] );
			}

			if ( isset( $post['post_modified'] ) ) {
				$importer->set_modified( $post['post_modified'] );
			}

			if ( isset( $post['post_modified_gmt'] ) ) {
				$importer->set_modified_gmt( $post['post_modified_gmt'] );
			}

			if ( isset( $post['post_content'] ) ) {
				$importer->set_batch( unserialize( base64_decode( $post['post_content'] ) ) );
			}

			if ( ! empty( $meta ) ) {
				foreach ( $meta as $key => $value ) {
					if ( $key == 'sme_import_messages' ) {
						$messages = unserialize( $value );
						foreach ( $messages as $level => $message ) {
							$importer->add_message( $message, $level );
						}
					}
					if ( $key == 'sme_import_status' ) {
						$importer->set_status( $value );
					}
				}
			}
		}

		return $importer;
	}

}