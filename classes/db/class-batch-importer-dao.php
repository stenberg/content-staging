<?php
namespace Me\Stenberg\Content\Staging\DB;

use Exception;
use Me\Stenberg\Content\Staging\DB\Mappers\Batch_Importer_Mapper;
use Me\Stenberg\Content\Staging\Models\Batch_Importer;

class Batch_Importer_DAO extends DAO {

	private $importer_mapper;

	public function __construct( $wpdb, Batch_Importer_Mapper $importer_mapper ) {
		parent::__constuct( $wpdb );

		$this->importer_mapper = $importer_mapper;
	}

	/**
	 * Get importer by id.
	 *
	 * @param $id
	 * @return Batch_Importer
	 */
	public function get_importer_by_id( $id ) {
		$importer_query = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->wpdb->posts . ' WHERE ID = %d',
			$id
		);

		$meta_query = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->wpdb->postmeta . ' WHERE post_id = %d',
			$id
		);

		$importer_data = $this->wpdb->get_row( $importer_query, ARRAY_A );
		$meta_data     = $this->wpdb->get_results( $meta_query, ARRAY_A );

		return $this->importer_mapper->array_to_importer_object( $importer_data, $meta_data );
	}

	/**
	 * @param Batch_Importer $importer
	 */
	public function insert_importer( Batch_Importer $importer ) {

		$importer->set_date( current_time( 'mysql' ) );
		$importer->set_date_gmt( current_time( 'mysql', 1 ) );
		$importer->set_modified( $importer->get_date() );
		$importer->set_modified_gmt( $importer->get_date_gmt() );

		$data = $this->filter_importer_data( $importer );

		$importer->set_id( wp_insert_post( $data['values'] ) );

		// Create a key needed to run this importer.
		$importer->generate_key();
		$this->update_post_meta( $importer->get_id(), 'sme_import_key', $importer->get_key() );
	}

	/**
	 * @param Batch_Importer $importer
	 */
	public function update_importer( Batch_Importer $importer ) {

		$importer->set_modified( current_time( 'mysql' ) );
		$importer->set_modified_gmt( current_time( 'mysql', 1 ) );

		$data = $this->filter_importer_data( $importer );

		$this->update(
			'posts', $data['values'], array( 'ID' => $importer->get_id() ), $data['format'], array( '%d' )
		);

		$this->update_post_meta( $importer->get_id(), 'sme_import_messages', $importer->get_messages() );
		$this->update_post_meta( $importer->get_id(), 'sme_import_status', $importer->get_status() );

		// Make the import key unusable so it cannot be used in any more imports.
		$importer->generate_key();
		$this->update_post_meta( $importer->get_id(), 'sme_import_key', $importer->get_key() );
	}

	/**
	 * Delete provided importer.
	 *
	 * Set 'post_status' for provided importer to 'draft'. This will hide the
	 * importer from users, but keep it for future references.
	 *
	 * Empty 'post_content'. Since importers can store huge batches in the
	 * 'post_content' field this is just a precaution so we do not fill the
	 * users database with a lot of unnecessary data.
	 *
	 * @param Batch_Importer $importer
	 */
	public function delete_importer( Batch_Importer $importer ) {

		$this->wpdb->update(
			$this->wpdb->posts,
			array(
				'post_content' => '',
				'post_status'  => 'draft',
			),
			array(
				'ID' => $importer->get_id(),
			),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * @param Batch_Importer $importer
	 * @return array
	 */
	private function filter_importer_data( Batch_Importer $importer ) {

		$values = array(
			'post_status'    => 'publish',
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
			'post_type'      => 'sme_batch_importer'
		);

		$format = array( '%s', '%s', '%s', '%s' );

		if ( $importer->get_creator_id() ) {
			$values['post_author'] = $importer->get_creator_id();
			$format[]              = '%d';
		}

		if ( $importer->get_date() ) {
			$values['post_date'] = $importer->get_date();
			$format[]            = '%s';
		}

		if ( $importer->get_date_gmt() ) {
			$values['post_date_gmt'] = $importer->get_date_gmt();
			$format[]                = '%s';
		}

		if ( $importer->get_modified() ) {
			$values['post_modified'] = $importer->get_modified();
			$format[]                = '%s';
		}

		if ( $importer->get_modified_gmt() ) {
			$values['post_modified_gmt'] = $importer->get_modified_gmt();
			$format[]                    = '%s';
		}

		if ( $importer->get_batch() ) {
			$values['post_content'] = base64_encode( serialize( $importer->get_batch() ) );
			$format[]               = '%s';
		}

		return array(
			'values' => $values,
			'format' => $format,
		);
	}

}
