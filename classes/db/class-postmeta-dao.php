<?php
namespace Me\Stenberg\Content\Staging\DB;

class Postmeta_DAO extends DAO {

	public function __construct( $wpdb ) {
		parent::__constuct( $wpdb );
	}

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

	public function delete_postmeta( $where, $where_format ) {
		$this->wpdb->delete( $this->wpdb->postmeta, $where, $where_format );
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