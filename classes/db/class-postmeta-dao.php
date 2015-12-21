<?php
namespace Me\Stenberg\Content\Staging\DB;

use Me\Stenberg\Content\Staging\Models\Model;

class Postmeta_DAO extends DAO {

	public function __construct( $wpdb ) {
		parent::__construct( $wpdb );
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
	 * Insert multiple post meta records.
	 *
	 * @param array $records
	 */
	public function insert_post_meta_records( $records = array() ) {
		$values = array();
		$format = '';

		for ( $i = 0; $i < count( $records ); $i++ ) {
			if ( $i != 0 ) {
				$format .= ',';
			}
			$values[] = $records[$i]['post_id'];
			$values[] = $records[$i]['meta_key'];
			$values[] = $records[$i]['meta_value'];
			$format  .= '(%d, %s, %s)';
		}

		if ( ! $format ) {
			return;
		}

		$this->wpdb->query(
			$this->wpdb->prepare(
				'INSERT INTO ' . $this->get_table() . ' (post_id, meta_key, meta_value) VALUES ' . $format,
				$values
			)
		);
	}

	/**
	 * Add post meta record.
	 *
	 * @param int    $post_id
	 * @param string $key
	 * @param string $value
	 * @param bool   $unique
	 *
	 * @return int
	 */
	public function add_post_meta( $post_id, $key, $value, $unique = false ) {

		if ( is_array( $value ) ) {
			$value = serialize( $value );
		}

		$this->wpdb->insert(
			$this->wpdb->postmeta,
			array(
				'post_id'    => $post_id,
				'meta_key'   => $key,
				'meta_value' => $value,
			),
			array( '%d', '%s', '%s' )
		);
	}

	/**
	 * Get post meta record(s).
	 *
	 * @param int    $post_id
	 * @param string $key
	 * @param bool   $single
	 *
	 * @return array
	 */
	public function get_post_meta( $post_id, $key = null, $single = false ) {

		$where_stmt = 'post_id = %d';
		$query_vars = array( $post_id );

		if ( $key ) {
			$where_stmt .= ' AND meta_key = %s';
			array_push( $query_vars, $key );
		}

		$query = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->wpdb->postmeta . ' WHERE ' . $where_stmt,
			$query_vars
		);

		$records = $this->wpdb->get_results( $query, ARRAY_A );

		if ( isset( $records[0] ) && $single ) {
			if ( is_serialized( $records[0]['meta_value'] ) ) {
				return unserialize( $records[0]['meta_value'] );
			}

			return $records[0]['meta_value'];
		}

		$values = array();

		foreach ( $records as $record ) {
			array_push( $values, unserialize( $record['meta_value'] ) );
		}

		return $values;
	}

	/**
	 * Insert postmeta.
	 *
	 * @param array $postmeta
	 *
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

		foreach ( $delete as $record ) {
			$this->delete_postmeta(
				array( 'meta_id' => $record['meta_id'] ),
				array( '%d' )
			);
		}

		foreach ( $insert as $record ) {
			$this->insert_postmeta( $record );
		}

		foreach ( $update as $record ) {
			$this->update_postmeta( $record );
		}
	}

	public function delete_postmeta( $where, $where_format ) {
		$this->wpdb->delete( $this->wpdb->postmeta, $where, $where_format );
	}

	/**
	 * @return string
	 */
	protected function get_table() {
		return $this->wpdb->postmeta;
	}

	protected function target_class() {}
	protected function unique_key( array $raw ) {}
	protected function select_stmt() {}
	protected function select_by_ids_stmt( array $ids ) {}
	protected function do_insert( Model $obj ) {}
	protected function do_create_object( array $raw ) {}
	protected function do_create_array( Model $obj ) {}
	protected function format() {}

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