<?php
namespace Me\Stenberg\Content\Staging\DB;

class DAO {

	protected $wpdb;

	protected function __constuct( $wpdb ) {
		$this->wpdb = $wpdb;
	}

	/**
	 * Insert data.
	 *
	 * @param string $table
	 * @param array $data
	 * @param array $format
	 * @return int
	 */
	public function insert( $table, $data, $format ) {
		$this->wpdb->insert( $this->wpdb->$table, $data, $format );
		return $this->wpdb->insert_id;
	}

	/**
	 * Update data.
	 *
	 * @param string $table
	 * @param array $data
	 * @param array $where
	 * @param array $format
	 * @param array $where_format
	 */
	public function update( $table, $data, $where, $format = null, $where_format = null ) {
		$this->wpdb->update( $this->wpdb->$table, $data, $where, $format, $where_format );
	}

	/**
	 * Wrapper for WordPress function get_post_meta.
	 *
	 * @see http://codex.wordpress.org/Function_Reference/get_post_meta
	 *
	 * @param int $post_id
	 * @param string $key
	 * @param bool $single
	 * @return mixed
	 */
	public function get_post_meta( $post_id, $key = '', $single = false ) {
		return get_post_meta( $post_id, $key, $single );
	}

	/**
	 * Wrapper for WordPress function update_post_meta.
	 *
	 * @see http://codex.wordpress.org/Function_Reference/update_post_meta
	 *
	 * @param int $post_id
	 * @param string $meta_key
	 * @param mixed $meta_value
	 * @param mixed $prev_value
	 * @return bool|int
	 */
	public function update_post_meta( $post_id, $meta_key, $meta_value, $prev_value = null ) {
		return update_post_meta( $post_id, $meta_key, $meta_value, $prev_value );
	}

	/**
	 * Take an array of values that will be used in a SQL IN clause and
	 * create placeholders that can be used together with wpdb->prepare.
	 *
	 * Example of a SQL IN clause:
	 * SELECT * FROM table WHERE id IN (1, 2, 3, ...)
	 *
	 * @param array $values Values to use in SQL IN clause.
	 * @param string $format Format of values in $values, %s for strings, %d for digits.
	 * @return string A string of placeholders, e.g. %d, %d, %d, ...
	 */
	protected function in_clause_placeholders( $values, $format = '%s' ) {

		$nbr_of_values = count( $values );

		if ( $nbr_of_values <= 0 ) {
			return '';
		}

		// Prepare the right amount of placeholders.
		$placeholders = array_fill( 0, $nbr_of_values, $format );

		// Transform array of placeholders into comma separated string.
		return implode( ', ', $placeholders );
	}

	/**
	 * The content staging database is most likely created from a dump of the
	 * production database. In this dumb the production domain name might
	 * have been replaced by a domain name specific for the content stage.
	 * To be able to compare GUIDs between content stage and production we
	 * need to normalize the URL, in this case that means stripping the
	 * domain name.
	 *
	 * @param string $guid
	 * @return string
	 */
	protected function normalize_guid( $guid ) {

		$info = parse_url( $guid );

		if ( ! isset( $info['scheme'] ) || ! isset( $info['host'] ) ) {
			return null;
		}

		return str_replace( $info['scheme'] . '://' . $info['host'], '', $guid );
	}

}