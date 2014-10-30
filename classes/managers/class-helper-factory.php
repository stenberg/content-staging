<?php
namespace Me\Stenberg\Content\Staging;

use Exception;

class Helper_Factory {

	private $wpdb;
	private $db          = array();
	private $controllers = array();
	private static $instance;

	private function __construct( $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public static function get_instance() {
		if ( empty( self::$instance ) ) {
			global $wpdb;
			self::$instance = new Helper_Factory( $wpdb );
		}
		return self::$instance;
	}

	public function get_dao( $key ) {
		return $this->get( 'DB', $key . '_DAO' );
	}

	private function get( $type, $key ) {
		$class = 'Me\Stenberg\Content\Staging\\' . $type . '\\' . $key;
		$array = strtolower( $type );
		if ( isset( $this->$array[$class] ) ) {
			return $this->$array[$class];
		}
		if ( ! class_exists( $class ) ) {
			throw new Exception( 'Class ' . $class . ' not found' );
		}
		if ( $array == 'db' ) {
			$instance = new $class( $this->wpdb );
		} else {
			$instance = new $class();
		}
		$this->$array[$class] = $instance;
		return $this->$array[$class];
	}

}