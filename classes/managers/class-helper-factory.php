<?php
namespace Me\Stenberg\Content\Staging;

use Exception;
use Me\Stenberg\Content\Staging\DB\DAO;

class Helper_Factory {

	private $dao = array();
	private static $instance;

	private function __construct() {
		// Nothing here.
	}

	public static function get_instance() {
		if ( empty( self::$instance ) ) {
			self::$instance = new Helper_Factory();
		}
		return self::$instance;
	}

	public function add_dao( DAO $dao ) {
		$key = get_class( $dao );
		$this->dao[$key] = $dao;
	}

	public function get_dao( $key ) {
		$key = 'Me\Stenberg\Content\Staging\DB\\' . $key . '_DAO';
		if ( ! isset( $this->dao[$key] ) ) {
			throw new Exception( 'Class not found: ' . $key );
		}
		return $this->dao[$key];
	}

}