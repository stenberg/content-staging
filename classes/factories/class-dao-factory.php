<?php
namespace Me\Stenberg\Content\Staging\Factories;

use Me\Stenberg\Content\Staging\DB\DAO;
use Me\Stenberg\Content\Staging\Helper_Factory;
use wpdb;

class DAO_Factory {

	/**
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * Constructor.
	 *
	 * Injecting wpdb even though it is not used. Want to have the option to
	 * easily get rid of the Helper_Factory in the future. In that case wpdb
	 * will have to be injected into DAO:s.
	 *
	 * @param wpdb $wpdb
	 */
	public function __construct( wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	/**
	 * Create DAO object of specified type.
	 *
	 * @param string $dao
	 *
	 * @return DAO
	 */
	public function create( $dao ) {
		return Helper_Factory::get_instance()->get_dao( $dao );
	}
}