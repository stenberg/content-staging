<?php
namespace Me\Stenberg\Content\Staging\DB;

class Postmeta_DAO extends DAO {

	public function __construct( $wpdb ) {
		parent::__constuct( $wpdb );
	}

	/**
	 * Get all post meta records for a specific post.
	 *
	 * @param int $post_id
	 * @return array
	 */
	public function get_postmetas_by_post_id( $post_id ) {
		$query = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->wpdb->postmeta . ' WHERE post_id = %d',
			$post_id
		);

		return $this->wpdb->get_results( $query, ARRAY_A );
	}

	/**
	 * Insert postmeta.
	 *
	 * @param array $postmeta
	 * @return int|null Return generated meta_id on success, null otherwise.
	 */
	public function insert_postmeta( $postmeta ) {

		$insert = $this->filter_input( $postmeta );

		if ( ! isset( $insert['data'] ) || ! isset( $insert['format'] ) ) {
			return null;
		}

		return $this->wpdb->insert( $this->wpdb->postmeta, $insert['data'], $insert['format'] );
	}

	/**
	 * Update post meta.
	 *
	 * @param array $record
	 */
	public function update_postmeta( $record ) {
		$this->wpdb->update(
			$this->wpdb->postmeta,
			array(
				'post_id'    => $record['post_id'],
				'meta_key'   => $record['meta_key'],
				'meta_value' => $record['meta_value'],
			),
			array( 'meta_id' => $record['meta_id'] ),
			array( '%d', '%s', '%s' ),
			array( '%d' )
		);
	}

	public function delete_postmeta( $where, $where_format ) {
		$this->wpdb->delete( $this->wpdb->postmeta, $where, $where_format );
	}

	/**
	 * Update all post meta for a post.
	 *
	 * @param int $post_id
	 * @param array $stage_records
	 */
	public function update_postmeta_by_post( $post_id, array $stage_records ) {

		$insert_keys = array();
		$stage_keys  = array();

		$insert = array();
		$update = array();
		$delete = array();

		$prod_records = $this->get_postmetas_by_post_id( $post_id );

		/*
		 * Go through each meta record we got from stage. If a meta_key exists
		 * more then once, then we will not try to update any records with that
		 * meta key.
		 */
		foreach ( $stage_records as $key => $prod_record ) {
			if ( in_array( $prod_record['meta_key'], $stage_keys ) ) {
				$insert[]      = $prod_record;
				$insert_keys[] = $prod_record['meta_key'];
				unset( $stage_records[$key] );
			} else {
				$stage_keys[] = $prod_record['meta_key'];
			}
		}

		/*
		 * Go through each meta record we got from production. If a meta_key
		 * exist that is already part of the keys scheduled for insertion or if a
		 * key that is found that is not part of the keys from stage, then
		 * schedule that record for deletion.
		 *
		 * Records left in $stage_records is candidates for being updated. Go
		 * through them and see if they already exist in $prod_records.
		 */
		foreach ( $prod_records as $prod_key => $prod_record ) {
			if ( ! in_array( $prod_record['meta_key'], $stage_keys ) || in_array( $prod_record['meta_key'], $insert_keys ) ) {
				$delete[] = $prod_record;
				unset( $prod_records[$prod_key] );
			} else {
				foreach ( $stage_records as $stage_key => $stage_record ) {
					if ( $stage_record['meta_key'] == $prod_record['meta_key'] ) {
						$stage_record['meta_id'] = $prod_record['meta_id'];
						$update[] = $stage_record;
						unset( $stage_records[$stage_key] );
						unset( $prod_records[$prod_key] );
					}
				}
			}
		}

		// Records left in $stage_records should be inserted.
		foreach ( $stage_records as $record ) {
			$insert[] = $record;
		}

		// Records left in $prod_records should be deleted.
		foreach ( $prod_records as $record ) {
			$delete[] = $record;
		}

		foreach( $delete as $record ) {
			$this->delete_postmeta(
				array( 'meta_id' => $record['meta_id'] ),
				array( '%d' )
			);
		}

		foreach ( $insert as $record ) {
			$this->insert_postmeta( $record );
		}

		foreach( $update as $record ) {
			$this->update_postmeta( $record );
		}
	}

	/**
	 * Go through provided postmeta and filter out all values that we accept
	 * adding to the database postmeta table. Create an array containing all
	 * data to insert into database and an array containing the format of
	 * each value we want to insert.
	 *
	 * @param array $postmeta
	 * @return array
	 */
	private function filter_input( $postmeta ) {

		$data   = array();
		$format = array();

		if ( isset( $postmeta['post_id'] ) ) {
			$data['post_id'] = $postmeta['post_id'];
			$format[]        = '%d';
		}

		if ( isset( $postmeta['meta_key'] ) ) {
			$data['meta_key'] = $postmeta['meta_key'];
			$format[]         = '%s';
		}

		if ( isset( $postmeta['meta_value'] ) ) {
			$data['meta_value'] = $postmeta['meta_value'];
			$format[]           = '%s';
		}

		return array(
			'data'   => $data,
			'format' => $format,
		);
	}
}