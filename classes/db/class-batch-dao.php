<?php
namespace Me\Stenberg\Content\Staging\DB;

use Me\Stenberg\Content\Staging\DB\Mappers\Batch_Mapper;
use Me\Stenberg\Content\Staging\Models\Batch;

class Batch_DAO extends DAO {

	private $batch_mapper;

	public function __construct( $wpdb, Batch_Mapper $batch_mapper ) {
		parent::__constuct( $wpdb );

		$this->batch_mapper = $batch_mapper;
	}

	/**
	 * Get batch by id.
	 *
	 * @param $id
	 * @return Batch
	 */
	public function get_batch_by_id( $id ) {
		$query = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->wpdb->posts . ' WHERE ID = %d',
			$id
		);

		return $this->batch_mapper->array_to_batch_object( $this->wpdb->get_row( $query, ARRAY_A ) );
	}

	/**
	 * Get batch by global unique identifier.
	 *
	 * @param $guid
	 * @return Batch
	 */
	public function get_batch_by_guid( $guid ) {

		$guid = $this->normalize_guid( $guid );

		// Select post with a specific GUID ending.
		$query = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->wpdb->posts . ' WHERE guid LIKE %s',
			'%' . $guid
		);

		return $this->batch_mapper->array_to_batch_object( $this->wpdb->get_row( $query, ARRAY_A ) );
	}

	/**
	 * Get published content batches.
	 *
	 * @param string $order_by
	 * @param string $order
	 * @param int $per_page
	 * @param int $paged
	 * @return array
	 */
	public function get_published_content_batches( $order_by = null, $order = 'asc', $per_page = 5, $paged = 1 ) {

		$batches = array();

		// Only allow to order the query result by the following fields.
		$allowed_order_by_values = array( 'post_title', 'post_modified' );

		// Make sure provided order by value is allowed.
		if ( ! in_array( $order_by, $allowed_order_by_values ) ) {
			$order_by = null;
		}

		// Only allow sorting results ascending or descending.
		if ( $order !== 'asc' ) {
			$order = 'desc';
		}

		$stmt   = 'SELECT * FROM ' . $this->wpdb->posts . ' WHERE post_type = "sme_content_batch" AND post_status = "publish"';
		$values = array();

		if ( ! empty( $order_by ) && ! empty( $order ) ) {
			$stmt    .= ' ORDER BY ' . $order_by . ' ' . $order;
		}

		// Adjust the query to take pagination into account.
		if ( ! empty( $paged ) && ! empty( $per_page ) ) {
			$stmt    .= ' LIMIT %d, %d';
			$values[] = ( $paged - 1 ) * $per_page;
			$values[] = $per_page;
		}

		$query = $this->wpdb->prepare( $stmt, $values );

		foreach ( $this->wpdb->get_results( $query, ARRAY_A ) as $batch ) {
			$batches[] = $this->batch_mapper->array_to_batch_object( $batch );
		}

		return $batches;
	}

	/**
	 * Get number of published content batches that exists.
	 *
	 * @return int
	 */
	public function get_published_content_batches_count() {
		return $this->wpdb->get_var( 'SELECT COUNT(*) FROM ' . $this->wpdb->posts . ' WHERE post_type = "sme_content_batch" AND post_status = "publish"' );
	}

	/**
	 * @param Batch $batch
	 * @return int
	 */
	public function insert_batch( Batch $batch ) {

		/*
		 * Name (slug) of this batch. Batches are inserted into the posts table.
		 * Every post should have a name.
		 */
		$name = '';

		$batch->set_date( current_time( 'mysql' ) );
		$batch->set_date_gmt( current_time( 'mysql', 1 ) );
		$batch->set_modified( $batch->get_date() );
		$batch->set_modified_gmt( $batch->get_date_gmt() );

		// Set a post name (slug).
		if ( $batch->get_title() ) {
			$name = sanitize_title( $batch->get_title() );
		}

		$data = $this->filter_batch_data( $batch );

		$id = wp_insert_post( $data['values'] );

		/*
		 * If no 'post_name' has been created for this batch, then use the newly
		 * generated ID as 'post_name'.
		 */
		if ( ! $name ) {
			$this->update( 'posts', array( 'post_name' => $id ), array( 'ID' => $id ), array( '%s' ), array( '%s' ) );
		}

		return $id;
	}

	/**
	 * @param Batch $batch
	 */
	public function update_batch( Batch $batch ) {

		$batch->set_modified( current_time( 'mysql' ) );
		$batch->set_modified_gmt( current_time( 'mysql', 1 ) );

		$data = $this->filter_batch_data( $batch );

		$this->update(
			'posts', $data['values'], array( 'ID' => $batch->get_id() ), $data['format'], array( '%d' )
		);
	}

	/**
	 * Delete provided batch.
	 *
	 * @param Batch $batch
	 */
	public function delete_batch( Batch $batch ) {
		wp_delete_post( $batch->get_id() );
	}

	/**
	 * @param Batch $batch
	 * @return array
	 */
	private function filter_batch_data( Batch $batch ) {

		$values = array(
			'post_status'    => 'publish',
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
			'post_type'      => 'sme_content_batch'
		);

		$format = array( '%s', '%s', '%s', '%s' );

		if ( $batch->get_creator_id() ) {
			$values['post_author'] = $batch->get_creator_id();
			$format[]              = '%d';
		}

		if ( $batch->get_date() ) {
			$values['post_date'] = $batch->get_date();
			$format[]            = '%s';
		}

		if ( $batch->get_date_gmt() ) {
			$values['post_date_gmt'] = $batch->get_date_gmt();
			$format[]                = '%s';
		}

		if ( $batch->get_content() ) {
			$values['post_content'] = $batch->get_content();
			$format[]               = '%s';
		}

		if ( $batch->get_title() ) {
			$values['post_title'] = $batch->get_title();
			$format[]             = '%s';
		}

		if ( $batch->get_modified() ) {
			$values['post_modified'] = $batch->get_modified();
			$format[]                = '%s';
		}

		if ( $batch->get_modified_gmt() ) {
			$values['post_modified_gmt'] = $batch->get_modified_gmt();
			$format[]                    = '%s';
		}

		if ( $batch->get_guid() ) {
			$values['guid'] = $batch->get_guid();
			$format[]       = '%s';
		}

		return array(
			'values' => $values,
			'format' => $format,
		);
	}

}
