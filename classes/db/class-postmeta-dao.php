<?php
namespace Me\Stenberg\Content\Staging\DB;

use Me\Stenberg\Content\Staging\Models\Model;
use Me\Stenberg\Content\Staging\Models\Post;

class Postmeta_DAO extends DAO {

	private $table;

	public function __construct( $wpdb ) {
		parent::__constuct( $wpdb );
		$this->table = $wpdb->postmeta;
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
	public function insert_post_meta( $records = array() ) {
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
				'INSERT INTO ' . $this->table . ' (post_id, meta_key, meta_value) VALUES ' . $format,
				$values
			)
		);
	}

	/**
	 * Insert post meta record.
	 *
	 * @param array $record
	 * @return int
	 */
	public function insert_post_meta_record( $record ) {
		$this->wpdb->insert(
			$this->wpdb->postmeta,
			array(
				'post_id'    => $record['post_id'],
				'meta_key'   => $record['meta_key'],
				'meta_value' => $record['meta_value'],
			),
			array( '%d', '%s', '%s' )
		);
	}

	/**
	 * Update post meta record.
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
	 * @return string
	 */
	protected function get_table() {
		return $this->table;
	}

	protected function target_class() {}
	protected function unique_key( array $raw ) {}
	protected function select_stmt() {}
	protected function select_by_ids_stmt( array $ids ) {}
	protected function do_insert( Model $obj ) {}
	protected function do_create_object( array $raw ) {}
	protected function do_create_array( Model $obj ) {}
	protected function format() {}

}