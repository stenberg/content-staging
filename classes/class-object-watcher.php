<?php
namespace Me\Stenberg\Content\Staging;

use Me\Stenberg\Content\Staging\Models\Model;

class Object_Watcher {

	private $all = array();

	private static $instance = null;

	private function __construct() {
		// Nothing here atm.
	}

	static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new Object_Watcher();
		}
		return self::$instance;
	}

	public function global_key( Model $obj ) {
		$key = get_class( $obj ) . '.' . $obj->get_id();
		return $key;
	}

	static function add( Model $obj ) {
		$inst = self::instance();
		$inst->all[$inst->global_key( $obj )] = $obj;
	}

	static function exists( $classname, $id ) {
		$inst = self::instance();
		$key  = $classname . '.' . $id;
		if ( isset( $inst->all[$key] ) ) {
			return $inst->all[$key];
		}
		return null;
	}

}