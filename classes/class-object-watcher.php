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
		$key = get_class( $obj ) . '.' . get_current_blog_id() . '.' . $obj->get_id();
		return $key;
	}

	public static function add( Model $obj ) {
		$inst = self::instance();
		$inst->all[$inst->global_key( $obj )] = $obj;
	}

	public static function exists( $classname, $id ) {
		$inst = self::instance();
		$key  = $classname . '.' . get_current_blog_id() . '.' . $id;
		if ( isset( $inst->all[$key] ) ) {
			return $inst->all[$key];
		}
		return null;
	}

	/**
	 * Remove objects for a specific blog. Useful to avoid memory issues
	 * in multi-site setup.
	 *
	 * @param int $blog_id ID of blog you want to remove objects for. Defaults to current blog.
	 */
	public static function delete_by_blog( $blog_id = null ) {

		$inst = self::instance();

		if ( ! $blog_id ) {
			$blog_id = get_current_blog_id();
		}

		// Delete all objects for the specified blog.
		foreach ( $inst->all as $key => $obj ) {
			$parts = explode( '.', $key );
			$parts_count = count( $parts );
			if ( isset( $parts[1] ) && $parts_count == 3 && $blog_id == $parts[1] ) {
				unset( $inst->all[$key] );
			}
		}
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