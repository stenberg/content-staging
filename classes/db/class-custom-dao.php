<?php
namespace Me\Stenberg\Content\Staging\DB;

use wpdb;

class Custom_DAO {

	/**
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * Constructor.
	 *
	 * @param wpdb $wpdb
	 */
	public function __construct( $wpdb ) {
		$this->wpdb = $wpdb;
	}

	/**
	 * Get post GUIDs of deleted posts.
	 *
	 * @param string $key
	 *
	 * @return array
	 */
	public function get_deleted_posts( $key = 'delete' ) {

		$posts = array();

		$query = $this->wpdb->prepare(
			'SELECT option_value FROM ' . $this->wpdb->options . ' WHERE option_name LIKE %s',
			'_sme_' . $key . '_post_%'
		);

		$result = $this->wpdb->get_col( $query );

		foreach ( $result as $row ) {
			if ( is_serialized( $row ) ) {
				$row = unserialize( $row );
			}

			if ( is_array( $row ) ) {
				array_push( $posts, $row );
			}
		}

		return $posts;
	}


	public function remove_from_deleted_posts_log( array $post_ids ) {

		$result = $this->wpdb->get_results(
			'SELECT * FROM ' . $this->wpdb->options . ' WHERE option_name LIKE "_sme_delete_post_%"',
			ARRAY_A
		);

		foreach ( $result as $row ) {

			if ( ! isset( $row['option_name'] ) || ! isset( $row['option_value'] ) ) {
				continue;
			}

			// Extract the post ID.
			$post_id = substr( $row['option_name'], strlen( '_sme_delete_post_' ) );

			if ( in_array( $post_id, $post_ids ) ) {
				delete_option( '_sme_delete_post_' . $post_id );
			}
		}
	}

	/**
	 * Get base prefix of database tables.
	 *
	 * @return string
	 */
	public function get_table_base_prefix() {
		return $this->wpdb->base_prefix;
	}

}
