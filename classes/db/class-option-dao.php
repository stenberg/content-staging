<?php
namespace Me\Stenberg\Content\Staging\DB;

use Exception;
use Me\Stenberg\Content\Staging\Models\Model;
use Me\Stenberg\Content\Staging\Models\Option;

class Option_DAO extends DAO {

	public function __construct( $wpdb ) {
		parent::__construct( $wpdb );
	}

	/**
	 * Get all options that has been selected for syncing.
	 *
	 * Utility method that wraps two methods of this class.
	 *
	 * @return array Array of Option objects.
	 */
	public function get_options_to_sync() {
		$names = $this->get_option_names_to_sync();
		return $this->get_options_by_names( $names );
	}

	/**
	 * Get specific options defined by array of names passed to this method.
	 *
	 * @param array $names
	 * @return array Array of Option objects.
	 */
	public function get_options_by_names( array $names ) {

		// Array of options.
		$options = array();

		// Make sure array of names only contain strings.
		$names = array_filter( $names, array( $this, 'is_non_empty_string' ) );

		// Return empty result set if no valid names has been provided.
		if ( empty( $names ) ) {
			return $options;
		}

		// Statement placeholders for option names.
		$placeholders = implode( ',', array_fill( 0, count( $names ), '%s' ) );

		$stmt   = 'SELECT * FROM ' . $this->get_table() . ' WHERE option_name IN (' . $placeholders . ')';
		$query  = $this->wpdb->prepare( $stmt, $names );
		$result = $this->wpdb->get_results( $query, ARRAY_A );
		$result = $result ? $result : array();

		foreach ( $result as $option ) {

			// Sanity check, make sure row is valid.
			if ( isset( $option['option_id'] ) ) {
				$options[] = $this->create_object( $option );
			}
		}

		return $options;
	}

	/**
	 * Get names of all WordPress options we wish to sync.
	 *
	 * @return array Numeric array where each value is a non empty string
	 * specifying the option name we wish to sync.
	 */
	public function get_option_names_to_sync() {

		$names = get_option( 'sme_wp_options', array() );

		// Make sure array of names only contain strings.
		return array_filter( $names, array( $this, 'is_non_empty_string' ) );
	}

	/**
	 * Insert options.
	 *
	 * Using the WordPress method 'update_option()' to insert/update options
	 * one by one.
	 *
	 * @todo Consider changing the implementation so that all options are
	 * imported in one query.
	 *
	 * @param array $options
	 */
	public function insert_options( array $options ) {
		foreach ( $options as $option ) {
			if ( $option instanceof Option ) {
				update_option( $option->get_name(), $option->get_value(), $option->get_autoload() );
			}
		}
	}

	/**
	 * @return string
	 */
	protected function get_table() {
		return $this->wpdb->options;
	}

	/**
	 * @return string
	 */
	protected function target_class() {
		return '\Me\Stenberg\Content\Staging\Models\Option';
	}

	/**
	 * @param array $raw
	 * @return string
	 */
	protected function unique_key( array $raw ) {
		return $raw['option_id'];
	}

	/**
	 * @return string
	 */
	protected function select_stmt() {
		return 'SELECT * FROM ' . $this->get_table() . ' WHERE option_id = %d';
	}

	/**
	 * @param array $ids
	 * @return string
	 */
	protected function select_by_ids_stmt( array $ids ) {
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		return 'SELECT * FROM ' . $this->get_table() . ' WHERE option_id in (' . $placeholders . ')';
	}

	/**
	 * @param Model $obj
	 */
	protected function do_insert( Model $obj ) {
		$data   = $this->create_array( $obj );
		$format = $this->format();
		$this->wpdb->insert( $this->get_table(), $data, $format );
		$obj->set_id( $this->wpdb->insert_id );
	}

	/**
	 * @param array $raw
	 * @return Option
	 */
	protected function do_create_object( array $raw ) {
		$obj = new Option( $raw['option_id'] );

		$obj->set_name( $raw['option_name'] );
		$obj->set_value( $raw['option_value'] );
		$obj->set_autoload( $raw['autoload'] );

		return $obj;
	}

	/**
	 * @param Model|Option $obj
	 * @return array
	 * @throws Exception
	 */
	protected function do_create_array( Model $obj ) {

		if ( ! $obj instanceof Option ) {
			throw new Exception( 'Object must be of type \'Option\'' );
		}

		return array(
			'option_name'  => $obj->get_name(),
			'option_value' => $obj->get_value(),
			'autoload'     => $obj->get_autoload(),
		);
	}

	/**
	 * Format of each of the values in the result set.
	 *
	 * Important! Must mimic the array returned by the
	 * 'do_create_array' method.
	 *
	 * @return array
	 */
	protected function format() {
		return array(
			'%s', // option_name
			'%s', // option_value
			'%s', // autoload
		);
	}

}
