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
		$query = $this->wpdb->prepare(
			'SELECT option_value FROM ' . $this->wpdb->options . ' WHERE option_name LIKE %s',
			'_sme_' . $key . '_post_%'
		);

		return $this->wpdb->get_col( $query );
	}

}
