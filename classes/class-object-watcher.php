<?php
namespace Me\Stenberg\Content\Staging;

use Me\Stenberg\Content\Staging\Models\Model;

class Object_Watcher {

	private $all    = array();
	private $dirty  = array();
	private $new    = array();
	private $delete = array();

	private static $instance = null;

	private function __construct() {
		// Nothing here atm.
	}

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new Object_Watcher();
		}
		return self::$instance;
	}

	public function global_key( Model $obj ) {
		$key = get_class( $obj ) . '.' . $obj->get_id();
		return $key;
	}

	public static function add( Model $obj ) {
		$inst = self::instance();
		$inst->all[$inst->global_key( $obj )] = $obj;
	}

	public static function exists( $classname, $id ) {
		$inst = self::instance();
		$key  = $classname . '.' . $id;
		if ( isset( $inst->all[$key] ) ) {
			return $inst->all[$key];
		}
		return null;
	}

	/**
	 * @deprecated
	 * @param Model $obj
	 */
	public static function add_delete( Model $obj ) {
		$self = self::instance();
		$self->delete[$self->global_key( $obj )] = $obj;
	}

	/**
	 * @deprecated
	 * @param Model $obj
	 */
	public static function add_dirty( Model $obj ) {
		$inst = self::instance();
		if ( ! in_array( $obj, $inst->new, true ) ) {
			$inst->dirty[$inst->global_key( $obj )] = $obj;
		}
	}

	/**
	 * @deprecated
	 * @param Model $obj
	 */
	public static function add_new( Model $obj ) {
		$inst = self::instance();
		// We don't yet have an ID.
		$inst->new[] = $obj;
	}

	/**
	 * @deprecated
	 * @param Model $obj
	 */
	public static function add_clean( Model $obj ) {
		$self = self::instance();
		unset( $self->delete[$self->global_key( $obj )] );
		unset( $self->dirty[$self->global_key( $obj )] );
		$self->new = array_filter(
			$self->new,
			function( $a ) use ( $obj ) {
				return !( $a === $obj );
			}
		);
	}

}